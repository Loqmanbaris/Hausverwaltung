<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Backup\BackupService;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Statusseite für den Administrator: Versionen und einfache Sicherheitsprüfungen.
 * Zustände: ok (in Ordnung), warn (bitte ansehen), off (Handlungsbedarf).
 */
class StatusController extends Controller
{
    public function index()
    {
        $software = [
            'PHP' => PHP_VERSION.' ('.PHP_SAPI.')',
            'Laravel' => app()->version(),
            'Datenbank' => $this->databaseVersion(),
            'Umgebung' => app()->environment(),
            'Serverzeit' => now()->timezone('Europe/Berlin')->format('d.m.Y H:i'),
        ];

        $checks = [
            $this->debugCheck(),
            $this->httpsCheck(),
            $this->cookieCheck(),
            $this->writableCheck(),
            $this->extensionsCheck(),
            $this->twoFactorCheck(),
            $this->imprintCheck(),
            $this->backupKeyCheck(),
            $this->backupCheck(),
            $this->backupDownloadCheck(),
            $this->cronCheck(),
            [
                'label' => 'Sicherheitsupdates',
                'state' => 'warn',
                'value' => 'Automatische Prüfung noch nicht eingerichtet',
                'hint' => 'Kommt mit der Wartungserinnerung. Bis dahin werden Updates von Hand eingespielt.',
            ],
        ];

        return view('status.index', [
            'software' => $software,
            'checks' => $checks,
            'cronUrl' => \App\Support\CronToken::url(),
        ]);
    }

    /** Neue Cron-Adresse erzeugen (die alte gilt sofort nicht mehr). */
    public function newCronToken()
    {
        Settings::putMany(['cron_token' => \Illuminate\Support\Str::random(48)]);
        \App\Support\Audit::log('cron.token_renewed', 'Neue Cron-Adresse erzeugt');

        return redirect()->to(route('status').'#cron')->with('status', 'Neue Cron-Adresse erzeugt. Bitte jetzt bei IONOS im Cronjob die Adresse austauschen, die alte funktioniert nicht mehr.');
    }

    private function cronCheck(): array
    {
        try {
            $last = DB::table('audit_logs')->where('event', 'cron.run')->max('created_at');
        } catch (\Throwable) {
            $last = null;
        }
        if ($last === null) {
            return ['label' => 'Täglicher Lauf (Cronjob)', 'state' => 'warn', 'value' => 'Noch nie gelaufen', 'hint' => 'Cronjob bei IONOS mit der Adresse unten anlegen (täglich, HTTP GET).'];
        }
        $at = \Illuminate\Support\Carbon::parse($last);
        $old = $at->lt(now()->subHours(36));

        return [
            'label' => 'Täglicher Lauf (Cronjob)',
            'state' => $old ? 'off' : 'ok',
            'value' => 'Zuletzt '.$at->timezone('Europe/Berlin')->format('d.m.Y H:i'),
            'hint' => $old ? 'Seit mehr als 36 Stunden nicht gelaufen. Cronjob bei IONOS prüfen.' : '',
        ];
    }

    private function databaseVersion(): string
    {
        try {
            return (string) (DB::selectOne('select version() as v')->v ?? 'unbekannt');
        } catch (\Throwable) {
            return 'nicht erreichbar';
        }
    }

    private function debugCheck(): array
    {
        $on = (bool) config('app.debug');

        return [
            'label' => 'Fehlerdetails ausgeblendet',
            'state' => $on ? 'off' : 'ok',
            'value' => $on ? 'Nein, Debug-Modus ist an' : 'Ja',
            'hint' => $on ? 'In der Datei .env muss APP_DEBUG=false stehen, sonst sehen Besucher interne Fehlermeldungen.' : '',
        ];
    }

    private function httpsCheck(): array
    {
        $https = str_starts_with((string) config('app.url'), 'https://');

        return [
            'label' => 'Adresse mit https',
            'state' => $https ? 'ok' : 'off',
            'value' => (string) config('app.url'),
            'hint' => $https ? '' : 'APP_URL in der .env muss mit https:// beginnen.',
        ];
    }

    private function cookieCheck(): array
    {
        $secure = config('session.secure') === true;

        return [
            'label' => 'Sitzungs-Cookie nur über https',
            'state' => $secure ? 'ok' : 'warn',
            'value' => $secure ? 'Ja' : 'Noch nicht erzwungen',
            'hint' => $secure ? '' : 'Empfohlen: In der .env die Zeile SESSION_SECURE_COOKIE=true ergänzen.',
        ];
    }

    private function writableCheck(): array
    {
        $paths = ['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')];
        $bad = array_keys(array_filter($paths, fn ($p) => ! is_writable($p)));

        return [
            'label' => 'Schreibrechte für Zwischenspeicher',
            'state' => $bad === [] ? 'ok' : 'off',
            'value' => $bad === [] ? 'In Ordnung' : 'Nicht beschreibbar: '.implode(', ', $bad),
            'hint' => '',
        ];
    }

    private function extensionsCheck(): array
    {
        $now = ['mbstring', 'openssl', 'pdo_mysql', 'fileinfo', 'curl'];
        $later = ['zip', 'gd'];

        $missingNow = array_values(array_filter($now, fn ($e) => ! extension_loaded($e)));
        $missingLater = array_values(array_filter($later, fn ($e) => ! extension_loaded($e)));

        if ($missingNow !== []) {
            return [
                'label' => 'PHP-Erweiterungen',
                'state' => 'off',
                'value' => 'Fehlen: '.implode(', ', $missingNow),
                'hint' => 'Diese Erweiterungen werden gebraucht.',
            ];
        }

        if ($missingLater !== []) {
            return [
                'label' => 'PHP-Erweiterungen',
                'state' => 'warn',
                'value' => 'Für spätere Pakete fehlen: '.implode(', ', $missingLater),
                'hint' => 'Ohne zip werden Sicherungen als tar.gz gepackt, gd wird für verkleinerte Belegfotos gebraucht.',
            ];
        }

        return ['label' => 'PHP-Erweiterungen', 'state' => 'ok', 'value' => 'Alle benötigten vorhanden', 'hint' => ''];
    }

    private function twoFactorCheck(): array
    {
        $names = User::query()
            ->where('is_active', true)
            ->whereNull('two_factor_confirmed_at')
            ->orderBy('name')
            ->pluck('name')
            ->all();

        return [
            'label' => 'Zwei-Faktor bei allen Benutzern',
            'state' => $names === [] ? 'ok' : 'warn',
            'value' => $names === [] ? 'Alle aktiven Benutzer' : 'Noch nicht eingerichtet: '.implode(', ', $names),
            'hint' => $names === [] ? '' : 'Wird beim nächsten Login automatisch verlangt.',
        ];
    }

    private function imprintCheck(): array
    {
        $ok = Settings::imprintComplete();

        return [
            'label' => 'Impressum ausgefüllt',
            'state' => $ok ? 'ok' : 'off',
            'value' => $ok ? 'Ja' : 'Angaben fehlen',
            'hint' => $ok ? '' : 'Unter „Einstellungen“ eintragen. Ohne Impressum darf die Webseite nicht öffentlich sein.',
        ];
    }

    private function backupKeyCheck(): array
    {
        $has = Settings::get('backup_public_key') !== null;

        return [
            'label' => 'Schlüssel für Sicherungen',
            'state' => $has ? 'ok' : 'off',
            'value' => $has ? 'Hinterlegt' : 'Fehlt',
            'hint' => $has ? '' : 'Unter „Sicherungen“ einrichten, sonst ist keine Sicherung möglich.',
        ];
    }

    private function backupCheck(): array
    {
        $label = 'Letzte Sicherung auf dem Server';
        $last = BackupService::lastBackupAt();

        $failedMessage = null;
        if (is_file(BackupService::statusFile())) {
            $status = json_decode((string) file_get_contents(BackupService::statusFile()), true);
            if (is_array($status) && ($status['ok'] ?? true) === false) {
                $failedAt = ! empty($status['finished_at']) ? strtotime((string) $status['finished_at']) : false;
                if ($failedAt !== false && ($last === null || $failedAt > $last)) {
                    $failedMessage = (string) ($status['message'] ?? 'unbekannter Fehler');
                }
            }
        }

        if ($failedMessage !== null) {
            return [
                'label' => $label,
                'state' => 'off',
                'value' => $last ? date('d.m.Y H:i', $last).' (danach ein Fehler)' : 'Noch keine erfolgreiche Sicherung',
                'hint' => 'Der letzte Versuch ist fehlgeschlagen: '.$failedMessage,
            ];
        }

        if ($last === null) {
            return ['label' => $label, 'state' => 'warn', 'value' => 'Noch keine Sicherung', 'hint' => 'Unter „Sicherungen“ mit „Jetzt sichern“ erstellen.'];
        }

        $recent = (time() - $last) < 2 * 86400;

        return [
            'label' => $label,
            'state' => $recent ? 'ok' : 'off',
            'value' => date('d.m.Y H:i', $last),
            'hint' => $recent ? '' : 'Die letzte Sicherung ist älter als zwei Tage. Läuft die tägliche Sicherung?',
        ];
    }

    private function backupDownloadCheck(): array
    {
        $label = 'Letzter Download auf den PC';
        $download = BackupService::lastDownloadAt();

        if ($download === null) {
            return ['label' => $label, 'state' => 'warn', 'value' => 'Noch nie', 'hint' => 'Sicherungen unter „Sicherungen“ herunterladen und im OneDrive-Ordner ablegen.'];
        }

        $recent = (time() - $download) < 7 * 86400;

        return [
            'label' => $label,
            'state' => $recent ? 'ok' : 'warn',
            'value' => date('d.m.Y H:i', $download),
            'hint' => $recent ? '' : 'Mehr als sieben Tage her. Bitte eine aktuelle Sicherung herunterladen.',
        ];
    }
}
