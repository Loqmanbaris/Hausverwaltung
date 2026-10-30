<?php

namespace App\Support\Backup;

use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Lädt die verschlüsselten Sicherungen in einen OneDrive-Ordner (Microsoft 365 Business) hoch.
 * Anmeldung als App (Client-Credentials) über die Microsoft Graph API. Das CRM schreibt nur, es liest und löscht dort nichts.
 */
class OneDrive
{
    public const GRAPH = 'https://graph.microsoft.com/v1.0';

    /** Bis zu dieser Größe genügt ein einzelner Upload, darüber wird in Stücken gesendet. */
    public const SMALL_LIMIT = 3 * 1024 * 1024;

    /** Stückgröße: ein Vielfaches von 320 KiB, wie Microsoft es verlangt. */
    public const CHUNK = 16 * 327680;

    public const MAX_FILES_PER_RUN = 3;

    // ------------------------------------------------------------------ Einstellungen

    public static function statusFile(): string
    {
        return storage_path('app/onedrive-status.json');
    }

    /** @return array{tenant: string, client_id: string, secret: string, user: string, folder: string, auto: bool}|null */
    public static function config(): ?array
    {
        $secretEnc = (string) Settings::get('onedrive_secret_enc', '');
        $cfg = [
            'tenant' => trim((string) Settings::get('onedrive_tenant', '')),
            'client_id' => trim((string) Settings::get('onedrive_client_id', '')),
            'secret' => '',
            'user' => trim((string) Settings::get('onedrive_user', '')),
            'folder' => trim((string) Settings::get('onedrive_folder', 'Sicherungen Baris Hausverwaltung')),
            'auto' => (string) Settings::get('onedrive_auto', '1') === '1',
        ];
        if ($secretEnc !== '') {
            try {
                $cfg['secret'] = Crypt::decryptString($secretEnc);
            } catch (\Throwable) {
                $cfg['secret'] = '';
            }
        }

        return self::complete($cfg) ? $cfg : null;
    }

    public static function complete(array $c): bool
    {
        return $c['tenant'] !== '' && $c['client_id'] !== '' && $c['secret'] !== '' && $c['user'] !== '' && $c['folder'] !== '';
    }

    public static function hasSecret(): bool
    {
        return (string) Settings::get('onedrive_secret_enc', '') !== '';
    }

    /** @return array<string, string> Fehler je Feld, leer = in Ordnung */
    public static function validate(array $in, bool $secretRequired): array
    {
        $e = [];
        $tenant = trim((string) ($in['tenant'] ?? ''));
        if (preg_match('/^[0-9a-fA-F-]{36}$/', $tenant) !== 1 && preg_match('/^[A-Za-z0-9][A-Za-z0-9.-]{2,98}\.[A-Za-z]{2,}$/', $tenant) !== 1) {
            $e['tenant'] = 'Die Verzeichnis-ID (Tenant) sieht ungültig aus. Sie besteht aus Buchstaben, Ziffern und Strichen (36 Zeichen) oder ist eine Domain.';
        }
        if (preg_match('/^[0-9a-fA-F-]{36}$/', trim((string) ($in['client_id'] ?? ''))) !== 1) {
            $e['client_id'] = 'Die Anwendungs-ID (Client ID) muss genau 36 Zeichen haben, zum Beispiel 11111111-2222-3333-4444-555555555555.';
        }
        $secret = trim((string) ($in['secret'] ?? ''));
        if ($secret === '' && $secretRequired) {
            $e['secret'] = 'Bitte den geheimen Clientschlüssel (Wert) eintragen.';
        } elseif (strlen($secret) > 400 || preg_match('/\s/', $secret) === 1) {
            $e['secret'] = 'Der geheime Clientschlüssel enthält Leerzeichen oder ist zu lang. Bitte nur den Wert kopieren, nicht die ID.';
        }
        if (filter_var(trim((string) ($in['user'] ?? '')), FILTER_VALIDATE_EMAIL) === false) {
            $e['user'] = 'Bitte die E-Mail-Adresse des OneDrive-Besitzers eintragen.';
        }
        $folder = trim((string) ($in['folder'] ?? ''));
        if ($folder === '' || strlen($folder) > 120 || str_contains($folder, '..') || str_starts_with($folder, '/') || preg_match('/["*:<>?\\\\|]/', $folder) === 1 || preg_match('/[\x00-\x1f]/', $folder) === 1) {
            $e['folder'] = 'Der Ordnername ist ungültig. Erlaubt sind Buchstaben, Ziffern, Leerzeichen, Strich und Schrägstrich für Unterordner, nicht aber : * ? " < > \\ |.';
        }

        return $e;
    }

    /** Speichert die Einstellungen. Ein leeres Geheimnis lässt das gespeicherte unverändert. */
    public static function save(array $in): void
    {
        $values = [
            'onedrive_tenant' => trim((string) $in['tenant']),
            'onedrive_client_id' => trim((string) $in['client_id']),
            'onedrive_user' => trim((string) $in['user']),
            'onedrive_folder' => trim((string) $in['folder']),
            'onedrive_auto' => ! empty($in['auto']) ? '1' : '0',
        ];
        $secret = trim((string) ($in['secret'] ?? ''));
        if ($secret !== '') {
            $values['onedrive_secret_enc'] = Crypt::encryptString($secret);
        }
        Settings::putMany($values);
    }

    // ------------------------------------------------------------------ Microsoft Graph

    /** Holt ein Zugriffstoken. */
    public static function token(array $cfg): string
    {
        try {
            $res = Http::asForm()->timeout(30)->connectTimeout(10)->post('https://login.microsoftonline.com/'.rawurlencode($cfg['tenant']).'/oauth2/v2.0/token', [
                'client_id' => $cfg['client_id'], 'client_secret' => $cfg['secret'],
                'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials',
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('Der Server erreicht Microsoft nicht (Anmeldung). Bitte später noch einmal versuchen.');
        }
        if (! $res->successful() || (string) $res->json('access_token') === '') {
            throw new RuntimeException('Die Anmeldung bei Microsoft ist fehlgeschlagen: '.self::reason($res->json('error_description') ?? $res->json('error') ?? 'Code '.$res->status()));
        }

        return (string) $res->json('access_token');
    }

    /** Kürzt eine Microsoft-Fehlermeldung auf den verständlichen ersten Satz. */
    public static function reason(mixed $text): string
    {
        $t = trim((string) (is_array($text) ? json_encode($text, JSON_UNESCAPED_UNICODE) : $text));
        $t = trim(explode("\r\n", explode("\n", $t)[0])[0]);

        return mb_substr(preg_replace('/\s+/', ' ', $t) ?? $t, 0, 220);
    }

    /** Adresse der Datei im Ordner, Pfadteile einzeln kodiert. */
    public static function remoteUrl(array $cfg, string $name, string $suffix): string
    {
        $segments = array_filter(explode('/', trim($cfg['folder'], '/')), fn ($s) => $s !== '');
        $segments[] = $name;
        $path = implode('/', array_map('rawurlencode', $segments));

        return self::GRAPH.'/users/'.rawurlencode($cfg['user']).'/drive/root:/'.$path.':/'.$suffix;
    }

    /**
     * Stücke für große Dateien.
     *
     * @return array<int, array{start: int, end: int, header: string}>
     */
    public static function chunkPlan(int $size, int $chunk = self::CHUNK): array
    {
        $plan = [];
        for ($start = 0; $start < $size; $start += $chunk) {
            $end = min($start + $chunk, $size) - 1;
            $plan[] = ['start' => $start, 'end' => $end, 'header' => 'bytes '.$start.'-'.$end.'/'.$size];
        }

        return $plan;
    }

    /** Lädt eine Datei hoch. Gibt die Adresse der Datei bei OneDrive zurück (falls vorhanden). */
    public static function putFile(array $cfg, string $localPath, string $remoteName): ?string
    {
        if (! is_file($localPath) || ! is_readable($localPath)) {
            throw new RuntimeException('Die Sicherungsdatei fehlt oder ist nicht lesbar.');
        }
        $size = (int) filesize($localPath);
        if ($size <= 0) {
            throw new RuntimeException('Die Sicherungsdatei ist leer.');
        }
        $token = self::token($cfg);

        try {
            if ($size <= self::SMALL_LIMIT) {
                $res = Http::withToken($token)->timeout(120)->withBody((string) file_get_contents($localPath), 'application/octet-stream')
                    ->put(self::remoteUrl($cfg, $remoteName, 'content').'?@microsoft.graph.conflictBehavior=rename');
                if (! $res->successful()) {
                    throw new RuntimeException('OneDrive hat den Upload abgelehnt: '.self::graphError($res));
                }

                return (string) $res->json('webUrl');
            }

            $session = Http::withToken($token)->timeout(60)->post(self::remoteUrl($cfg, $remoteName, 'createUploadSession'), [
                'item' => ['@microsoft.graph.conflictBehavior' => 'rename', 'name' => $remoteName],
            ]);
            if (! $session->successful() || (string) $session->json('uploadUrl') === '') {
                throw new RuntimeException('OneDrive hat den Upload nicht gestartet: '.self::graphError($session));
            }
            $uploadUrl = (string) $session->json('uploadUrl');
            $fh = fopen($localPath, 'rb');
            if ($fh === false) {
                throw new RuntimeException('Die Sicherungsdatei lässt sich nicht öffnen.');
            }
            $web = null;
            try {
                foreach (self::chunkPlan($size) as $c) {
                    $data = (string) fread($fh, $c['end'] - $c['start'] + 1);
                    $res = null;
                    for ($try = 1; $try <= 3; $try++) {
                        // Die Upload-Adresse enthält ihre Berechtigung selbst. Das Token wird hier bewusst nicht mitgeschickt.
                        $res = Http::timeout(180)->withHeaders(['Content-Range' => $c['header']])->withBody($data, 'application/octet-stream')->put($uploadUrl);
                        if ($res->successful() || $res->status() < 500) {
                            break;
                        }
                        sleep($try * 2);
                    }
                    if (! $res->successful()) {
                        throw new RuntimeException('OneDrive hat ein Teilstück abgelehnt: '.self::graphError($res));
                    }
                    $web = (string) $res->json('webUrl') ?: $web;
                }
            } finally {
                fclose($fh);
            }

            return $web;
        } catch (ConnectionException) {
            throw new RuntimeException('Die Verbindung zu OneDrive wurde unterbrochen. Beim nächsten Lauf wird es erneut versucht.');
        }
    }

    private static function graphError($res): string
    {
        $msg = $res->json('error.message') ?? $res->json('error') ?? null;

        return self::reason($msg ?? 'Code '.$res->status()).' (Code '.$res->status().')';
    }

    // ------------------------------------------------------------------ Status

    /** @return array{uploaded: array<string, string>, last: ?array} */
    public static function status(): array
    {
        $raw = @file_get_contents(self::statusFile());
        $d = $raw ? json_decode($raw, true) : null;

        return ['uploaded' => is_array($d['uploaded'] ?? null) ? $d['uploaded'] : [], 'last' => is_array($d['last'] ?? null) ? $d['last'] : null];
    }

    private static function writeStatus(array $status): void
    {
        @file_put_contents(self::statusFile(), json_encode($status, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        @chmod(self::statusFile(), 0600);
    }

    private static function remember(string $name, bool $ok, string $message, ?int $size = null): void
    {
        $s = self::status();
        if ($ok) {
            $s['uploaded'][$name] = date('c');
        }
        $s['last'] = ['ok' => $ok, 'at' => date('c'), 'file' => $name, 'message' => $message, 'size' => $size];
        self::writeStatus($s);
    }

    // ------------------------------------------------------------------ Hochladen

    /** Sicherungen auf dem Server, die noch nicht bei OneDrive liegen, neueste zuerst. */
    public static function pending(?string $dir = null): array
    {
        $done = self::status()['uploaded'];

        return array_values(array_filter(BackupService::list($dir), fn ($b) => ! isset($done[$b['name']])));
    }

    /**
     * Lädt noch fehlende Sicherungen hoch (höchstens $max pro Lauf). Fehler stoppen den Lauf, werden gespeichert und weitergegeben.
     *
     * @return array<int, string> hochgeladene Dateien
     */
    public static function syncPending(int $max = self::MAX_FILES_PER_RUN, ?string $dir = null): array
    {
        $cfg = self::config();
        if ($cfg === null) {
            throw new RuntimeException('OneDrive ist noch nicht eingerichtet.');
        }
        $dir ??= BackupService::directory();
        $sent = [];
        foreach (array_slice(self::pending($dir), 0, $max) as $b) {
            try {
                self::putFile($cfg, rtrim($dir, '/').'/'.$b['name'], $b['name']);
            } catch (RuntimeException $e) {
                self::remember($b['name'], false, $e->getMessage(), $b['size']);
                Audit::log('backup.onedrive_failed', 'OneDrive-Upload fehlgeschlagen: '.$b['name'].' – '.$e->getMessage());
                throw $e;
            }
            self::remember($b['name'], true, 'Hochgeladen', $b['size']);
            Audit::log('backup.onedrive', 'Sicherung bei OneDrive abgelegt: '.$b['name'].' ('.BackupService::formatSize($b['size']).')');
            $sent[] = $b['name'];
        }

        return $sent;
    }

    /** Kleine Textdatei zum Testen der Verbindung. */
    public static function test(): string
    {
        $cfg = self::config();
        if ($cfg === null) {
            throw new RuntimeException('Bitte zuerst alle Angaben speichern.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'odtest');
        file_put_contents($tmp, 'Verbindungstest des Immobilien-CRM am '.date('d.m.Y H:i')." Uhr.\nDiese Datei kannst du löschen.\n");
        try {
            $name = 'Verbindungstest '.date('Y-m-d H-i').'.txt';
            self::putFile($cfg, $tmp, $name);
        } finally {
            @unlink($tmp);
        }
        self::remember('Verbindungstest', true, 'Verbindung getestet');

        return $name;
    }
}
