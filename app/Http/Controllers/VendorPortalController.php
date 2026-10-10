<?php

namespace App\Http\Controllers;

use App\Models\WorkOrderPhoto;
use App\Support\Documents\DocumentStore;
use App\Support\Technik\Auftraege;
use App\Support\Technik\Fotos;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Auftragsseite für Handwerker ohne Anmeldung (Paket 24). Zugang nur über den geheimen Link aus der E-Mail.
 * Der Handwerker sieht nur diesen einen Auftrag und kann Termin, Erledigt und Fotos melden.
 */
class VendorPortalController extends Controller
{
    private function order(string $token)
    {
        $o = Auftraege::byToken($token);
        abort_if($o === null, 404);

        return $o;
    }

    public function show(string $token)
    {
        $o = $this->order($token);
        $o->load(['property', 'unit', 'lease.tenant.contacts', 'vendor', 'photos', 'notes']);

        return response()->view('workorders.vendor', ['o' => $o, 'token' => $token, 'contact' => Auftraege::tenantContact($o)])
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer');
    }

    public function appointment(Request $request, string $token)
    {
        $o = $this->order($token);
        $data = $request->validate(['appointment_at' => ['required', 'date', 'after:yesterday'], 'note' => ['nullable', 'string', 'max:1000']], [
            'appointment_at.required' => 'Bitte Datum und Uhrzeit angeben.', 'appointment_at.after' => 'Der Termin darf nicht in der Vergangenheit liegen.',
        ]);
        abort_unless($o->isOpen(), 404);
        $who = $o->vendor?->name ?? 'Handwerker';
        Auftraege::setAppointment($o, $data['appointment_at'], $data['note'] ?? null, null, $who);
        Auftraege::notifyTeam($o, $who.' hat einen Termin eingetragen: '.date('d.m.Y H:i', strtotime($data['appointment_at'])).' Uhr');

        return redirect()->route('vendor.order', $token)->with('status', 'Danke, der Termin ist eingetragen.');
    }

    public function done(Request $request, string $token)
    {
        $o = $this->order($token);
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
            'fotos' => ['nullable', 'array', 'max:10'],
            'fotos.*' => ['file', 'max:20480'],
        ]);
        $who = $o->vendor?->name ?? 'Handwerker';
        $errors = [];
        foreach ((array) $request->file('fotos', []) as $f) {
            try {
                Fotos::store($o, $f, 'handwerker');
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        try {
            Auftraege::markDone($o, date('Y-m-d'), $data['note'] ?? null, null, $who);
        } catch (RuntimeException $e) {
            return back()->withErrors(['done' => $e->getMessage()]);
        }
        Auftraege::notifyTeam($o, $who.' meldet: Auftrag erledigt.');
        $r = redirect()->route('vendor.order', $token)->with('status', 'Danke! Der Auftrag ist als erledigt gemeldet. Bitte senden Sie uns die Rechnung mit der Auftragsnummer '.$o->number().'.');

        return $errors ? $r->withErrors(['fotos' => implode(' ', $errors)]) : $r;
    }

    public function photo(string $token, WorkOrderPhoto $photo, string $size)
    {
        $o = $this->order($token);
        abort_unless((int) $photo->work_order_id === (int) $o->id, 404);

        return response(DocumentStore::get($size === 's' ? $photo->thumb : $photo->path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=600']);
    }
}
