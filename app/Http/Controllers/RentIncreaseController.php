<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Lease;
use App\Models\RentIncrease;
use App\Support\Audit;
use App\Support\Rent\Erhoehung\Regeln;
use App\Support\Rent\Erhoehung\Schreiben;
use App\Support\Rent\Erhoehung\Verfahren;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Mieterhöhungen: Übersicht, Berechnen, Entwurf, Schreiben, Zustimmung, Übernahme (Paket 27). */
class RentIncreaseController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $ownerId = (int) $request->query('eigentuemer') ?: null;
        if ($ownerId) {
            $this->assertOwnerAccess($ownerId);
        }
        $today = date('Y-m-d');
        $user = $this->me();

        $running = RentIncrease::query()->visibleTo($user)->whereIn('status', ['entwurf', 'erklaert', 'abgelehnt'])
            ->when($ownerId, fn ($q) => $q->where('rent_increases.owner_id', $ownerId))
            ->with(['lease.unit.property', 'lease.tenant'])->orderBy('effective_from')->get();
        $done = RentIncrease::query()->visibleTo($user)->whereIn('status', ['wirksam', 'zurueckgezogen'])
            ->when($ownerId, fn ($q) => $q->where('rent_increases.owner_id', $ownerId))
            ->with(['lease.unit.property', 'lease.tenant'])->orderByDesc('effective_from')->limit(30)->get();

        return view('rentincrease.index', [
            'possible' => Verfahren::possible($user, $ownerId, $today),
            'running' => $running,
            'done' => $done,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'ownerId' => $ownerId,
            'today' => $today,
            'indexMode' => Verfahren::indexMode(),
        ]);
    }

    /** Formular für eine neue Erhöhung (noch ohne Berechnung). */
    public function create(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $lease->load(['unit.property', 'tenant', 'rents']);
        $kind = $this->kindFor($request, $lease);

        return $this->form($lease, $kind, $this->defaults($lease, $kind), null);
    }

    /** Prüfen und berechnen, ohne zu speichern. */
    public function preview(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $lease->load(['unit.property', 'tenant', 'rents']);
        $kind = $this->kindFor($request, $lease);
        $values = $request->except(['_token']);
        $calc = Verfahren::compute($lease, $kind, $values);

        return $this->form($lease, $kind, $values + $this->defaults($lease, $kind), $calc);
    }

    public function store(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $lease->load(['unit.property', 'tenant', 'rents']);
        $kind = $this->kindFor($request, $lease);
        $request->validate(['notice_on' => ['nullable', 'date_format:Y-m-d'], 'note' => ['nullable', 'string', 'max:3000']]);

        try {
            $inc = Verfahren::create($lease, $kind, $request->except(['_token']), $this->me());
        } catch (RuntimeException $e) {
            $values = $request->except(['_token']);
            $calc = Verfahren::compute($lease, $kind, $values);

            return $this->form($lease, $kind, $values + $this->defaults($lease, $kind), $calc, $e->getMessage());
        }

        return redirect()->route('rent_increase.show', $inc)->with('status', 'Der Entwurf wurde gespeichert. Prüfe das Schreiben und verschicke es.');
    }

    public function show(RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);
        $increase->load(['lease.unit.property', 'lease.tenant', 'rent']);

        return view('rentincrease.show', ['inc' => $increase, 'today' => date('Y-m-d')]);
    }

    public function print(RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);

        return view('rentincrease.print', Schreiben::data($increase));
    }

    public function updateNote(Request $request, RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);
        if (! $increase->isDraft()) {
            return back()->withErrors(['note' => 'Der Text kann nur im Entwurf geändert werden.']);
        }
        $data = $request->validate(['note' => ['nullable', 'string', 'max:3000']]);
        $increase->update(['note' => trim((string) ($data['note'] ?? '')) ?: null]);
        Audit::log('rent_increase.note', $increase, 'Zusatztext der Mieterhöhung geändert: '.$increase->title());

        return back()->with('status', 'Zusatztext gespeichert.');
    }

    public function redate(Request $request, RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);
        $data = $request->validate(['notice_on' => ['required', 'date_format:Y-m-d']]);

        return $this->run(fn () => Verfahren::redate($increase, $data['notice_on'], $this->me()), 'Das Datum wurde geändert, die Fristen sind neu berechnet.', $increase);
    }

    public function declare(Request $request, RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);
        $data = $request->validate(['notice_on' => ['nullable', 'date_format:Y-m-d']]);
        $date = $data['notice_on'] ?? null;
        $msg = 'Als erklärt vermerkt. ';
        if ($date !== null && $date !== $increase->notice_on->format('Y-m-d')) {
            $msg .= 'Die Fristen wurden mit dem Zugang am '.Regeln::de($date).' neu berechnet. Bitte das Schreiben neu ausdrucken, falls Daten abweichen.';
        }

        return $this->run(fn () => Verfahren::declare($increase, $this->me(), $date), trim($msg), $increase);
    }

    public function consent(Request $request, RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);
        $data = $request->validate(['decided_on' => ['required', 'date_format:Y-m-d']]);

        return $this->run(fn () => Verfahren::consent($increase, $data['decided_on'], $this->me()), 'Zustimmung vermerkt. Die neue Miete steht jetzt im Mietstand.', $increase);
    }

    public function adopt(RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);

        return $this->run(fn () => Verfahren::adopt($increase, $this->me()), 'Die neue Miete steht jetzt im Mietstand.', $increase);
    }

    public function reject(Request $request, RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);
        $data = $request->validate(['decided_on' => ['required', 'date_format:Y-m-d']]);

        return $this->run(fn () => Verfahren::reject($increase, $data['decided_on'], $this->me()), 'Als abgelehnt vermerkt.', $increase);
    }

    public function withdraw(RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);

        return $this->run(fn () => Verfahren::withdraw($increase, $this->me()), 'Die Erhöhung wurde zurückgezogen.', $increase);
    }

    public function undo(RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);

        return $this->run(fn () => Verfahren::undo($increase, $this->me()), 'Die Erhöhung wurde rückgängig gemacht, der Mietstand ist entfernt.', $increase);
    }

    public function destroy(RentIncrease $increase)
    {
        $this->assertOwnerAccess((int) $increase->owner_id);
        $lease = $increase->lease_id;
        try {
            Verfahren::delete($increase);
        } catch (RuntimeException $e) {
            return back()->withErrors(['increase' => $e->getMessage()]);
        }

        return redirect()->route('rent_increase.index')->with('status', 'Der Entwurf wurde gelöscht.');
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['rent_index_cap_mode' => ['required', Rule::in(array_keys(Regeln::INDEX_CAP_MODES))]]);
        $old = Verfahren::indexMode();
        Settings::putMany(['rent_index_cap_mode' => $data['rent_index_cap_mode']]);
        Audit::log('settings.rent_index_cap', 'Begrenzung der Indexmiete geändert', null, ['modus' => $old], ['modus' => $data['rent_index_cap_mode']]);

        return back()->with('status', 'Einstellung gespeichert.');
    }

    // ------------------------------------------------------------------ Hilfen

    private function run(callable $action, string $okMessage, RentIncrease $increase)
    {
        try {
            $action();
        } catch (RuntimeException $e) {
            return back()->withErrors(['increase' => $e->getMessage()]);
        }

        return redirect()->route('rent_increase.show', $increase)->with('status', $okMessage);
    }

    /** Art aus der Anfrage, sonst die passende Art zum Vertrag. */
    private function kindFor(Request $request, Lease $lease): string
    {
        $kind = (string) $request->input('art', $request->query('art', ''));
        if (array_key_exists($kind, RentIncrease::KINDS)) {
            return $kind;
        }

        return match (true) {
            $lease->adjustment_type === 'index' => 'index',
            $lease->adjustment_type === 'staffel' => 'staffel',
            $lease->lease_type === 'wohnen' => 'vergleich',
            default => 'vereinbarung',
        };
    }

    private function defaults(Lease $lease, string $kind): array
    {
        $d = ['notice_on' => date('Y-m-d'), 'note' => ''];
        if ($kind === 'vergleich') {
            $d += ['cap_percent' => Verfahren::defaultCap($lease), 'basis_type' => 'mietspiegel'];
        }
        if ($kind === 'modernisierung') {
            $d += ['upkeep_percent' => '30'];
        }
        if ($kind === 'vereinbarung') {
            $d += ['effective_from' => date('Y-m-d', strtotime('first day of next month'))];
        }

        return $d;
    }

    private function form(Lease $lease, string $kind, array $values, ?array $calc, ?string $error = null)
    {
        $prior = $kind === 'modernisierung' ? Verfahren::priorModernisation($lease, date('Y-m-d')) : null;

        return view('rentincrease.create', [
            'lease' => $lease,
            'kind' => $kind,
            'values' => $values,
            'calc' => $calc,
            'error' => $error,
            'prior' => $prior,
            'indexMode' => Verfahren::indexMode(),
            'staffeln' => $lease->rents->filter(fn ($r) => $r->reason === 'staffel' && $r->valid_from->format('Y-m-d') >= date('Y-m-d', strtotime('-1 year')))->values(),
        ]);
    }
}
