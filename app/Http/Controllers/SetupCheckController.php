<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Settings;
use App\Support\Setup\Einrichtung;
use Illuminate\Http\Request;

/** Einrichtung prüfen (Paket 25, nur Administrator). */
class SetupCheckController extends Controller
{
    public function index()
    {
        return view('setup.index', ['checks' => Einrichtung::checks(), 'summary' => Einrichtung::summary()]);
    }

    public function confirmDatev(Request $request)
    {
        $on = $request->boolean('confirmed');
        Settings::putMany(['datev_accounts_confirmed' => $on ? '1' : '0']);
        Audit::log('settings.updated', null, 'DATEV-Konten mit Steuerberater '.($on ? 'abgestimmt' : 'wieder offen'));

        return redirect()->route('setup.index')->with('status', 'Gespeichert.');
    }
}
