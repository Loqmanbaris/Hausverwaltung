<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Lease;
use App\Models\NkSettlement;
use App\Models\NkSettlementCost;
use App\Models\Property;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Nk\NkService;
use App\Support\Rent\RentLedger;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Nebenkostenabrechnungen: anlegen, Kosten prüfen, berechnen, drucken, abschließen. */
class NkController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $ownerIds = $this->visibleOwnerIds();
        $settlements = NkSettlement::query()->whereIn('owner_id', $ownerIds)->with(['property', 'owner'])
            ->orderByDesc('period_from')->orderBy('property_id')->get();
        $properties = Property::query()->whereIn('owner_id', $ownerIds)->with('owner')->orderBy('name')->get();

        return view('nk.index', [
            'settlements' => $settlements,
            'properties' => $properties,
            'defaultYear' => (int) date('Y') - 1,
        ]);
    }

    public function store(Request $request)
    {
        $properties = Property::query()->whereIn('owner_id', $this->visibleOwnerIds())->pluck('id')->all();
        $data = $request->validate([
            'property_id' => ['required', 'integer', Rule::in($properties)],
            'period_from' => ['required', 'date_format:Y-m-d'],
            'period_to' => ['required', 'date_format:Y-m-d', 'after:period_from'],
        ], ['property_id.*' => 'Bitte ein Objekt wählen.', 'period_from.*' => 'Bitte den Beginn angeben.', 'period_to.*' => 'Das Ende muss nach dem Beginn liegen.']);

        $days = (new DateTimeImmutable($data['period_from']))->diff(new DateTimeImmutable($data['period_to']))->days + 1;
        if ($days > 366) {
            return back()->withInput()->withErrors(['period_to' => 'Der Abrechnungszeitraum darf höchstens zwölf Monate lang sein.']);
        }
        $overlap = NkSettlement::query()->where('property_id', $data['property_id'])
            ->where('period_from', '<=', $data['period_to'])->where('period_to', '>=', $data['period_from'])->first();
        if ($overlap) {
            return redirect()->route('nk.show', $overlap)->with('status', 'Für dieses Objekt gibt es in diesem Zeitraum schon eine Abrechnung.');
        }

        $property = Property::query()->findOrFail($data['property_id']);
        $s = NkSettlement::create($data + ['owner_id' => $property->owner_id, 'status' => 'entwurf', 'created_by' => $this->me()->id]);
        $n = NkService::importReceipts($s);
        Audit::log('nk.created', $s, 'Nebenkostenabrechnung angelegt: '.$property->name.', '.$s->periodLabel());

        return redirect()->route('nk.show', $s)->with('status', 'Abrechnung angelegt. '.$n.' Beleg'.($n === 1 ? '' : 'e').' mit umlagefähigen Kosten übernommen.');
    }

    public function show(NkSettlement $settlement)
    {
        $this->assertOwnerAccess((int) $settlement->owner_id);
        $settlement->load(['property.units', 'owner']);
        $result = $settlement->isFinal() ? $settlement->snapshot() : NkService::compute($settlement);
        $costs = NkSettlementCost::query()->where('nk_settlement_id', $settlement->id)->with(['receipt', 'unit'])
            ->get()->sortBy(fn ($c) => [$c->cost_key, $c->receipt?->receipt_date?->format('Y-m-d')])->values();
        $allocatable = DB::table('cost_types')->where('allocatable', true)->orderBy('sort')->pluck('label', 'key')->all();

        return view('nk.show', compact('settlement', 'result', 'costs', 'allocatable'));
    }

    public function refresh(NkSettlement $settlement)
    {
        $this->editable($settlement);
        $n = NkService::importReceipts($settlement);

        return redirect()->route('nk.show', $settlement)->with('status', $n === 0 ? 'Keine neuen Belege gefunden.' : $n.' neue Belege übernommen.');
    }

    public function toggleCost(NkSettlement $settlement, NkSettlementCost $cost)
    {
        $this->editable($settlement);
        abort_unless((int) $cost->nk_settlement_id === (int) $settlement->id, 404);
        $cost->update(['included' => ! $cost->included]);

        return redirect()->to(route('nk.show', $settlement).'#kosten')->with('status', $cost->included ? 'Position wird wieder berücksichtigt.' : 'Position wird nicht abgerechnet.');
    }

    public function storeCost(Request $request, NkSettlement $settlement)
    {
        $this->editable($settlement);
        $allowed = DB::table('cost_types')->where('allocatable', true)->pluck('key')->all();
        $data = $request->validate([
            'cost_key' => ['required', Rule::in($allowed)],
            'amount' => ['required', 'string', 'max:20'],
            'label' => ['required', 'string', 'max:190'],
            'unit_id' => ['nullable', 'integer', Rule::in($settlement->property->units()->pluck('id')->all())],
        ], ['cost_key.*' => 'Bitte eine Kostenart wählen.', 'label.required' => 'Bitte kurz beschreiben, z. B. „Abgrenzung Grundsteuer Q4“.']);
        $amount = Money::parse($data['amount']);
        if ($amount === null || (float) $amount == 0.0) {
            return back()->withInput()->withErrors(['amount' => 'Der Betrag ist ungültig. Für eine Minderung ein Minus davor setzen.']);
        }
        NkSettlementCost::create(['nk_settlement_id' => $settlement->id, 'cost_key' => $data['cost_key'], 'amount' => $amount,
            'label' => $data['label'], 'unit_id' => $data['unit_id'] ?? null, 'included' => true]);

        return redirect()->to(route('nk.show', $settlement).'#kosten')->with('status', 'Position hinzugefügt.');
    }

    public function destroyCost(NkSettlement $settlement, NkSettlementCost $cost)
    {
        $this->editable($settlement);
        abort_unless((int) $cost->nk_settlement_id === (int) $settlement->id && $cost->receipt_id === null, 404);
        $cost->delete();

        return redirect()->to(route('nk.show', $settlement).'#kosten')->with('status', 'Position entfernt.');
    }

    public function updateNote(Request $request, NkSettlement $settlement)
    {
        $this->editable($settlement);
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        $settlement->update(['note' => trim((string) ($data['note'] ?? '')) ?: null]);

        return redirect()->route('nk.show', $settlement)->with('status', 'Text gespeichert.');
    }

    /** CO2-Menge und CO2-Kosten laut Rechnung des Versorgers (Paket 26). Leere Felder löschen den Wert. */
    public function saveCo2(Request $request, NkSettlement $settlement)
    {
        $this->editable($settlement);
        $request->validate([
            'co2_kg' => ['nullable', 'string', 'max:20'],
            'co2_cost' => ['nullable', 'string', 'max:20'],
            'co2_area' => ['nullable', 'string', 'max:20'],
        ]);
        $vals = [];
        $labels = ['co2_kg' => 'Die CO2-Menge', 'co2_cost' => 'Die CO2-Kosten', 'co2_area' => 'Die Wohnfläche'];
        foreach ($labels as $f => $label) {
            $raw = trim((string) $request->input($f));
            if ($raw === '') {
                $vals[$f] = null;

                continue;
            }
            $v = Money::parse($raw);
            if ($v === null || (float) $v < 0) {
                return redirect()->to(route('nk.show', $settlement).'#co2')->withInput()->withErrors([$f => $label.' ist keine gültige Zahl.']);
            }
            $vals[$f] = $v;
        }
        $settlement->update($vals);
        Audit::log('nk.co2', $settlement, 'CO2-Angaben gespeichert: '.$settlement->property?->name.', '.$settlement->periodLabel());

        return redirect()->to(route('nk.show', $settlement).'#co2')->with('status', 'CO2-Angaben gespeichert.');
    }

    public function finalize(Request $request, NkSettlement $settlement)
    {
        $this->editable($settlement);
        $data = $request->validate(['issued_on' => ['required', 'date_format:Y-m-d']], ['issued_on.*' => 'Bitte das Datum der Abrechnung angeben.']);
        try {
            NkService::finalize($settlement, $data['issued_on'], $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['nk' => $e->getMessage()]);
        }

        return redirect()->route('nk.show', $settlement)->with('status', 'Abrechnung abgeschlossen. Nachzahlungen stehen jetzt als offene Posten im Mieterkonto, Guthaben werden mit der nächsten Miete verrechnet.');
    }

    public function reopen(NkSettlement $settlement)
    {
        $this->assertOwnerAccess((int) $settlement->owner_id);
        abort_unless($settlement->isFinal(), 404);
        NkService::reopen($settlement);

        return redirect()->route('nk.show', $settlement)->with('status', 'Die Abrechnung ist wieder ein Entwurf. Die Buchungen im Mieterkonto wurden entfernt.');
    }

    public function destroy(NkSettlement $settlement)
    {
        $this->editable($settlement);
        DB::transaction(function () use ($settlement) {
            Audit::log('nk.deleted', $settlement, 'Nebenkostenabrechnung (Entwurf) gelöscht: '.$settlement->property?->name.', '.$settlement->periodLabel());
            $settlement->costs()->delete();
            $settlement->delete();
        });

        return redirect()->route('nk.index')->with('status', 'Entwurf gelöscht.');
    }

    /** Druckansicht: alle Mieter oder einer (?vertrag=ID). */
    public function print(Request $request, NkSettlement $settlement)
    {
        $this->assertOwnerAccess((int) $settlement->owner_id);
        $settlement->load(['property', 'owner']);
        $result = $settlement->isFinal() ? $settlement->snapshot() : NkService::compute($settlement);
        $only = (int) $request->query('vertrag');
        $leaseIds = array_keys($result['leases']);
        if ($only) {
            abort_unless(in_array($only, array_map('intval', $leaseIds), true), 404);
            $leaseIds = [$only];
        }
        $leases = Lease::query()->whereIn('id', $leaseIds)->with(['tenant.contacts', 'unit'])->get()->keyBy('id');

        return view('nk.print', compact('settlement', 'result', 'leaseIds', 'leases'));
    }

    /** Vorschlag für die neue Vorauszahlung als Mietänderung übernehmen. */
    public function applySuggestion(Request $request, NkSettlement $settlement, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        abort_unless($settlement->isFinal(), 404);
        $data = $request->validate([
            'valid_from' => ['required', 'date_format:Y-m'],
            'operating' => ['required', 'string', 'max:20'],
            'heating' => ['required', 'string', 'max:20'],
        ]);
        $from = $data['valid_from'].'-01';
        $op = Money::parse($data['operating']);
        $he = Money::parse($data['heating']);
        if ($op === null || $he === null || (float) $op < 0 || (float) $he < 0) {
            return back()->withErrors(['nk' => 'Die Beträge für die neue Vorauszahlung sind ungültig.']);
        }
        if ($from <= $lease->start_date->format('Y-m-d') || $lease->rents()->where('valid_from', $from)->exists()) {
            return back()->withErrors(['nk' => 'Für diesen Monat gibt es schon eine Mietänderung, oder er liegt vor Vertragsbeginn.']);
        }
        $latest = $lease->rents()->orderByDesc('valid_from')->first();
        $values = $latest ? $latest->only(['rent_cold', 'parking', 'garage', 'furniture']) : [];
        $lease->rents()->create($values + [
            'valid_from' => $from,
            'reason' => 'vorauszahlung',
            'prepay_operating' => $op,
            'prepay_heating' => $he,
            'note' => 'nach '.'Nebenkostenabrechnung '.$settlement->periodLabel(),
            'created_by' => $this->me()->id,
        ]);
        Audit::log('lease.rent_added', $lease, 'Neue Vorauszahlung ab '.substr($from, 5, 2).'/'.substr($from, 0, 4).' nach Nebenkostenabrechnung');
        RentLedger::sync($lease);

        return redirect()->route('nk.show', $settlement)->with('status', 'Neue Vorauszahlung ab '.substr($from, 5, 2).'/'.substr($from, 0, 4).' gespeichert.');
    }

    private function editable(NkSettlement $s): void
    {
        $this->assertOwnerAccess((int) $s->owner_id);
        abort_if($s->isFinal(), 403, 'Die Abrechnung ist abgeschlossen.');
    }
}
