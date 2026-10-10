<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('password.change', ['forced' => $request->user()->must_change_password]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => [
                'required', 'confirmed', 'different:current_password',
                Password::min(12)->letters()->mixedCase()->numbers(),
            ],
        ]);

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
        ])->save();

        $request->session()->regenerate();
        Audit::log('password.changed', 'Passwort geändert', $user);

        return redirect()->route('dashboard')->with('status', 'Das Passwort wurde geändert.');
    }
}
