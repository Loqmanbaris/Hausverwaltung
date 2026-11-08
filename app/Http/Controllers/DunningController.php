<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\DunningNotice;
use App\Models\Lease;
use App\Support\Audit;
use App\Support\Dunning\Mahnwesen;
use App\Support\Money;
use App\Support\Rent\RentLedger;
use App\Support\Settings;
use Illuminate\Http\Request;
use RuntimeException;

/** Mahnwesen: Vorschläge, Schreiben erstellen, drucken, stornieren, Einstellungen. */
class DunningController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        RentLedger::syncAllIfStale();
        $ownerId = (int) $request->query('eigentuemer') ?: null;
        if ($ownerId) {
            $this->assertOwnerAccess($ownerId);
        }
        $rows = Mahnwesen::overview($this->me(), $ownerId);

        $history = DunningNotice::visibleTo($this->me())
            ->when($ownerId, fn ($q) => $q->where('owner_id', $ownerId))
            ->with(['lease.unit.property', 'lease.tenant'])
            ->orderByDesc('issued_on')->orderByDesc('id')->limit(100)->get();

        $settings = [];
        foreach (Mahnwesen::SETTINGS as $key => [$label, $default, $type]) {
            $settings[$key] = ['label' => $label, 'type' => $type, 'value' => $type === 'money' ? Money::input(Mahnwesen::amount($key)) : (string) Mahnwesen::days($key)];
        }

        return view('dunning.index', [
            'rows' => $rows,
            'history' => $history,
            'settings' => $settings,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'ownerId' => $ownerId,
            'created' => collect(explode(',', (string) session('dunning_created')))->filter()->map(fn ($v) => (int) $v)->values(),
            'baseRates' => \App\Support\Interest\Verzugszinsen::baseRates(),
            'missingRate' => \App\Support\Interest\Verzugszinsen::missingCurrentRate(),
        ]);
    }

    /** Mehrere Schreiben auf einmal erstellen (aus der Vorschlagsliste) oder eins aus dem Mietvertrag. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'leases' => ['required', 'array', 'min:1', 'max:200'],
            'leases.*' => ['integer'],
            'level' => ['nullable', 'array'],
            'level.*' => ['integer', 'between:1,3'],
            'note' => ['nullable', 'string', 'max:2000'],
            'back' => ['nullable', 'in:vertrag,tag'],
        ], ['leases.required' => 'Bitte mindestens einen Mietvertrag auswählen.']);

        $created = [];
        $errors = [];
        foreach (array_unique($data['leases']) as $leaseId) {
            $lease = Lease::query()->with(['unit.property', 'tenant'])->find($leaseId);
            if ($lease === null) {
                continue;
            }
            $this->assertOwnerAccess((int) $lease->owner_id);
            $level = (int) ($data['level'][$leaseId] ?? 1);
            try {
                $created[] = Mahnwesen::create($lease, $level, $this->me(), $data['note'] ?? null)->id;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        // Aus „Mein Tag“: gleich das Schreiben zum Drucken öffnen
        if (($data['back'] ?? null) === 'tag') {
            return count($created) === 1
                ? redirect()->route('dunning.print', $created[0])
                : redirect()->route('tag.index')->withErrors(['dunning' => implode(' ', $errors) ?: 'Es wurde kein Schreiben erstellt.']);
        }

        $msg = count($created) === 0 ? 'Es wurde kein Schreiben erstellt.' : (count($created) === 1 ? 'Ein Schreiben erstellt.' : count($created).' Schreiben erstellt.');
        $redirect = ($data['back'] ?? null) === 'vertrag' && count($data['leases']) === 1
            ? redirect()->to(route('leases.show', (int) $data['leases'][0]).'#mahnungen')
            : redirect()->route('dunning.index');

        $redirect = $redirect->with('status', $msg.(count($created) > 0 ? ' Jetzt drucken und versenden.' : ''))
            ->with('dunning_created', implode(',', $created));

        return $errors === [] ? $redirect : $redirect->withErrors(['dunning' => implode(' ', $errors)]);
    }

    /** Druckansicht: ein Schreiben oder mehrere (?ids=1,2,3). */
    public function print(Request $request, ?DunningNotice $notice = null)
    {
        $ids = $notice ? [$notice->id] : collect(explode(',', (string) $request->query('ids')))->map(fn ($v) => (int) $v)->filter()->unique()->take(200)->all();
        abort_if($ids === [], 404);
        $notices = DunningNotice::visibleTo($this->me())->whereIn('id', $ids)
            ->with(['lease.unit.property', 'lease.tenant.contacts', 'owner'])
            ->orderBy('id')->get();
        abort_if($notices->isEmpty(), 404);

        return view('dunning.print', [
            'notices' => $notices,
            'previous' => $notices->mapWithKeys(fn ($n) => [$n->id => Mahnwesen::previous($n)]),
        ]);
    }

    public function cancel(DunningNotice $notice)
    {
        $this->assertOwnerAccess((int) $notice->owner_id);
        Mahnwesen::cancel($notice);

        return back()->with('status', $notice->levelLabel().' vom '.$notice->issued_on->format('d.m.Y').' storniert.'.((float) $notice->fee > 0 ? ' Die Gebühr wurde aus dem Mieterkonto entfernt.' : ''));
    }

    public function saveSettings(Request $request)
    {
        $rules = [];
        foreach (Mahnwesen::SETTINGS as $key => [$label, $default, $type]) {
            $rules[$key] = $type === 'days' ? ['required', 'integer', 'between:0,90'] : ['required', 'string', 'max:20'];
        }
        $data = $request->validate($rules, ['*.integer' => 'Bitte eine ganze Zahl von 0 bis 90 eingeben.', '*.between' => 'Bitte eine ganze Zahl von 0 bis 90 eingeben.']);

        $values = [];
        foreach (Mahnwesen::SETTINGS as $key => [$label, $default, $type]) {
            if ($type === 'money') {
                $v = Money::parse($data[$key]);
                if ($v === null || (float) $v < 0 || (float) $v > 1000) {
                    return back()->withInput()->withErrors([$key => '„'.$label.'“: Bitte einen Betrag von 0 bis 1.000 € eingeben.']);
                }
                $values[$key] = $v;
            } else {
                $values[$key] = (string) (int) $data[$key];
            }
        }
        Settings::putMany($values);
        Audit::log('dunning.settings', null, 'Einstellungen Mahnwesen geändert');

        return redirect()->to(route('dunning.index').'#einstellungen')->with('status', 'Einstellungen gespeichert.');
    }
}
