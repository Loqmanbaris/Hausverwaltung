<?php

namespace App\Support\Backup;

use App\Models\AuditLog;
use App\Support\Audit;
use App\Support\Settings;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Erstellt verschlüsselte Sicherungen unter storage/app/backups und räumt alte weg.
 *
 * Aufbewahrung auf dem Server: alle Sicherungen der letzten 30 Tage und die erste Sicherung
 * jedes Monats für ein Jahr.
 */
class BackupService
{
    /** Flüchtige Tabellen, die nicht gesichert werden. */
    public const SKIPPED_TABLES = ['cache', 'cache_locks', 'sessions'];

    public const FILE_PATTERN = '/^sicherung-(\d{4})-(\d{2})-(\d{2})-(\d{4})\.bkp$/';

    public const KEEP_DAYS = 30;

    public const KEEP_MONTHS = 12;

    public static function directory(): string
    {
        return storage_path('app/backups');
    }

    public static function statusFile(): string
    {
        return storage_path('app/backup-status.json');
    }

    /**
     * @param  array{public_key?: string, target_dir?: string, write_status?: bool}  $options
     * @return array{file: string, size: int, tables: int, rows: int}
     */
    public function run(array $options = []): array
    {
        $writeStatus = $options['write_status'] ?? true;

        try {
            $publicKey = $options['public_key'] ?? Settings::get('backup_public_key');
            if ($publicKey === null || trim($publicKey) === '') {
                throw new RuntimeException('Es ist noch kein öffentlicher Schlüssel hinterlegt. Bitte zuerst unter „Sicherungen“ eintragen.');
            }

            $dir = $options['target_dir'] ?? self::directory();
            self::ensureDirectory($dir);

            $stamp = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d-Hi');
            $work = $dir.'/tmp-'.bin2hex(random_bytes(4));
            if (! mkdir($work, 0700, true) && ! is_dir($work)) {
                throw new RuntimeException('Der Arbeitsordner konnte nicht angelegt werden.');
            }

            try {
                $sql = $work.'/datenbank.sql';
                $stats = DatabaseDumper::dump(DB::connection()->getPdo(), $sql, self::SKIPPED_TABLES);

                $archive = Archiver::create($work.'/paket', $sql, base_path(), self::readme($stamp, $stats));
                $extension = str_ends_with($archive, '.tar.gz') ? 'tar.gz' : 'zip';

                $final = $dir.'/sicherung-'.$stamp.'.bkp';
                Crypto::encryptFile($archive, $final.'.part', $publicKey, $extension);
                if (! rename($final.'.part', $final)) {
                    throw new RuntimeException('Die fertige Sicherung konnte nicht abgelegt werden.');
                }
                @chmod($final, 0600);
            } finally {
                self::deleteDirectory($work);
            }

            $result = ['file' => basename($final), 'size' => (int) filesize($final)] + $stats;

            if ($writeStatus) {
                self::writeStatus([
                    'ok' => true,
                    'finished_at' => date('c'),
                    'file' => $result['file'],
                    'size' => $result['size'],
                    'message' => 'Sicherung erstellt',
                ]);
                self::audit('backup.created', 'Sicherung erstellt: '.$result['file'].' ('.self::formatSize($result['size']).')');
            }

            return $result;
        } catch (Throwable $e) {
            if ($writeStatus) {
                self::writeStatus([
                    'ok' => false,
                    'finished_at' => date('c'),
                    'message' => $e->getMessage(),
                ]);
                self::audit('backup.failed', 'Sicherung fehlgeschlagen: '.$e->getMessage());
            }

            throw $e;
        }
    }

    /**
     * Löscht Sicherungen, die nach den Regeln nicht mehr gebraucht werden.
     *
     * @return string[] Namen der gelöschten Dateien
     */
    public static function prune(string $dir, ?DateTimeInterface $now = null, bool $dryRun = false): array
    {
        $now = $now ? DateTimeImmutable::createFromInterface($now) : new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin'));
        $files = self::files($dir);

        $dailyFrom = $now->modify('-'.self::KEEP_DAYS.' days')->setTime(0, 0);
        $monthlyFrom = $now->modify('first day of this month')->modify('-'.self::KEEP_MONTHS.' months')->setTime(0, 0);

        // Erste Sicherung jedes Monats
        $firstOfMonth = [];
        foreach ($files as $name => $date) {
            $key = $date->format('Y-m');
            if (! isset($firstOfMonth[$key]) || $name < $firstOfMonth[$key]) {
                $firstOfMonth[$key] = $name;
            }
        }

        $deleted = [];
        foreach ($files as $name => $date) {
            $keep = $date >= $dailyFrom
                || ($firstOfMonth[$date->format('Y-m')] === $name && $date >= $monthlyFrom);

            if (! $keep) {
                $deleted[] = $name;
                if (! $dryRun) {
                    @unlink(rtrim($dir, '/').'/'.$name);
                }
            }
        }

        return $deleted;
    }

    /**
     * Vorhandene Sicherungen, neueste zuerst.
     *
     * @return array<int, array{name: string, size: int, date: DateTimeImmutable}>
     */
    public static function list(?string $dir = null): array
    {
        $dir ??= self::directory();
        $list = [];
        foreach (self::files($dir) as $name => $date) {
            $list[] = ['name' => $name, 'size' => (int) @filesize(rtrim($dir, '/').'/'.$name), 'date' => $date];
        }
        usort($list, fn ($a, $b) => strcmp($b['name'], $a['name']));

        return $list;
    }

    /** @return array<string, DateTimeImmutable> Dateiname => Zeitpunkt aus dem Dateinamen */
    public static function files(string $dir): array
    {
        $result = [];
        foreach (glob(rtrim($dir, '/').'/sicherung-*.bkp') ?: [] as $path) {
            $name = basename($path);
            if (preg_match(self::FILE_PATTERN, $name, $m)) {
                $result[$name] = DateTimeImmutable::createFromFormat('!Y-m-d-Hi', $m[1].'-'.$m[2].'-'.$m[3].'-'.$m[4], new DateTimeZone('Europe/Berlin'));
            }
        }
        ksort($result);

        return $result;
    }

    public static function formatSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1, ',', '.').' MB';
        }

        return number_format(max($bytes, 0) / 1024, 0, ',', '.').' KB';
    }

    /** Zeitpunkt der neuesten Sicherung auf dem Server (Unix-Zeit) oder null. */
    public static function lastBackupAt(?string $dir = null): ?int
    {
        $files = self::files($dir ?? self::directory());

        return $files === [] ? null : $files[array_key_last($files)]->getTimestamp();
    }

    /** Zeitpunkt des letzten Downloads einer Sicherung (aus dem Protokoll) oder null. */
    public static function lastDownloadAt(): ?int
    {
        $log = AuditLog::query()->where('event', 'backup.downloaded')->orderByDesc('id')->first();

        return $log?->created_at?->getTimestamp();
    }

    /** Kurzer Hinweistext, wenn bei der Sicherung etwas zu tun ist, sonst null. */
    public static function attention(): ?string
    {
        if (Settings::get('backup_public_key') === null) {
            return 'Für die Sicherung fehlt noch der Schlüssel.';
        }

        $last = self::lastBackupAt();
        if ($last === null) {
            return 'Es gibt noch keine Sicherung.';
        }
        if (time() - $last > 2 * 86400) {
            return 'Die letzte Sicherung ist älter als zwei Tage.';
        }

        $download = self::lastDownloadAt();
        if ($download === null) {
            return 'Keine Sicherung wurde bisher auf den PC heruntergeladen.';
        }
        if (time() - $download > 7 * 86400) {
            return 'Der letzte Download einer Sicherung ist mehr als sieben Tage her.';
        }

        return null;
    }

    /** Kurzer Fingerabdruck des öffentlichen Schlüssels zum Vergleichen mit dem Werkzeug. */
    public static function keyFingerprint(?string $publicKeyPem): ?string
    {
        if ($publicKeyPem === null || trim($publicKeyPem) === '') {
            return null;
        }

        $der = base64_decode(preg_replace('/-----[A-Z0-9 ]+-----|\s+/', '', $publicKeyPem), true);
        if ($der === false || $der === '') {
            return null;
        }

        return implode(' ', str_split(strtoupper(substr(hash('sha256', $der), 0, 32)), 4));
    }

    private static function ensureDirectory(string $dir): void
    {
        if (! is_dir($dir) && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
            throw new RuntimeException('Der Ordner für Sicherungen konnte nicht angelegt werden.');
        }
        if (! is_writable($dir)) {
            throw new RuntimeException('Der Ordner für Sicherungen ist nicht beschreibbar.');
        }
    }

    private static function writeStatus(array $data): void
    {
        @file_put_contents(self::statusFile(), json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    private static function audit(string $event, string $description): void
    {
        try {
            Audit::log($event, $description, null, null, null, 'System');
        } catch (Throwable) {
            // Das Protokoll darf die Sicherung nicht verhindern.
        }
    }

    private static function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) && ! is_link($path) ? self::deleteDirectory($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private static function readme(string $stamp, array $stats): string
    {
        return <<<TXT
        SICHERUNG VOM {$stamp}
        ======================

        Inhalt dieses Archivs:
          datenbank.sql   Alle Tabellen und Daten ({$stats['tables']} Tabellen, {$stats['rows']} Zeilen)
          dateien/        Programmdateien, Einstellungen (.env) und hochgeladene Dateien
                          (ohne den Ordner vendor, der sich neu installieren lässt)
          LIESMICH.txt    Diese Datei

        WICHTIG: Die Datei dateien/.env enthält den Schlüssel (APP_KEY), ohne den die
        verschlüsselten Felder (Steuernummern, IBAN, Zwei-Faktor-Codes) nicht mehr lesbar sind.
        Diese Sicherung deshalb nur verschlüsselt aufbewahren und nicht weitergeben.

        Wiederherstellung in Kurzform:
          1. Datenbank: datenbank.sql in eine leere Datenbank einspielen.
          2. Dateien: Inhalt von dateien/ auf den Server legen.
          3. Mit "composer install" den Ordner vendor neu erzeugen.
          4. Die Zugangsdaten der Datenbank in .env prüfen.
        Genaue Schritte stehen in der Anleitung zur Wiederherstellung.
        TXT;
    }
}
