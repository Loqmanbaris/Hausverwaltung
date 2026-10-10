<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\MaintenanceTask;
use App\Models\Property;
use App\Models\Unit;
use App\Models\Vendor;
use App\Support\Audit;
use App\Support\Technik\Wartung;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Wartungsplan (Paket 24). */
class MaintenanceController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $pid = (int) $request->query('objekt') ?: null;
        $tasks = MaintenanceTask::visibleTo($this->me())->where('active', true)
            ->when($pid, fn ($q) => $q->where('property_id', $pid))
            ->with(['property', 'unit', 'vendor', 'logs'])->orderBy('next_due')->get();
        $props = Property::visibleTo($this->me())->orderBy('name')->get();

        return view('maintenance.index', [
            'tasks' => $tasks, 'pid' => $pid, 'properties' => $props,
            'inactive' => MaintenanceTask::visibleTo($this->me())->where('active', false)->when($pid, fn ($q) => $q->where('property_id', $pid))->with('property')->orderByDesc('last_done')->limit(30)->get(),
        ]);
    }

    private function formData(MaintenanceTask $t): array
    {
        return [
            't' => $t,
            'properties' => Property::visibleTo($this->me())->orderBy('name')->get(),
            'units' => Unit::query()->whereIn('property_id', Property::visibleTo($this->me())->select('properties.id'))->orderBy('label')->get(['id', 'property_id', 'label']),
            'vendors' => Vendor::query()->where('active', true)->orderBy('name')->get(['id', 'name']),
        ];
    }

    public function create(Request $request)
    {
        $type = (string) $request->query('art', 'rauchmelder');
        $type = isset(Wartung::CATALOG[$type]) ? $type : 'eigen';

        return view('maintenance.form', $this->formData(new MaintenanceTask([
            'type' => $type, 'title' => $type === 'eigen' ? '' : Wartung::CATALOG[$type][0], 'interval_months' => Wartung::CATALOG[$type][1],
            'property_id' => (int) $request->query('objekt') ?: null, 'next_due' => date('Y-m-d', strtotime('+1 month')), 'remind_days' => 30,
        ])));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'type' => ['required', Rule::in(array_keys(Wartung::CATALOG))],
            'title' => ['required', 'string', 'max:150'],
            'interval_months' => ['nullable', 'integer', 'between:1,240'],
            'next_due' => ['required', 'date'],
            'last_done' => ['nullable', 'date'],
            'vendor_id' => ['nullable', 'integer'],
            'remind_days' => ['nullable', 'integer', 'between:1,365'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['title.required' => 'Bitte eine Bezeichnung eingeben.', 'next_due.required' => 'Bitte den nächsten Termin angeben.']);
        $p = Property::query()->findOrFail((int) $data['property_id']);
        $this->assertOwnerAccess((int) $p->owner_id);
        if (! empty($data['unit_id']) && ! Unit::query()->where('property_id', $p->id)->whereKey((int) $data['unit_id'])->exists()) {
            $data['unit_id'] = null;
        }

        return [
            'owner_id' => $p->owner_id, 'property_id' => $p->id, 'unit_id' => $data['unit_id'] ?: null, 'type' => $data['type'], 'title' => $data['title'],
            'interval_months' => $data['interval_months'] ?? null, 'next_due' => $data['next_due'], 'last_done' => $data['last_done'] ?? null,
            'vendor_id' => ! empty($data['vendor_id']) && Vendor::query()->whereKey((int) $data['vendor_id'])->exists() ? (int) $data['vendor_id'] : null,
            'remind_days' => (int) ($data['remind_days'] ?? 30) ?: 30, 'note' => $data['note'] ?? null,
        ];
    }

    public function store(Request $request)
    {
        $t = MaintenanceTask::create($this->validated($request) + ['created_by' => $this->me()->id]);
        Audit::log('maintenance.changed', $t, 'Wartung angelegt: '.$t->title.' ('.$t->place().')');

        return redirect()->route('maintenance.index')->with('status', 'Wartung angelegt.');
    }

    public function edit(MaintenanceTask $task)
    {
        $this->assertOwnerAccess((int) $task->owner_id);

        return view('maintenance.form', $this->formData($task->load('logs.user')));
    }

    public function update(Request $request, MaintenanceTask $task)
    {
        $this->assertOwnerAccess((int) $task->owner_id);
        $data = $this->validated($request);
        if ($task->next_due->format('Y-m-d') !== date('Y-m-d', strtotime($data['next_due'])) || (int) $task->remind_days !== $data['remind_days']) {
            $data['reminded_at'] = null;
        }
        $data['active'] = true;
        $task->update($data);
        Audit::log('maintenance.changed', $task, 'Wartung geändert: '.$task->title);

        return redirect()->route('maintenance.index')->with('status', 'Gespeichert.');
    }

    public function destroy(MaintenanceTask $task)
    {
        $this->assertOwnerAccess((int) $task->owner_id);
        $task->update(['active' => false]);
        Audit::log('maintenance.changed', $task, 'Wartung beendet: '.$task->title);

        return redirect()->route('maintenance.index')->with('status', 'Wartung beendet. Sie erscheint nicht mehr im Plan.');
    }

    public function done(Request $request, MaintenanceTask $task)
    {
        $this->assertOwnerAccess((int) $task->owner_id);
        $data = $request->validate(['done_on' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:500']], ['done_on.before_or_equal' => 'Das Datum darf nicht in der Zukunft liegen.']);
        Wartung::complete($task, $data['done_on'], $data['note'] ?? null, $this->me());

        return redirect()->route('maintenance.index')->with('status', $task->title.' erledigt.'.($task->interval_months ? ' Nächster Termin: '.$task->next_due->format('d.m.Y').'.' : ''));
    }

    public function defaults(Request $request)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => ['string', Rule::in(array_keys(Wartung::CATALOG))],
            'first_due' => ['required', 'date'],
        ], ['types.required' => 'Bitte mindestens eine Prüfung auswählen.']);
        $p = Property::query()->findOrFail((int) $data['property_id']);
        $this->assertOwnerAccess((int) $p->owner_id);
        $n = Wartung::createDefaults($p, $data['types'], $data['first_due'], $this->me());

        return redirect()->route('maintenance.index', ['objekt' => $p->id])->with('status', $n === 0 ? 'Alles schon vorhanden.' : $n.' Wartung(en) für '.$p->name.' angelegt. Bitte die Termine prüfen und bei Bedarf anpassen.');
    }
}
