<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Co2Claim;
use App\Models\Lease;
use App\Models\NkSetting;
use App\Models\RentPayment;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Nk\Co2Split;
use App\Support\Rent\RentLedger;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CO2-Erstattung an Mieter mit eigenem Gas- oder Wärmevertrag (Etagenheizung), § 6 CO2KostAufG (Paket 26).
 * Der Mieter macht den Anspruch innerhalb von 12 Monaten nach der Rechnung seines Versorgers in Textform geltend.
 */
class Co2ClaimController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $claims = Co2Claim::visibleTo($this->me())->with(['lease.tenant', 'lease.unit.property'])->orderByRaw("status = 'offen' desc")->orderByDesc('claimed_on')->orderByDesc('id')->get();
        $leases = Lease::visibleTo($this->me())->where('lease_type', '!=', 'montage')
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', date('Y-m-d', strtotime('-2 years'))))
            ->with(['tenant', 'unit.property'])->get();
        $direct = NkSetting::query()->where('heating_mode', 'direkt')->pluck('property_id')->map(fn ($i) => (int) $i)->all();
        $leases = $leases->sortBy(fn ($l) => [in_array((int) $l->unit?->property_id, $direct, true) ? 0 : 1, $l->unit?->property?->name, $l->unit?->label])->values();
        $selected = $request->integer('vertrag') ?: null;

        return view('co2.index', compact('claims', 'leases', 'direct', 'selected'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'lease_id' => ['required', 'integer'],
            'period_from' => ['required', 'date_format:Y-m-d'],
            'period_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_from'],
            'co2_kg' => ['required', 'string', 'max:20'],
            'co2_cost' => ['required', 'string', 'max:20'],
            'area' => ['nullable', 'string', 'max:20'],
            'claimed_on' => ['nullable', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'lease_id.*' => 'Bitte den Mietvertrag wählen.',
            'period_from.*' => 'Bitte den Beginn des Zeitraums der Versorger-Rechnung angeben.',
            'period_to.*' => 'Das Ende des Zeitraums muss nach dem Beginn liegen.',
            'co2_kg.required' => 'Bitte die CO2-Menge (kg) von der Rechnung angeben.',
            'co2_cost.required' => 'Bitte die CO2-Kosten (€) von der Rechnung angeben.',
        ]);
        $lease = Lease::visibleTo($this->me())->with(['unit.property'])->findOrFail((int) $data['lease_id']);
        $kg = Money::parse($data['co2_kg']);
        $cost = Money::parse($data['co2_cost']);
        $area = trim((string) ($data['area'] ?? '')) !== '' ? Money::parse($data['area']) : (string) ($lease->unit?->area_sqm ?? '');
        if ($kg === null || (float) $kg <= 0) {
            return back()->withInput()->withErrors(['co2_kg' => 'Die CO2-Menge ist keine gültige Zahl.']);
        }
        if ($cost === null || (float) $cost <= 0) {
            return back()->withInput()->withErrors(['co2_cost' => 'Die CO2-Kosten sind kein gültiger Betrag.']);
        }
        if ($area === null || $area === '' || (float) $area <= 0) {
            return back()->withInput()->withErrors(['area' => 'Bitte die Wohnfläche angeben (bei der Einheit fehlt sie).']);
        }
        if ($data['period_from'] < Co2Split::FROM) {
            return back()->withInput()->withErrors(['period_from' => 'Die CO2-Aufteilung gilt erst für Zeiträume ab dem 1.1.2023.']);
        }
        $days = (int) (new DateTimeImmutable($data['period_from']))->diff(new DateTimeImmutable($data['period_to']))->days + 1;
        if ($days > 400) {
            return back()->withInput()->withErrors(['period_to' => 'Der Zeitraum ist länger als ein Jahr. Bitte je Rechnung einzeln erfassen.']);
        }

        $setting = NkSetting::forProperty((int) $lease->unit?->property_id);
        $use = $lease->lease_type === 'gewerbe' ? 'nichtwohn' : 'wohn';
        $restriction = $setting->co2_restriction ?: 'keine';
        $split = Co2Split::calculate((float) $kg, (float) $cost, (float) $area, $days, $use, $restriction, $request->boolean('gas_stove'));

        $claim = Co2Claim::create([
            'owner_id' => $lease->owner_id,
            'lease_id' => $lease->id,
            'period_from' => $data['period_from'],
            'period_to' => $data['period_to'],
            'co2_kg' => $kg,
            'co2_cost' => $cost,
            'area' => $area,
            'gas_stove' => $request->boolean('gas_stove'),
            'building_use' => $use,
            'restriction' => $restriction,
            'kg_per_sqm' => $split['kg_per_sqm'],
            'step' => $split['step'] ?? 0,
            'landlord_pct' => (int) round($split['landlord_pct']),
            'amount' => number_format($split['landlord_cents'] / 100, 2, '.', ''),
            'claimed_on' => $data['claimed_on'] ?? date('Y-m-d'),
            'status' => 'offen',
            'note' => $data['note'] ?? null,
            'created_by' => $this->me()->id,
        ]);
        Audit::log('co2.claim', $claim, 'CO2-Erstattung erfasst: '.$lease->title().', '.Money::format($claim->amount));

        return redirect()->to(route('co2.index').'#erstattung-'.$claim->id)->with('status', 'Berechnet: Anteil Vermieter '.Co2Split::pct($split['landlord_pct']).' = '.Money::format($claim->amount).'.');
    }

    /** Als Gutschrift ins Mieterkonto buchen (wird mit der nächsten Miete verrechnet). */
    public function credit(Co2Claim $claim)
    {
        $this->open($claim);
        DB::transaction(function () use ($claim) {
            $pay = RentPayment::create([
                'lease_id' => $claim->lease_id,
                'owner_id' => $claim->owner_id,
                'paid_on' => date('Y-m-d'),
                'amount' => $claim->amount,
                'method' => 'sonstiges',
                'source' => 'co2',
                'note' => 'Erstattung CO2-Kosten '.$claim->period_from->format('d.m.Y').'–'.$claim->period_to->format('d.m.Y'),
                'created_by' => $this->me()->id,
            ]);
            $claim->update(['status' => 'gutgeschrieben', 'settled_on' => date('Y-m-d'), 'rent_payment_id' => $pay->id]);
            Audit::log('co2.credit', $claim, 'CO2-Erstattung gutgeschrieben: '.$claim->lease?->title().', '.Money::format($claim->amount));
        });
        if ($claim->lease) {
            RentLedger::sync($claim->lease);
        }

        return back()->with('status', 'Die Erstattung steht als Gutschrift im Mieterkonto.');
    }

    public function paid(Request $request, Co2Claim $claim)
    {
        $this->open($claim);
        $data = $request->validate(['settled_on' => ['required', 'date_format:Y-m-d']], ['settled_on.*' => 'Bitte das Datum der Auszahlung angeben.']);
        $claim->update(['status' => 'ausgezahlt', 'settled_on' => $data['settled_on']]);
        Audit::log('co2.paid', $claim, 'CO2-Erstattung ausgezahlt: '.$claim->lease?->title().', '.Money::format($claim->amount));

        return back()->with('status', 'Als ausgezahlt vermerkt.');
    }

    /** Zurück auf „offen“: eine gebuchte Gutschrift wird wieder entfernt. */
    public function reset(Co2Claim $claim)
    {
        $this->assertOwnerAccess((int) $claim->owner_id);
        abort_if($claim->status === 'offen', 404);
        DB::transaction(function () use ($claim) {
            if ($claim->rent_payment_id) {
                DB::table('rent_allocations')->where('rent_payment_id', $claim->rent_payment_id)->delete();
                RentPayment::withTrashed()->whereKey($claim->rent_payment_id)->forceDelete();
            }
            $claim->update(['status' => 'offen', 'settled_on' => null, 'rent_payment_id' => null]);
            Audit::log('co2.reset', $claim, 'CO2-Erstattung wieder offen: '.$claim->lease?->title());
        });
        if ($claim->lease) {
            RentLedger::sync($claim->lease);
        }

        return back()->with('status', 'Die Erstattung ist wieder offen.');
    }

    public function destroy(Co2Claim $claim)
    {
        $this->open($claim);
        Audit::log('co2.deleted', $claim, 'CO2-Erstattung gelöscht: '.$claim->lease?->title().', '.Money::format($claim->amount));
        $claim->delete();

        return redirect()->route('co2.index')->with('status', 'Eintrag gelöscht.');
    }

    /** Berechnung als Schreiben an den Mieter. */
    public function print(Co2Claim $claim)
    {
        $this->assertOwnerAccess((int) $claim->owner_id);
        $claim->load(['lease.tenant.contacts', 'lease.unit.property', 'owner']);

        return view('co2.print', ['claim' => $claim]);
    }

    private function open(Co2Claim $claim): void
    {
        $this->assertOwnerAccess((int) $claim->owner_id);
        abort_unless($claim->status === 'offen', 404);
    }
}
