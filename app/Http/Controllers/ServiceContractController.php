<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\CostType;
use App\Models\Property;
use App\Models\ServiceContract;
use App\Models\Unit;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Vertraege\Vertraege;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Laufende Verträge mit Kündigungserinnerung und Kosten (Paket 32). */
class ServiceContractController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $user = $this->me();
        $ownerId = (int) $request->query('eigentuemer') ?: null;
        $propertyId = (int) $request->query('objekt') ?: null;
        $kind = array_key_exists((string) $request->query('art'), ServiceContract::KINDS) ? (string) $request->query('art') : null;
        $stand = (string) $request->query('stand', 'aktiv');
        if ($ownerId) {
            $this->assertOwnerAccess($ownerId);
        }
        $items = ServiceContract::query()->visibleTo($user)->with(['property', 'unit', 'owner'])
            ->when($ownerId, fn ($q) => $q->where('service_contracts.owner_id', $ownerId))
            ->when($propertyId, fn ($q) => $q->where('property_id', $propertyId))
            ->when($kind, fn ($q) => $q->where('kind', $kind))
            ->when($stand === 'aktiv', fn ($q) => $q->whereIn('status', ['aktiv', 'gekuendigt']))
            ->when(in_array($stand, ['beendet'], true), fn ($q) => $q->where('status', $stand))
            ->orderBy('kind')->orderBy('title')->get();
        $infos = $items->mapWithKeys(fn ($c) => [$c->id => Vertraege::info($c)]);
        $running = $items->filter(fn ($c) => $c->status !== 'beendet');
        $byKind = $running->groupBy('kind')->map(fn ($g) => round($g->sum(fn ($c) => $c->annualCost()), 2))->sortDesc();

        return view('svc.index', [
            'items' => $items, 'infos' => $infos, 'attention' => Vertraege::attention($user, $ownerId),
            'total' => round($running->sum(fn ($c) => $c->annualCost()), 2), 'byKind' => $byKind,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']), 'properties' => Property::query()->visibleTo($user)->orderBy('name')->get(['properties.id', 'properties.name']),
            'ownerId' => $ownerId, 'propertyId' => $propertyId, 'kind' => $kind, 'stand' => $stand,
        ]);
    }

    public function create(Request $request)
    {
        $c = new ServiceContract(['kind' => 'strom', 'auto_renew' => false, 'renew_months' => 12, 'notice_value' => 3, 'notice_unit' => 'monate', 'remind_days' => 30, 'interval' => 'monatlich', 'status' => 'aktiv']);
        if ($p = (int) $request->query('objekt')) {
            $c->property_id = $p;
        }

        return view('svc.form', $this->formData($c));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $c = ServiceContract::create($data + ['status' => 'aktiv', 'created_by' => $this->me()->id]);
        Audit::log('contract.created', $c, 'Vertrag angelegt: '.$c->title.', '.$c->place(), null, ['kosten_jahr' => $c->annualCost()]);

        return redirect()->route('svc.show', $c)->with('status', 'Der Vertrag ist angelegt.');
    }

    public function show(ServiceContract $contract)
    {
        $this->assertOwnerAccess((int) $contract->owner_id);
        $contract->load(['property', 'unit', 'owner', 'costType']);

        return view('svc.show', ['c' => $contract, 'info' => Vertraege::info($contract)]);
    }

    public function edit(ServiceContract $contract)
    {
        $this->assertOwnerAccess((int) $contract->owner_id);

        return view('svc.form', $this->formData($contract));
    }

    public function update(Request $request, ServiceContract $contract)
    {
        $this->assertOwnerAccess((int) $contract->owner_id);
        $data = $this->validated($request, $contract);
        $termChanged = ($data['term_end'] ?? null) !== $contract->term_end?->format('Y-m-d');
        $contract->update($data + ($termChanged ? ['reminded_for' => null, 'reminded_stage' => 0] : []));
        Audit::log('contract.updated', $contract, 'Vertrag geändert: '.$contract->title.', '.$contract->place());

        return redirect()->route('svc.show', $contract)->with('status', 'Gespeichert.');
    }

    public function destroy(ServiceContract $contract)
    {
        $this->assertOwnerAccess((int) $contract->owner_id);
        Audit::log('contract.deleted', $contract, 'Vertrag gelöscht: '.$contract->title.', '.$contract->place());
        foreach (\App\Models\Document::query()->where('attach_type', 'contract')->where('attach_id', $contract->id)->get() as $doc) {
            \App\Support\Ablage\Ablage::delete($doc);
        }
        $contract->delete();

        return redirect()->route('svc.index')->with('status', 'Der Vertrag wurde gelöscht.');
    }

    public function cancel(Request $request, ServiceContract $contract)
    {
        $this->assertOwnerAccess((int) $contract->owner_id);
        $d = $request->validate(['cancelled_on' => ['required', 'date_format:Y-m-d'], 'cancel_effective' => ['nullable', 'date_format:Y-m-d']]);

        return $this->run(fn () => Vertraege::cancel($contract, $d['cancelled_on'], $d['cancel_effective'] ?? null), 'Die Kündigung ist vermerkt. Es kommen keine Erinnerungen mehr.', $contract);
    }

    public function renew(Request $request, ServiceContract $contract)
    {
        $this->assertOwnerAccess((int) $contract->owner_id);
        $d = $request->validate(['term_end' => ['required', 'date_format:Y-m-d', 'after:today']], ['term_end.after' => 'Das neue Laufzeitende muss in der Zukunft liegen.']);

        return $this->run(fn () => Vertraege::renew($contract, $d['term_end']), 'Das Laufzeitende ist geändert, die Erinnerung beginnt neu.', $contract);
    }

    public function end(ServiceContract $contract)
    {
        $this->assertOwnerAccess((int) $contract->owner_id);

        return $this->run(fn () => Vertraege::end($contract), 'Der Vertrag ist als beendet eingetragen.', $contract);
    }

    public function reopen(ServiceContract $contract)
    {
        $this->assertOwnerAccess((int) $contract->owner_id);

        return $this->run(fn () => Vertraege::reopen($contract), 'Der Vertrag läuft wieder.', $contract);
    }

    // ------------------------------------------------------------------ Hilfen

    private function run(callable $f, string $msg, ServiceContract $c)
    {
        try {
            $f();
        } catch (RuntimeException $e) {
            return back()->withErrors(['contract' => $e->getMessage()]);
        }

        return redirect()->route('svc.show', $c)->with('status', $msg);
    }

    private function formData(ServiceContract $c): array
    {
        return [
            'c' => $c,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'properties' => Property::query()->visibleTo($this->me())->with('units')->orderBy('name')->get(),
            'costTypes' => CostType::query()->where('active', true)->orderBy('group_label')->orderBy('sort')->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ServiceContract $existing = null): array
    {
        $d = $request->validate([
            'kind' => ['required', Rule::in(array_keys(ServiceContract::KINDS))], 'title' => ['required', 'string', 'max:150'],
            'provider' => ['nullable', 'string', 'max:150'], 'contract_no' => ['nullable', 'string', 'max:80'], 'customer_no' => ['nullable', 'string', 'max:80'], 'meter_no' => ['nullable', 'string', 'max:80'],
            'start_date' => ['nullable', 'date_format:Y-m-d'], 'term_end' => ['nullable', 'date_format:Y-m-d'],
            'renew_months' => ['nullable', 'integer', 'between:1,120'], 'notice_value' => ['required', 'integer', 'between:0,60'], 'notice_unit' => ['required', Rule::in(array_keys(ServiceContract::UNITS))],
            'remind_days' => ['required', 'integer', 'between:1,365'], 'amount' => ['nullable', 'string', 'max:20'], 'interval' => ['required', Rule::in(array_keys(ServiceContract::INTERVALS))],
            'cost_type_id' => ['nullable', 'integer'], 'property_id' => ['nullable', 'integer'], 'unit_id' => ['nullable', 'integer'], 'owner_id' => ['nullable', 'integer'],
            'contact' => ['nullable', 'string', 'max:190'], 'note' => ['nullable', 'string', 'max:3000'],
        ], ['title.required' => 'Bitte eine Bezeichnung eintragen, zum Beispiel „Strom Allgemeinstrom Haus A“.', 'notice_value.required' => 'Bitte die Kündigungsfrist angeben (0, wenn es keine gibt).']);

        $amount = null;
        if (trim((string) ($d['amount'] ?? '')) !== '') {
            $amount = Money::parse($d['amount']);
            if ($amount === null || (float) $amount < 0) {
                back()->withErrors(['amount' => 'Bitte einen gültigen Betrag eingeben (zum Beispiel 89,00).'])->withInput()->throwResponse();
            }
        }
        $propertyId = null;
        $ownerId = (int) ($d['owner_id'] ?? 0);
        if (! empty($d['property_id'])) {
            $p = Property::query()->visibleTo($this->me())->find((int) $d['property_id']);
            abort_if($p === null, 404);
            $propertyId = (int) $p->id;
            $ownerId = (int) $p->owner_id;
        }
        $unitId = null;
        if ($propertyId && ! empty($d['unit_id'])) {
            $u = Unit::query()->where('property_id', $propertyId)->find((int) $d['unit_id']);
            $unitId = $u?->id;
        }
        if ($ownerId === 0) {
            $only = $this->visibleOwnerIds();
            $ownerId = count($only) === 1 ? $only[0] : ($existing?->owner_id ?? 0);
        }
        if ($ownerId === 0) {
            back()->withErrors(['owner_id' => 'Bitte ein Objekt oder einen Eigentümer wählen.'])->withInput()->throwResponse();
        }
        $this->assertOwnerAccess($ownerId);

        foreach (['provider', 'contract_no', 'customer_no', 'meter_no', 'contact', 'note', 'start_date', 'term_end'] as $k) {
            $d[$k] = isset($d[$k]) && trim((string) $d[$k]) !== '' ? trim((string) $d[$k]) : null;
        }

        return [
            'owner_id' => $ownerId, 'property_id' => $propertyId, 'unit_id' => $unitId, 'kind' => $d['kind'], 'title' => trim($d['title']),
            'provider' => $d['provider'], 'contract_no' => $d['contract_no'], 'customer_no' => $d['customer_no'], 'meter_no' => $d['meter_no'],
            'start_date' => $d['start_date'], 'term_end' => $d['term_end'], 'auto_renew' => $request->boolean('auto_renew'), 'renew_months' => (int) ($d['renew_months'] ?? 12) ?: 12,
            'notice_value' => (int) $d['notice_value'], 'notice_unit' => $d['notice_unit'], 'remind_days' => (int) $d['remind_days'],
            'amount' => $amount, 'interval' => $d['interval'], 'cost_type_id' => ! empty($d['cost_type_id']) && CostType::query()->whereKey((int) $d['cost_type_id'])->exists() ? (int) $d['cost_type_id'] : null,
            'contact' => $d['contact'], 'note' => $d['note'],
        ];
    }
}
