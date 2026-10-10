<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\HandoverPhoto;
use App\Models\HandoverProtocol;
use App\Models\Lease;
use App\Support\Contracts\ContractService;
use App\Support\Documents\DocumentStore;
use App\Support\Handover\HandoverService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Übergabeprotokolle bei Ein- und Auszug (Paket 13). */
class HandoverController extends Controller
{
    use ScopesOwners;

    public function store(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $kind = $request->validate(['kind' => ['required', Rule::in(['einzug', 'auszug'])]])['kind'];
        $draft = HandoverProtocol::query()->where('lease_id', $lease->id)->where('kind', $kind)->where('status', 'entwurf')->first();
        if ($draft) {
            return redirect()->route('handover.edit', $draft)->with('status', 'Es gibt schon einen Entwurf, er wurde geöffnet.');
        }
        $p = HandoverService::create($lease, $kind, $this->me());

        return redirect()->route('handover.edit', $p)->with('status', $p->compare_id ? 'Protokoll angelegt. Räume und Schlüssel sind aus dem Einzugsprotokoll übernommen.' : 'Protokoll angelegt.');
    }

    public function edit(HandoverProtocol $protocol)
    {
        $this->access($protocol);
        if (! $protocol->isDraft()) {
            return redirect()->route('handover.show', $protocol);
        }

        return view('handover.edit', self::viewData($protocol));
    }

    public function update(Request $request, HandoverProtocol $protocol)
    {
        $this->access($protocol);
        $data = $request->validate([
            'handover_on' => ['required', 'date_format:Y-m-d'],
            'attendees' => ['nullable', 'string', 'max:500'],
            'note' => ['nullable', 'string', 'max:5000'],
            'new_address' => ['nullable', 'string', 'max:300'],
            'rooms' => ['nullable', 'array', 'max:40'],
            'rooms.*.name' => ['nullable', 'string', 'max:100'],
            'rooms.*.note' => ['nullable', 'string', 'max:2000'],
            'rooms.*.items' => ['nullable', 'array', 'max:40'],
            'rooms.*.items.*.name' => ['nullable', 'string', 'max:100'],
            'rooms.*.items.*.state' => ['nullable', 'string', 'max:20'],
            'rooms.*.items.*.note' => ['nullable', 'string', 'max:500'],
            'keys' => ['nullable', 'array', 'max:20'],
            'keys.*.label' => ['nullable', 'string', 'max:100'],
            'keys.*.count' => ['nullable', 'string', 'max:5'],
            'readings' => ['nullable', 'array'],
            'readings.*' => ['nullable', 'string', 'max:20'],
            'action' => ['nullable', Rule::in(['save', 'add_room', 'add_key', 'sign'])],
            'new_room' => ['nullable', 'string', 'max:100'],
        ], ['handover_on.*' => 'Bitte das Datum der Übergabe angeben.']);

        $action = $data['action'] ?? 'save';
        if ($action === 'add_room') {
            $name = trim((string) ($data['new_room'] ?? '')) ?: 'Neuer Raum';
            $data['rooms'][] = ['name' => $name, 'items' => array_map(fn ($i) => ['name' => $i, 'state' => '', 'note' => ''], HandoverService::itemsFor($name)), 'note' => ''];
        }
        if ($action === 'add_key') {
            $data['keys'][] = ['label' => 'Weiterer Schlüssel', 'count' => ''];
        }
        try {
            HandoverService::save($protocol, $data);
        } catch (RuntimeException $e) {
            return back()->withErrors(['handover' => $e->getMessage()]);
        }

        return match ($action) {
            'sign' => redirect()->route('handover.sign', $protocol),
            'add_room' => redirect()->to(route('handover.edit', $protocol).'#raum'.(count($data['rooms']) - 1))->with('status', 'Raum hinzugefügt.'),
            'add_key' => redirect()->to(route('handover.edit', $protocol).'#schluessel'),
            default => redirect()->back()->with('status', 'Gespeichert.'),
        };
    }

    public function photoStore(Request $request, HandoverProtocol $protocol)
    {
        $this->access($protocol);
        abort_unless($protocol->isDraft(), 403);
        $request->validate([
            'room' => ['required', 'integer', 'min:0', 'max:60'],
            'fotos' => ['required', 'array', 'max:15'],
            'fotos.*' => ['file', 'max:20480'],
        ], ['fotos.required' => 'Bitte Fotos auswählen oder aufnehmen.', 'fotos.*.max' => 'Ein Foto darf höchstens 20 MB groß sein.']);
        $n = 0;
        $errors = [];
        foreach ($request->file('fotos') as $f) {
            try {
                HandoverService::storePhoto($protocol, (int) $request->input('room'), $f);
                $n++;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        $r = redirect()->to(route('handover.edit', $protocol).'#raum'.(int) $request->input('room'))->with('status', $n === 1 ? 'Foto gespeichert.' : $n.' Fotos gespeichert.');

        return $errors ? $r->withErrors(['fotos' => implode(' ', $errors)]) : $r;
    }

    public function photoShow(HandoverProtocol $protocol, HandoverPhoto $photo, string $size)
    {
        $this->access($protocol);
        abort_unless((int) $photo->handover_protocol_id === (int) $protocol->id, 404);

        return response(DocumentStore::get($size === 's' ? $photo->thumb : $photo->path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=3600']);
    }

    public function photoDestroy(HandoverProtocol $protocol, HandoverPhoto $photo)
    {
        $this->access($protocol);
        abort_unless($protocol->isDraft() && (int) $photo->handover_protocol_id === (int) $protocol->id, 404);
        DocumentStore::forget($photo->path);
        DocumentStore::forget($photo->thumb);
        $room = $photo->room;
        $photo->delete();

        return redirect()->to(route('handover.edit', $protocol).'#raum'.$room)->with('status', 'Foto gelöscht.');
    }

    public function signForm(HandoverProtocol $protocol)
    {
        $this->access($protocol);
        abort_unless($protocol->isDraft(), 404);

        return view('handover.sign', self::viewData($protocol) + ['landlordName' => $this->me()->name]);
    }

    public function signStore(Request $request, HandoverProtocol $protocol)
    {
        $this->access($protocol);
        $data = $request->validate([
            'method' => ['required', Rule::in(['tablet', 'papier'])],
            'tenant_name' => ['nullable', 'string', 'max:190'],
            'landlord_name' => ['nullable', 'string', 'max:190'],
            'tenant_signature' => ['nullable', 'string', 'max:900000'],
            'landlord_signature' => ['nullable', 'string', 'max:900000'],
        ]);
        try {
            HandoverService::finalize($protocol, $data['method'], $data['tenant_signature'] ?? null, $data['landlord_signature'] ?? null, (string) ($data['tenant_name'] ?? ''), (string) ($data['landlord_name'] ?? ''), $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['handover' => $e->getMessage()]);
        }

        if ($data['method'] === 'tablet') {
            \App\Support\Ablage\Ablage::fileFinished('uebergabe', (int) $protocol->id, $this->me());
        }

        return redirect()->route('handover.show', $protocol)->with('status', 'Protokoll abgeschlossen. Die Zählerstände sind in die Nebenkosten übernommen.');
    }

    public function show(HandoverProtocol $protocol)
    {
        $this->access($protocol);

        return view('handover.show', self::viewData($protocol));
    }

    public function print(HandoverProtocol $protocol)
    {
        $this->access($protocol);

        return view('handover.print', self::viewData($protocol, true));
    }

    public function destroy(HandoverProtocol $protocol)
    {
        $this->access($protocol);
        abort_unless($protocol->isDraft(), 403);
        $lease = $protocol->lease_id;
        HandoverService::delete($protocol);

        return redirect()->to(route('leases.show', $lease).'#uebergabe')->with('status', 'Entwurf gelöscht.');
    }

    private function access(HandoverProtocol $p): void
    {
        $this->assertOwnerAccess((int) $p->owner_id);
    }

    public static function viewData(HandoverProtocol $p, bool $embed = false): array
    {
        $p->load(['lease.unit.property', 'lease.tenant', 'lease.owner', 'photos', 'readings.meter', 'compare']);
        $photos = $p->photos->groupBy('room');
        $thumbs = [];
        if ($embed) {
            foreach ($p->photos as $ph) {
                try {
                    $thumbs[$ph->id] = 'data:image/jpeg;base64,'.base64_encode(DocumentStore::get($ph->thumb));
                } catch (\Throwable) {
                }
            }
        }

        return [
            'p' => $p,
            'photos' => $photos,
            'thumbs' => $thumbs,
            'prev' => HandoverService::previousStates($p->compare),
            'sig' => [
                'tenant' => ContractService::signatureDataUri($p->tenant_signature),
                'landlord' => ContractService::signatureDataUri($p->landlord_signature),
            ],
        ];
    }
}
