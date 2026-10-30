<?php

namespace App\Http\Controllers;

use App\Models\Lease;
use App\Models\TenantPortalUser;
use App\Support\Documents\DocumentStore;
use App\Support\Mail\MailSettings;
use App\Support\Portal\Portal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/** Mieterportal: Anmeldung per Link, Konto, Dokumente, Zählerstände, Schadensmeldung (Paket 36). */
class PortalController extends Controller
{
    // ------------------------------------------------------------------ Anmeldung

    public function login()
    {
        return $this->page('portal.login', ['sent' => session('portal_sent'), 'info' => session('portal_info')]);
    }

    public function requestLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190']], ['email.required' => 'Bitte Ihre E-Mail-Adresse eingeben.', 'email.email' => 'Bitte eine gültige E-Mail-Adresse eingeben.']);
        $key = 'portal-link:'.sha1(Portal::normalize($data['email']));
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return back()->withErrors(['email' => 'Zu viele Anfragen. Bitte warten Sie einige Minuten.']);
        }
        RateLimiter::hit($key, 600);
        Portal::requestLogin($data['email']);

        // Immer dieselbe Antwort, damit niemand herausfinden kann, wer ein Konto hat.
        return redirect()->route('portal.login')->with('portal_sent', true);
    }

    /** Zwischenseite, damit Mail-Programme den Link nicht schon beim Vorabruf verbrauchen. */
    public function confirm(string $token)
    {
        $u = Portal::peek($token);
        if ($u === null) {
            return $this->page('portal.expired', [], 410);
        }

        return $this->page('portal.confirm', ['token' => $token]);
    }

    public function enter(Request $request, string $token)
    {
        $u = Portal::consume($token);
        if ($u === null) {
            return $this->page('portal.expired', [], 410);
        }
        $request->session()->regenerate();
        $request->session()->put(['portal_user_id' => $u->id, 'portal_seen' => time()]);

        return redirect()->route('portal.home');
    }

    public function logout(Request $request)
    {
        $request->session()->forget(['portal_user_id', 'portal_seen']);

        return redirect()->route('portal.login')->with('portal_info', 'Sie sind abgemeldet.');
    }

    // ------------------------------------------------------------------ Bereiche

    public function home(Request $request)
    {
        $pu = $this->pu($request);
        $acc = Portal::account($pu->tenant);

        return $this->page('portal.home', ['pu' => $pu, 'account' => $acc, 'docs' => Portal::documents($pu->tenant)->count(), 'meters' => Portal::meters($pu->tenant)->count()]);
    }

    public function account(Request $request)
    {
        $pu = $this->pu($request);

        return $this->page('portal.account', ['pu' => $pu, 'account' => Portal::account($pu->tenant)]);
    }

    public function documents(Request $request)
    {
        $pu = $this->pu($request);

        return $this->page('portal.documents', ['pu' => $pu, 'docs' => Portal::documents($pu->tenant)]);
    }

    public function document(Request $request, int $document)
    {
        $pu = $this->pu($request);
        $doc = Portal::ownDocument($pu->tenant, $document);
        abort_if($doc === null, 404);
        $inline = $doc->mime === 'application/pdf' || $doc->isImage();
        $name = str_replace(['"', "\r", "\n"], '', $doc->original_name);

        return response(DocumentStore::get($doc->path), 200, [
            'Content-Type' => $doc->mime, 'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function meters(Request $request)
    {
        $pu = $this->pu($request);

        return $this->page('portal.meters', ['pu' => $pu, 'meters' => Portal::meters($pu->tenant)]);
    }

    public function readingStore(Request $request)
    {
        $pu = $this->pu($request);
        $d = $request->validate(['meter_id' => ['required', 'integer'], 'read_on' => ['required', 'date_format:Y-m-d'], 'value' => ['required', 'string', 'max:20']], ['value.required' => 'Bitte den Zählerstand eingeben.']);
        try {
            Portal::addReading($pu->tenant, (int) $d['meter_id'], $d['read_on'], $d['value']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['value' => $e->getMessage()])->withInput();
        }

        return redirect()->route('portal.meters')->with('portal_ok', 'Danke, Ihr Zählerstand ist gespeichert.');
    }

    public function damage(Request $request)
    {
        $pu = $this->pu($request);

        return $this->page('portal.damage', ['pu' => $pu, 'leases' => Portal::leases($pu->tenant)]);
    }

    public function damageStore(Request $request)
    {
        $pu = $this->pu($request);
        $d = $request->validate(['lease_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:120'], 'text' => ['required', 'string', 'max:3000']], ['title.required' => 'Bitte kurz sagen, worum es geht.', 'text.required' => 'Bitte beschreiben Sie den Schaden.']);
        $lease = Lease::query()->where('tenant_id', $pu->tenant_id)->find((int) $d['lease_id']);
        abort_if($lease === null, 404);
        Portal::reportDamage($pu, $lease, $d['title'], $d['text']);

        return redirect()->route('portal.home')->with('portal_ok', 'Danke, wir haben Ihre Meldung erhalten und kümmern uns darum. Bei einem Notfall rufen Sie bitte die Notfallnummer an.');
    }

    // ------------------------------------------------------------------ Hilfen

    private function pu(Request $request): TenantPortalUser
    {
        return $request->attributes->get('portal_user');
    }

    private function page(string $view, array $data, int $status = 200)
    {
        return response()->view($view, $data + ['siteName' => MailSettings::fromName()], $status)
            ->header('X-Robots-Tag', 'noindex, nofollow')->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store, private');
    }
}
