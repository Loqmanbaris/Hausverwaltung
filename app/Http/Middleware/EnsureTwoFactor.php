<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lässt nur Benutzer durch, die aktiv sind, den zweiten Faktor bestanden haben
 * und (falls verlangt) ihr vorläufiges Passwort geändert haben.
 */
class EnsureTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Dieser Zugang ist gesperrt.',
            ]);
        }

        if (! $request->session()->get('two_factor_passed')) {
            return redirect()->route(
                $user->hasTwoFactorEnabled() ? 'two-factor.challenge' : 'two-factor.setup'
            );
        }

        if ($user->must_change_password && ! $request->routeIs('password.change', 'password.update')) {
            return redirect()->route('password.change');
        }

        return $next($request);
    }
}
