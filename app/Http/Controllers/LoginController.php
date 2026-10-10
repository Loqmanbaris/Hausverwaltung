<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_FAILS_PER_ACCOUNT = 5;
    private const MAX_FAILS_PER_IP = 20;
    private const LOCK_MINUTES = 15;

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = Str::lower(trim($data['email']));
        $ipKey = 'login-ip:'.$request->ip();
        // Bewusst für alle Fehlerfälle dieselbe Meldung: so lässt sich nicht herausfinden, welche Adressen existieren.
        $message = 'Die Anmeldung war nicht erfolgreich. Bitte Eingaben prüfen oder später erneut versuchen.';

        if (RateLimiter::tooManyAttempts($ipKey, self::MAX_FAILS_PER_IP)) {
            $minutes = (int) ceil(RateLimiter::availableIn($ipKey) / 60);
            throw ValidationException::withMessages([
                'email' => "Zu viele Versuche von diesem Anschluss. Bitte in {$minutes} Minute(n) erneut versuchen.",
            ]);
        }

        $user = User::where('email', $email)->first();

        if ($user && $user->isLocked()) {
            RateLimiter::hit($ipKey, 900);
            Audit::log('login.blocked', 'Anmeldeversuch während der Sperre', label: $email);
            throw ValidationException::withMessages(['email' => $message]);
        }

        if ($user) {
            $passwordOk = Hash::check($data['password'], $user->password);
        } else {
            Hash::make($data['password']); // gleiche Rechenzeit wie bei einem echten Konto
            $passwordOk = false;
        }

        if (! $passwordOk) {
            RateLimiter::hit($ipKey, 900);

            if ($user) {
                $user->failed_login_count++;
                if ($user->failed_login_count >= self::MAX_FAILS_PER_ACCOUNT) {
                    $user->failed_login_count = 0;
                    $user->locked_until = now()->addMinutes(self::LOCK_MINUTES);
                    $user->save();
                    Audit::log('login.locked', 'Konto für '.self::LOCK_MINUTES.' Minuten gesperrt (zu viele Fehlversuche)', $user, label: $email);
                } else {
                    $user->save();
                }
            }

            Audit::log('login.failed', 'Falsches Passwort oder unbekannte E-Mail-Adresse', label: $email);
            throw ValidationException::withMessages(['email' => $message]);
        }

        if (! $user->is_active) {
            Audit::log('login.blocked', 'Anmeldeversuch eines gesperrten Benutzers', $user, label: $email);
            throw ValidationException::withMessages(['email' => $message]);
        }

        $user->forceFill(['failed_login_count' => 0, 'locked_until' => null])->save();

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('two_factor_passed', false);

        Audit::log('login.password_ok', 'Passwort korrekt, zweiter Faktor steht noch aus', actor: $user);

        return redirect()->route($user->hasTwoFactorEnabled() ? 'two-factor.challenge' : 'two-factor.setup');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Audit::log('logout', 'Abgemeldet');

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
