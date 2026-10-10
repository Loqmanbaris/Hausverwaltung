<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\HandoverProtocol;
use App\Models\MaintenanceTask;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\Unit;
use App\Models\Vendor;
use App\Models\WorkOrder;
use App\Models\WorkOrderPhoto;
use App\Support\Audit;
use App\Support\Documents\DocumentStore;
use App\Support\Money;
use App\Support\Technik\Auftraege;
use App\Support\Technik\Fotos;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Schadensfälle und Handwerkeraufträge (Paket 24). */
class WorkOrderController extends Controller
{
    use ScopesOwners;

    private function access(WorkOrder $o): void
    {
        if ($o->owner_id === null) {
            abort_unless($this->me()->isAdmin(), 404);

            return;
        }
        $this->assertOwnerAccess((int) $o->owner_id);
    }

    public function index(Request $request)
    {
        $state = (string) $request->query('stand', 'offen');
        $pid = (int) $request->query('objekt') ?: null;
        $q = trim((string) $request->query('q'));
        $orders = WorkOrder::visibleTo($this->me())
            ->when($state === 'offen', fn ($w) => $w->open())
            ->when(isset(WorkOrder::STATUS[$state]), fn ($w) => $w->where('status', $state))
            ->when($pid, fn ($w) => $w->where('property_id', $pid))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', '%'.$q.'%')->orWhere('description', 'like', '%'.$q.'%')))
            ->with(['property', 'unit', 'vendor'])
            ->orderByRaw("case priority when 'notfall' then 0 when 'dringend' then 1 else 2 end")
            ->orderByDesc('id')->simplePaginate(50)->withQueryString();
        $counts = WorkOrder::visibleTo($this->me())->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('workorders.index', [
            'orders' => $orders, 'state' => $state, 'pid' => $pid, 'q' => $q, 'counts' => $counts,
            'properties' => Property::visibleTo($this->me())->orderBy('name')->get(['properties.id', 'properties.name']),
        ]);
    }

    public function create(Request $request)
    {
        $task = null;
        if ($request->filled('wartung')) {
            $task = MaintenanceTask::visibleTo($this->me())->findOrFail((int) $request->query('wartung'));
        }

        return view('workorders.form', [
            'o' => new WorkOrder([
                'property_id' => $task?->property_id ?? ((int) $request->query('objekt') ?: null),
                'unit_id' => $task?->unit_id ?? ((int) $request->query('einheit') ?: null),
                'title' => $task?->title ?? '',
                'description' => $task?->note,
                'vendor_id' => $task?->vendor_id,
                'priority' => 'normal',
            ]),
            'task' => $task,
            'properties' => Property::visibleTo($this->me())->orderBy('name')->get(),
            'units' => Unit::query()->whereIn('property_id', Property::visibleTo($this->me())->select('properties.id'))->orderBy('label')->get(['id', 'property_id', 'label']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'property_id' => ['required', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(WorkOrder::PRIORITY))],
            'reporter_name' => ['nullable', 'string', 'max:120'],
            'reporter_phone' => ['nullable', 'string', 'max:60'],
            'maintenance_task_id' => ['nullable', 'integer'],
            'fotos' => ['nullable', 'array', 'max:15'],
            'fotos.*' => ['file', 'max:20480'],
        ], ['title.required' => 'Bitte kurz beschreiben, was kaputt ist.', 'property_id.required' => 'Bitte das Objekt wählen.']);
        $p = Property::query()->findOrFail((int) $data['property_id']);
        $this->assertOwnerAccess((int) $p->owner_id);
        if (! empty($data['unit_id']) && ! Unit::query()->where('property_id', $p->id)->whereKey((int) $data['unit_id'])->exists()) {
            return back()->withInput()->withErrors(['unit_id' => 'Die Einheit gehört nicht zu diesem Objekt.']);
        }
        $task = ! empty($data['maintenance_task_id']) ? MaintenanceTask::query()->where('property_id', $p->id)->find((int) $data['maintenance_task_id']) : null;
        $o = Auftraege::create($data + ['maintenance_task_id' => $task?->id, 'vendor_id' => $task?->vendor_id], $this->me(), $task ? 'wartung' : 'manuell');
        $errors = $this->savePhotos($request, $o, 'team');

        $r = redirect()->route('workorders.show', $o)->with('status', 'Schadensfall '.$o->number().' angelegt. Jetzt einen Handwerker beauftragen.');

        return $errors ? $r->withErrors(['fotos' => implode(' ', $errors)]) : $r;
    }

    private function savePhotos(Request $request, WorkOrder $o, string $by): array
    {
        $errors = [];
        foreach ((array) $request->file('fotos', []) as $f) {
            try {
                Fotos::store($o, $f, $by);
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return $errors;
    }

    public function show(WorkOrder $order)
    {
        $this->access($order);
        $order->load(['property', 'unit', 'lease.tenant.contacts', 'vendor', 'receipt', 'photos', 'notes', 'task']);
        $receipts = $order->owner_id ? Receipt::query()->where('owner_id', $order->owner_id)
            ->when($order->vendor_id, fn ($q) => $q->orderByRaw('vendor_id = ? desc', [$order->vendor_id]))
            ->orderByRaw('property_id = ? desc', [(int) $order->property_id])
            ->where('receipt_date', '>=', now()->subMonths(6)->format('Y-m-d'))->orderByDesc('receipt_date')->limit(40)->get() : collect();

        return view('workorders.show', [
            'o' => $order,
            'vendors' => Vendor::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'email', 'phone']),
            'receipts' => $receipts,
            'contact' => Auftraege::tenantContact($order),
            'link' => session('vendor_link'),
            'properties' => $order->property_id ? collect() : Property::visibleTo($this->me())->orderBy('name')->get(['properties.id', 'properties.name']),
        ]);
    }

    public function update(Request $request, WorkOrder $order)
    {
        $this->access($order);
        $data = $request->validate([
            'property_id' => ['nullable', 'integer'],
            'unit_id' => ['nullable', 'integer'],
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(WorkOrder::PRIORITY))],
        ]);
        if (! empty($data['property_id'])) {
            $p = Property::query()->findOrFail((int) $data['property_id']);
            $this->assertOwnerAccess((int) $p->owner_id);
            $order->owner_id = $p->owner_id;
            $order->property_id = $p->id;
            if (! empty($data['unit_id']) && Unit::query()->where('property_id', $p->id)->whereKey((int) $data['unit_id'])->exists()) {
                $order->unit_id = (int) $data['unit_id'];
                $order->lease_id = Auftraege::leaseFor((int) $data['unit_id'])?->id;
            }
        }
        $order->fill(['title' => $data['title'], 'description' => $data['description'] ?? null, 'priority' => $data['priority']])->save();
        Audit::log('workorder.updated', $order, $order->number().' geändert');

        return redirect()->route('workorders.show', $order)->with('status', 'Gespeichert.');
    }

    public function assign(Request $request, WorkOrder $order)
    {
        $this->access($order);
        $data = $request->validate([
            'vendor_id' => ['required', 'integer'],
            'message' => ['nullable', 'string', 'max:2000'],
            'send_mail' => ['nullable', 'in:1'],
            'cost_estimate' => ['nullable', 'string', 'max:20'],
        ], ['vendor_id.required' => 'Bitte einen Handwerker wählen.']);
        if ($order->property_id === null) {
            return back()->withErrors(['order' => 'Bitte zuerst das Objekt zuordnen.']);
        }
        $v = Vendor::query()->findOrFail((int) $data['vendor_id']);
        $est = trim((string) ($data['cost_estimate'] ?? '')) !== '' ? Money::parse($data['cost_estimate']) : null;
        try {
            $res = Auftraege::assign($order, $v, $data['message'] ?? null, isset($data['send_mail']), $est, $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }
        $r = redirect()->route('workorders.show', $order)->with('vendor_link', Auftraege::link($res['token']))
            ->with('status', 'An '.$v->name.' vergeben.'.(isset($data['send_mail']) && $res['mail'] === null ? ' Die E-Mail mit Link und PDF ist raus.' : ''));

        return $res['mail'] ? $r->withErrors(['order' => $res['mail']]) : $r;
    }

    public function appointment(Request $request, WorkOrder $order)
    {
        $this->access($order);
        $data = $request->validate(['appointment_at' => ['required', 'date'], 'note' => ['nullable', 'string', 'max:1000']]);
        Auftraege::setAppointment($order, $data['appointment_at'], $data['note'] ?? null, $this->me());

        return redirect()->route('workorders.show', $order)->with('status', 'Termin eingetragen.');
    }

    public function done(Request $request, WorkOrder $order)
    {
        $this->access($order);
        $data = $request->validate(['done_on' => ['required', 'date', 'before_or_equal:today'], 'note' => ['nullable', 'string', 'max:2000']], ['done_on.before_or_equal' => 'Das Datum darf nicht in der Zukunft liegen.']);
        try {
            Auftraege::markDone($order, $data['done_on'], $data['note'] ?? null, $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['order' => $e->getMessage()]);
        }

        return redirect()->route('workorders.show', $order)->with('status', 'Als erledigt eingetragen. Wenn die Rechnung da ist, hier den Beleg verknüpfen.');
    }

    public function bill(Request $request, WorkOrder $order)
    {
        $this->access($order);
        $data = $request->validate(['receipt_id' => ['required', 'integer']], ['receipt_id.required' => 'Bitte den Beleg wählen oder die Beleg-Nr. eingeben.']);
        $r = Receipt::query()->find((int) $data['receipt_id']);
        if ($r === null) {
            return back()->withErrors(['receipt_id' => 'Diesen Beleg gibt es nicht.']);
        }
        try {
            Auftraege::bill($order, $r, $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['receipt_id' => $e->getMessage()]);
        }

        return redirect()->route('workorders.show', $order)->with('status', 'Rechnung verknüpft, der Auftrag ist abgerechnet.');
    }

    public function cancel(Request $request, WorkOrder $order)
    {
        $this->access($order);
        Auftraege::cancel($order, trim((string) $request->input('reason')) ?: null, $this->me());

        return redirect()->route('workorders.show', $order)->with('status', 'Storniert. Der Link für den Handwerker gilt nicht mehr.');
    }

    public function reopen(WorkOrder $order)
    {
        $this->access($order);
        Auftraege::reopen($order, $this->me());

        return redirect()->route('workorders.show', $order)->with('status', 'Wieder geöffnet.');
    }

    public function newLink(WorkOrder $order)
    {
        $this->access($order);
        abort_unless($order->vendor_id && $order->status !== 'storniert', 404);
        $token = Auftraege::newToken($order);
        Auftraege::note($order, 'Neuer Link für den Handwerker erzeugt (alter Link ungültig).', $this->me());

        return redirect()->route('workorders.show', $order)->with('vendor_link', Auftraege::link($token))->with('status', 'Neuer Link erzeugt.');
    }

    public function note(Request $request, WorkOrder $order)
    {
        $this->access($order);
        $data = $request->validate(['text' => ['required', 'string', 'max:2000']]);
        Auftraege::note($order, $data['text'], $this->me());

        return redirect()->to(route('workorders.show', $order).'#verlauf')->with('status', 'Notiz gespeichert.');
    }

    public function photoStore(Request $request, WorkOrder $order)
    {
        $this->access($order);
        $request->validate(['fotos' => ['required', 'array', 'max:15'], 'fotos.*' => ['file', 'max:20480']], ['fotos.required' => 'Bitte Fotos auswählen.']);
        $errors = $this->savePhotos($request, $order, 'team');
        $r = redirect()->to(route('workorders.show', $order).'#fotos')->with('status', 'Fotos gespeichert.');

        return $errors ? $r->withErrors(['fotos' => implode(' ', $errors)]) : $r;
    }

    public function photoShow(WorkOrder $order, WorkOrderPhoto $photo, string $size)
    {
        $this->access($order);
        abort_unless((int) $photo->work_order_id === (int) $order->id, 404);

        return response(DocumentStore::get($size === 's' ? $photo->thumb : $photo->path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function photoDestroy(WorkOrder $order, WorkOrderPhoto $photo)
    {
        $this->access($order);
        abort_unless((int) $photo->work_order_id === (int) $order->id, 404);
        Fotos::delete($photo);

        return redirect()->to(route('workorders.show', $order).'#fotos')->with('status', 'Foto gelöscht.');
    }

    public function pdf(WorkOrder $order)
    {
        $this->access($order);

        return response(Auftraege::pdf($order), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="auftrag-'.$order->number().'.pdf"', 'Cache-Control' => 'private, no-store']);
    }

    /** Mängel aus einem Übergabeprotokoll übernehmen. */
    public function fromHandover(Request $request, HandoverProtocol $protocol)
    {
        $this->assertOwnerAccess((int) $protocol->owner_id);
        abort_if($protocol->isDraft(), 404);
        $data = $request->validate(['items' => ['required', 'array', 'min:1', 'max:100'], 'items.*' => ['string', 'max:20']], ['items.required' => 'Bitte mindestens einen Mangel auswählen.']);
        $items = [];
        foreach ($protocol->rooms ?? [] as $ri => $room) {
            foreach ($room['items'] ?? [] as $ii => $it) {
                if (in_array($ri.'-'.$ii, $data['items'], true) && in_array($it['state'] ?? '', ['mangel', 'gebrauch'], true)) {
                    $items[] = ['room' => $room['name'], 'item' => $it['name'], 'note' => $it['note'] ?? null];
                }
            }
        }
        $n = Auftraege::fromHandover($protocol, $items, $this->me());

        return redirect()->route('workorders.index')->with('status', $n === 0 ? 'Keine neuen Schadensfälle (schon übernommen).' : $n.' Schadensfall/-fälle aus dem Protokoll angelegt.');
    }
}
