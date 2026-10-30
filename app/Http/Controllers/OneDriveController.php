<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Backup\OneDrive;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/** OneDrive-Einstellungen für die Sicherung (Paket 40), nur für den Administrator. */
class OneDriveController extends Controller
{
    public function save(Request $request)
    {
        $in = $request->only(['tenant', 'client_id', 'secret', 'user', 'folder']) + ['auto' => $request->boolean('auto')];
        $errors = OneDrive::validate($in, ! OneDrive::hasSecret());
        if ($errors !== []) {
            return back()->withErrors($errors, 'onedrive')->withInput($request->except(['secret', 'password']));
        }
        // Änderungen an den Zugangsdaten verlangen das Passwort, weil damit Sicherungen an einen anderen Ort fließen können.
        if (! Hash::check((string) $request->input('password'), (string) $request->user()->password)) {
            return back()->withErrors(['password' => 'Bitte zur Bestätigung dein Passwort eingeben.'], 'onedrive')->withInput($request->except(['secret', 'password']));
        }
        OneDrive::save($in);
        Audit::log('backup.onedrive_saved', 'OneDrive-Einstellungen für die Sicherung gespeichert (Ordner: '.trim($in['folder']).', Konto: '.trim($in['user']).')');

        return redirect()->route('backups.index')->with('status', 'Die OneDrive-Einstellungen sind gespeichert. Teste jetzt die Verbindung.');
    }

    public function test()
    {
        @set_time_limit(120);
        try {
            $name = OneDrive::test();
        } catch (RuntimeException $e) {
            return redirect()->route('backups.index')->withErrors(['onedrive_test' => $e->getMessage()]);
        }

        return redirect()->route('backups.index')->with('status', 'Die Verbindung funktioniert. Die Datei „'.$name.'“ liegt jetzt im OneDrive-Ordner. Du kannst sie löschen.');
    }

    public function upload()
    {
        @set_time_limit(600);
        try {
            $sent = OneDrive::syncPending(10);
        } catch (RuntimeException $e) {
            return redirect()->route('backups.index')->withErrors(['onedrive_test' => $e->getMessage()]);
        }

        return redirect()->route('backups.index')->with('status', $sent === [] ? 'Alle Sicherungen liegen schon bei OneDrive.' : count($sent).' Sicherung(en) bei OneDrive abgelegt.');
    }

    public function disable()
    {
        \App\Support\Settings::putMany(['onedrive_auto' => '0']);
        Audit::log('backup.onedrive_off', 'Automatischer Upload zu OneDrive ausgeschaltet');

        return redirect()->route('backups.index')->with('status', 'Der automatische Upload ist ausgeschaltet. Die Einstellungen bleiben gespeichert.');
    }
}
