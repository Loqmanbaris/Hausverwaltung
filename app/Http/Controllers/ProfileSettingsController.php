<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Profilmenü → Einstellungen: alles an einem Ort als Kacheln statt im Seitenmenü (Gruppen „Stammdaten“, „Webseite“, „Verwaltung“).
 * Aufbau wie im Portal (App\Filament\Pages\Settings) und bei Freytag (seiten/einstellungen.php).
 */
class ProfileSettingsController extends Controller
{
    public function index(Request $request)
    {
        return view('profile.settings', ['groups' => self::groups($request)]);
    }

    /**
     * Gruppen mit Kacheln: [Titel, [[Bezeichnung, Beschreibung, Symbol, Ziel, Badge], …]].
     * Ziel: ['url' => …, 'blank' => bool] | ['post' => Route, 'fields' => […]] | ['install' => true]. Nur sichtbare Einträge.
     *
     * @return array<int, array{0: string, 1: array<int, array>}>
     */
    public static function groups(Request $request): array
    {
        $user = $request->user();
        $admin = $user->isAdmin();
        $link = fn (string $route, bool $onlyAdmin = false) => Route::has($route) && (! $onlyAdmin || $admin) ? ['url' => route($route), 'blank' => false] : null;
        $count = function (callable $f) {
            try {
                return (int) $f();
            } catch (\Throwable) {
                return 0;
            }
        };
        $dayMail = (bool) ($user->day_mail ?? false);

        $groups = [
            ['Mein Konto', [
                ['Passwort ändern', 'Eigenes Passwort neu festlegen', '🔑', $link('password.change')],
                ['Startseite anpassen', 'Kacheln der Übersicht anordnen', '🧩', $link('dashboard.layout')],
                ['Kalender-Abo', 'Termine und Fristen im Handy-Kalender', '📅', $link('calendar.index')],
                ['„Mein Tag“ per E-Mail', $dayMail ? 'Zurzeit an – tippen zum Ausschalten' : 'Zurzeit aus – tippen zum Einschalten', '☀️',
                    Route::has('tag.mail') ? ['post' => 'tag.mail', 'fields' => ['on' => $dayMail ? '0' : '1']] : null],
            ]],
            ['Dieses Gerät', [
                ['Als App installieren', 'Verwaltung wie eine App auf den Startbildschirm', '📱', ['install' => true]],
            ]],
            ['Stammdaten', [
                ['Eigentümer', 'Stammdaten, Bankverbindungen, Freigaben', '👤', $link('owners.index')],
                ['Objekte und Einheiten', 'Häuser, Wohnungen, Gewerbe, Zähler', '🏢', $link('properties.index')],
                ['Mieter', 'Privatpersonen und Firmen mit Ansprechpartnern', '🧑‍🤝‍🧑', $link('tenants.index')],
                ['Mietverträge', 'Verträge, Mietverlauf, Übergaben, Mieterkonto', '📝', $link('leases.index')],
                ['Dokumente', 'Ablage mit Fristen', '🗂️', $link('documents.index'),
                    $count(fn () => \App\Models\Document::visibleTo($user)->where('archived', false)->whereNotNull('expires_on')->where('expires_on', '<', date('Y-m-d'))->count())],
            ]],
            ['Finanzen und Steuer', [
                ['Finanzierung', 'Darlehen, Tilgungspläne, Werte und Rendite', '🏦', $link('finance.index')],
                ['Anlage V', 'Einnahmen und Werbungskosten je Objekt für die Steuer', '🧮', $link('anlage-v.index')],
                ['Jahresbericht', 'Jahresübersicht je Eigentümer zum Ausdrucken', '📊', $link('report.index')],
                ['DATEV-Export', 'Buchungen für den Steuerberater', '📤', $link('datev.index', true)],
            ]],
            ['Webseite', [
                ['Inserate', 'Freie Wohnungen und Gewerbe online stellen', '🏷️', $link('listings.index')],
                ['Anfragen', 'Anfragen über die Webseite', '💬', $link('inquiries.index'), $count(fn () => \App\Models\Inquiry::newCountFor($user))],
                ['Webseite gestalten', 'Texte, Bilder, Farben und Objekte der Webseite', '🎨', $link('site.edit', true)],
                ['Webseite ansehen', 'baris-verwaltung.de, wie Besucher sie sehen (neuer Tab)', '🌐', Route::has('home') ? ['url' => route('home'), 'blank' => true] : null],
            ]],
            ['Andere Systeme', [
                ['Telemaster-Portal', 'Zentrale Anmeldung, von dort auch Freytag (neuer Tab)', '🏬', ['url' => 'https://portal.tele-master.de', 'blank' => true]],
            ]],
            ['Verwaltung', [
                ['Benutzer', 'Zugänge, Rollen, Zwei-Faktor zurücksetzen', '👥', $link('users.index', true)],
                ['Allgemeine Einstellungen', 'Impressum, Datenschutz, Startseite, Kostenarten', '⚙️', $link('settings.edit', true)],
                ['E-Mail einrichten', 'Postfach für Senden und Empfangen (IONOS)', '✉️', $link('mail.settings', true)],
                ['Vertragsvorlagen', 'Vorlagen für Mietverträge', '📄', $link('contract-templates.index', true)],
                ['DATEV-Einstellungen', 'Berater, Mandant, Konten', '🧾', $link('datev.settings', true)],
            ]],
            ['System', [
                ['Einrichtung prüfen', 'Was noch fehlt, Schritt für Schritt', '✅', $link('setup.index', true),
                    $admin ? $count(fn () => \App\Support\Setup\Einrichtung::summary()['open']) : 0],
                ['Sicherungen', 'Sicherungen erstellen, herunterladen, OneDrive', '💾', $link('backups.index', true)],
                ['Löschfristen', 'Datenschutz: alte Mieterdaten bereinigen', '🗑️', $link('retention.index', true)],
                ['Updates', 'Programmstand', '⬆️', $link('updates.index', true)],
                ['Status', 'Server, Cronjob, Speicher', '🩺', $link('status', true)],
                ['Protokoll', 'Wer hat wann was geändert', '📜', $link('audit.index', true)],
            ]],
        ];

        return collect($groups)
            ->map(fn ($g) => [$g[0], array_values(array_filter($g[1], fn ($i) => $i[3] !== null))])
            ->filter(fn ($g) => $g[1] !== [])->values()->all();
    }

    /** Summe der Hinweise für das Profilmenü (z. B. offene Einrichtungsschritte). */
    public static function badge(Request $request): int
    {
        $sum = 0;
        foreach (self::groups($request) as [, $items]) {
            foreach ($items as $i) {
                $sum += (int) ($i[4] ?? 0);
            }
        }

        return $sum;
    }
}
