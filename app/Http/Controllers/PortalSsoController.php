<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Gemeinsamer Zugang: Anmeldung per Sprung-Schein aus dem Tele-Master-Portal (portal.tele-master.de).
 *
 * Der Schein ist 60 Sekunden gültig, nur einmal verwendbar und mit PORTAL_SSO_SECRET unterschrieben
 * (identisch mit SSO_HAUSVERWALTUNG_SECRET im Portal). Das Portal lässt den Sprung nur zu, wenn die Person dort mit
 * Authenticator angemeldet ist; deshalb gilt der zweite Faktor hier als bestanden.
 * Ohne Schlüssel in der .env ist die Seite abgeschaltet.
 */
class PortalSsoController extends Controller
{
    private const AUDIENCE = 'hausverwaltung';

    private const MAX_CLOCK_SKEW = 30;

    public function __invoke(Request $request): RedirectResponse
    {
        $secret = (string) config('services.portal_sso.secret');
        $fail = fn (string $why) => $this->fail($why);

        if (strlen($secret) < 32) {
            return $fail('Gemeinsamer Zugang ist nicht eingerichtet');
        }

        $token = (string) $request->query('token', '');
        $parts = explode('.', $token);
        if (count($parts) !== 2 || strlen($token) > 2000) {
            return $fail('Ungültiger Sprung-Schein');
        }
        [$body, $sig] = $parts;

        $expected = rtrim(strtr(base64_encode(hash_hmac('sha256', $body, $secret, true)), '+/', '-_'), '=');
        if (! hash_equals($expected, $sig)) {
            return $fail('Unterschrift des Sprung-Scheins stimmt nicht');
        }

        $payload = json_decode((string) base64_decode(strtr($body, '-_', '+/'), true), true);
        if (! is_array($payload)
            || ($payload['aud'] ?? null) !== self::AUDIENCE
            || ! is_string($payload['sub'] ?? null)
            || ! is_string($payload['jti'] ?? null) || strlen($payload['jti']) < 16
            || ! is_int($payload['exp'] ?? null) || ! is_int($payload['iat'] ?? null)) {
            return $fail('Ungültiger Sprung-Schein');
        }

        $now = time();
        if ($payload['exp'] < $now || $payload['iat'] > $now + self::MAX_CLOCK_SKEW) {
            return $fail('Sprung-Schein abgelaufen – im Portal bitte erneut auf „Öffnen“ klicken');
        }

        // Nur einmal verwendbar
        if (! Cache::add('portal-sso:'.$payload['jti'], 1, now()->addMinutes(5))) {
            return $fail('Sprung-Schein wurde schon verwendet');
        }

        $email = Str::lower(trim($payload['sub']));
        $user = User::where('email', $email)->first();
        $by = is_string($payload['by'] ?? null) ? $payload['by'] : '?';

        if (! $user || ! $user->is_active || $user->isLocked()) {
            Audit::log('login.sso_blocked', 'Sprung aus dem Portal abgelehnt (Konto fehlt, gesperrt oder inaktiv), Portal-Benutzer: '.$by, $user, label: $email);

            return $fail('Für dieses Konto ist der gemeinsame Zugang nicht möglich');
        }

        // Wer hier schon als jemand anderes angemeldet ist, wird abgemeldet
        if (Auth::check() && Auth::id() !== $user->id) {
            Auth::logout();
            $request->session()->invalidate();
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->regenerateToken();
        $request->session()->put('two_factor_passed', true);
        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();

        Audit::log('login.sso', 'Angemeldet über den gemeinsamen Zugang (Portal-Benutzer: '.$by.')', actor: $user);

        return redirect()->route('tag.index'); // Startseite: Mein Tag
    }

    private function fail(string $why): RedirectResponse
    {
        Audit::log('login.sso_failed', $why, label: 'Portal-Sprung');

        return redirect()->route('login')->withErrors(['email' => $why.'. Bitte normal anmelden.']);
    }
}
