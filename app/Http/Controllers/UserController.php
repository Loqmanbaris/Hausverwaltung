<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        return view('users.index', ['users' => User::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', 'in:admin,staff'],
        ]);

        $temp = $this->temporaryPassword();

        $user = User::create([
            'name' => trim($data['name']),
            'email' => Str::lower(trim($data['email'])),
            'password' => $temp,
            'role' => $data['role'],
            'is_active' => true,
            'must_change_password' => true,
        ]);

        Audit::log('user.created', 'Benutzer angelegt: '.$user->email.' ('.$user->roleLabel().')', $user, null, [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);

        return redirect()->route('users.index')->with('temp_password', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $temp,
        ]);
    }

    /** Recht „Alle E-Mails“ für einen Mitarbeiter ein- oder ausschalten. */
    public function toggleMailAll(User $user): RedirectResponse
    {
        $user->forceFill(['mail_all' => ! $user->mail_all])->save();
        Audit::log('user.mail_all', ($user->mail_all ? 'Darf alle E-Mails sehen: ' : 'Sieht nur E-Mails seiner Eigentümer: ').$user->email, $user);

        return back()->with('status', $user->name.($user->mail_all ? ' sieht jetzt alle E-Mails.' : ' sieht jetzt nur noch E-Mails ohne Zuordnung und die seiner Eigentümer.'));
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['users' => 'Das eigene Konto kann nicht gesperrt werden.']);
        }

        $user->forceFill([
            'is_active' => ! $user->is_active,
            'locked_until' => null,
            'failed_login_count' => 0,
        ])->save();

        if (! $user->is_active) {
            $this->endSessions($user);
        }

        Audit::log(
            $user->is_active ? 'user.unblocked' : 'user.blocked',
            ($user->is_active ? 'Entsperrt: ' : 'Gesperrt: ').$user->email,
            $user
        );

        return back()->with('status', $user->is_active
            ? $user->name.' ist wieder freigeschaltet.'
            : $user->name.' wurde gesperrt und sofort abgemeldet.');
    }

    public function resetTwoFactor(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['users' => 'Für das eigene Konto geht das nur über den Server-Befehl.']);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => 0,
            'two_factor_recovery_codes' => null,
        ])->save();

        $this->endSessions($user);
        Audit::log('two_factor.reset', 'Zwei-Faktor zurückgesetzt für '.$user->email, $user);

        return back()->with('status', 'Zwei-Faktor wurde für '.$user->name.' zurückgesetzt. Bei der nächsten Anmeldung wird die App neu eingerichtet.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->withErrors(['users' => 'Das eigene Passwort ändert man über „Passwort“ oben rechts.']);
        }

        $temp = $this->temporaryPassword();

        $user->forceFill([
            'password' => $temp,
            'must_change_password' => true,
            'failed_login_count' => 0,
            'locked_until' => null,
        ])->save();

        $this->endSessions($user);
        Audit::log('password.reset', 'Vorläufiges Passwort gesetzt für '.$user->email, $user);

        return redirect()->route('users.index')->with('temp_password', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => $temp,
        ]);
    }

    private function temporaryPassword(): string
    {
        return Str::password(14, symbols: false);
    }

    /** Meldet den Benutzer überall sofort ab. */
    private function endSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
