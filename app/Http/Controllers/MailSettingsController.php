<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Mail\MailService;
use App\Support\Mail\MailSettings;
use Illuminate\Http\Request;

/** E-Mail-Zugang einrichten (nur Administrator, Paket 21). */
class MailSettingsController extends Controller
{
    public function edit()
    {
        $values = [];
        foreach (array_keys(MailSettings::DEFAULTS) as $k) {
            $values[$k] = MailSettings::get($k);
        }

        return view('mail.settings', ['v' => $values, 'hasPassword' => MailSettings::hasPassword(), 'configured' => MailSettings::configured()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'mail_host' => ['required', 'string', 'max:120', 'regex:/^[A-Za-z0-9.-]+$/'],
            'mail_port' => ['required', 'integer', 'in:25,465,587,2525'],
            'mail_username' => ['required', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:190'],
            'mail_from_address' => ['required', 'email', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:120'],
            'mail_bcc' => ['nullable', 'email', 'max:190'],
            'mail_signature' => ['nullable', 'string', 'max:1000'],
            'mail_notify' => ['nullable', 'in:1'],
        ], [
            'mail_host.regex' => 'Bitte nur den Servernamen eingeben, z. B. smtp.ionos.de.',
            'mail_port.in' => 'Bitte Port 587 (empfohlen) oder 465 wählen.',
            'mail_from_address.email' => 'Bitte eine gültige Absender-Adresse eingeben.',
            'mail_bcc.email' => 'Bitte eine gültige Adresse für die Kopie eingeben.',
        ]);
        if (! MailSettings::hasPassword() && trim((string) ($data['mail_password'] ?? '')) === '') {
            return back()->withInput()->withErrors(['mail_password' => 'Bitte das Passwort des Postfachs eingeben.']);
        }
        $data['mail_notify'] = isset($data['mail_notify']) ? '1' : '0';
        MailSettings::save($data, $data['mail_password'] ?? null);
        Audit::log('mail.settings', null, 'E-Mail-Einstellungen geändert'.(! empty($data['mail_password']) ? ' (neues Passwort)' : ''));

        return redirect()->route('mail.settings')->with('status', 'Gespeichert. Schicke jetzt eine Test-E-Mail, um die Verbindung zu prüfen.');
    }

    public function test(Request $request)
    {
        $data = $request->validate(['test_to' => ['required', 'email', 'max:190']], ['test_to.email' => 'Bitte eine gültige Adresse eingeben.']);
        $log = MailService::send([$data['test_to']], 'Test-E-Mail aus dem CRM', "Hallo,\n\ndiese Test-E-Mail zeigt: Der E-Mail-Versand aus dem CRM funktioniert.\n\nGesendet am ".now()->timezone('Europe/Berlin')->format('d.m.Y H:i')." Uhr.", [], ['kind' => 'test'], $request->user(), false);

        return $log->status === 'gesendet'
            ? back()->with('status', 'Die Test-E-Mail wurde an '.$data['test_to'].' gesendet. Bitte im Postfach nachsehen (auch im Spam-Ordner).')
            : back()->withInput()->withErrors(['test_to' => 'Nicht gesendet: '.$log->error]);
    }
}
