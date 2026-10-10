<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use App\Support\RecoveryCodes;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    private const MAX_WRONG_CODES = 5;
    private const LOCK_MINUTES = 15;

    /** Eingabe des Codes bei jeder Anmeldung */
    public function challenge(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }
        if ($request->session()->get('two_factor_passed')) {
            return redirect()->route('dashboard');
        }

        return view('two-factor.challenge');
    }

    public function verify(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.setup');
        }
        if ($user->isLocked()) {
            return $this->kickOut($request, 'Das Konto ist vorübergehend gesperrt. Bitte später erneut versuchen.');
        }

        $request->validate(['code' => ['required', 'string', 'max:32']]);

        $input = trim((string) $request->input('code'));
        $userKey = '2fa-user:'.$user->id;

        $step = Totp::verify($user->two_factor_secret, $input, (int) $user->two_factor_last_step);
        $usedRecovery = false;

        if ($step === null) {
            $usedRecovery = $this->consumeRecoveryCode($user, $input);
        }

        if ($step === null && ! $usedRecovery) {
            RateLimiter::hit($userKey, 900);
            RateLimiter::hit('login-ip:'.$request->ip(), 900);
            Audit::log('two_factor.failed', 'Falscher Code eingegeben', $user);

            if (RateLimiter::attempts($userKey) >= self::MAX_WRONG_CODES) {
                $user->forceFill(['locked_until' => now()->addMinutes(self::LOCK_MINUTES)])->save();
                RateLimiter::clear($userKey);
                Audit::log('two_factor.locked', 'Konto für '.self::LOCK_MINUTES.' Minuten gesperrt (zu viele falsche Codes)', $user);

                return $this->kickOut($request, 'Zu viele falsche Codes. Das Konto ist für '.self::LOCK_MINUTES.' Minuten gesperrt.');
            }

            throw ValidationException::withMessages([
                'code' => 'Der Code stimmt nicht. Bitte den aktuellen Code aus der App eingeben.',
            ]);
        }

        if ($step !== null) {
            $user->two_factor_last_step = $step;
        }
        $user->last_login_at = now();
        $user->save();

        RateLimiter::clear($userKey);
        $request->session()->regenerate();
        $request->session()->put('two_factor_passed', true);

        if ($usedRecovery) {
            Audit::log('two_factor.recovery_used', 'Wiederherstellungscode benutzt (noch '.count($user->two_factor_recovery_codes ?? []).' übrig)', $user);
        }
        Audit::log('login.success', 'Angemeldet', $user);

        return redirect()->route('dashboard');
    }

    /** Ersteinrichtung der Authenticator-App */
    public function setup(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.challenge');
        }

        $secret = $request->session()->get('two_factor_pending_secret');
        if (! $secret) {
            $secret = Totp::generateSecret();
            $request->session()->put('two_factor_pending_secret', $secret);
        }

        $uri = Totp::uri($secret, $user->email, (string) config('app.name'));

        return view('two-factor.setup', [
            'secretGrouped' => trim(chunk_split($secret, 4, ' ')),
            'qrSvg' => $this->qrSvg($uri),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasTwoFactorEnabled()) {
            return redirect()->route('two-factor.challenge');
        }

        $request->validate(['code' => ['required', 'string', 'max:16']]);

        $secret = $request->session()->get('two_factor_pending_secret');
        if (! $secret) {
            return redirect()->route('two-factor.setup');
        }

        $key = '2fa-setup:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw ValidationException::withMessages(['code' => 'Zu viele Versuche. Bitte in ein paar Minuten erneut versuchen.']);
        }

        $step = Totp::verify($secret, (string) $request->input('code'));
        if ($step === null) {
            RateLimiter::hit($key, 600);
            throw ValidationException::withMessages([
                'code' => 'Der Code stimmt nicht. Bitte prüfen, ob Datum und Uhrzeit am Handy stimmen, und den aktuellen Code eingeben.',
            ]);
        }

        $codes = RecoveryCodes::generate();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
            'two_factor_recovery_codes' => array_map(fn (string $c) => RecoveryCodes::fingerprint($c), $codes),
            'last_login_at' => now(),
        ])->save();

        $request->session()->forget('two_factor_pending_secret');
        $request->session()->regenerate();
        $request->session()->put('two_factor_passed', true);

        Audit::log('two_factor.enabled', 'Zwei-Faktor-Anmeldung eingerichtet', $user);
        Audit::log('login.success', 'Angemeldet', $user);

        return redirect()->route('two-factor.recovery')->with('recovery_codes', $codes);
    }

    /** Einmalige Anzeige der Wiederherstellungscodes */
    public function recovery(Request $request): View|RedirectResponse
    {
        $codes = $request->session()->get('recovery_codes');

        if (! $codes) {
            return redirect()->route('dashboard');
        }

        return view('two-factor.recovery', ['codes' => $codes]);
    }

    private function consumeRecoveryCode(User $user, string $input): bool
    {
        $normalized = RecoveryCodes::normalize($input);
        if (strlen($normalized) !== 10) {
            return false;
        }

        $fingerprint = RecoveryCodes::fingerprint($normalized);
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $fingerprint)) {
                unset($codes[$index]);
                $user->two_factor_recovery_codes = array_values($codes);

                return true;
            }
        }

        return false;
    }

    private function qrSvg(string $uri): ?string
    {
        if (! class_exists(\BaconQrCode\Writer::class)) {
            return null;
        }

        try {
            $renderer = new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(220, 2),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
            );
            $svg = (new \BaconQrCode\Writer($renderer))->writeString($uri);

            return (string) preg_replace('/^<\?xml[^>]*\?>\s*/', '', $svg);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function kickOut(Request $request, string $message): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
