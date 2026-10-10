<?php

namespace App\Support\Updates;

use App\Models\SystemUpdate;
use App\Models\User;
use App\Support\Audit;
use App\Support\Backup\BackupService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Spielt ein geprüftes Update-Paket ein und nimmt es bei Bedarf zurück.
 * Ablauf: prüfen, verschlüsselte Datenbanksicherung, alte Dateien sichern, neue Dateien schreiben, Tabellen anlegen,
 * Zwischenspeicher leeren. Der Selbsttest läuft danach in einer eigenen Anfrage, damit schon der neue Programmcode geprüft wird.
 */
class Updater
{
    public static function root(): string
    {
        return base_path();
    }

    public static function storageDir(): string
    {
        return storage_path('app/updates');
    }

    /**
     * @return array{changed: string[], new: string[], same: string[], conflicts: string[], missing: string[], unwritable: string[], installed: bool}
     */
    public static function check(UpdatePackage $p): array
    {
        $root = self::root();
        $out = ['changed' => [], 'new' => [], 'same' => [], 'conflicts' => [], 'missing' => [], 'unwritable' => [], 'installed' => false];

        foreach ($p->requiresFiles as $r) {
            if (! is_file($root.'/'.$r)) {
                $out['missing'][] = $r;
            }
        }

        foreach ($p->files as $f) {
            $abs = $root.'/'.$f['path'];
            if (is_file($abs)) {
                $cur = hash_file('sha256', $abs);
                if ($cur === $f['sha256']) {
                    $out['same'][] = $f['path'];

                    continue;
                }
                if ($f['old_sha256'] === null || $cur !== $f['old_sha256']) {
                    $out['conflicts'][] = $f['path'];

                    continue;
                }
                $out['changed'][] = $f['path'];
                if (! is_writable($abs)) {
                    $out['unwritable'][] = $f['path'];
                }
            } else {
                if ($f['old_sha256'] !== null) {
                    $out['conflicts'][] = $f['path'];

                    continue;
                }
                $out['new'][] = $f['path'];
                $dir = dirname($abs);
                while (! is_dir($dir) && $dir !== $root) {
                    $dir = dirname($dir);
                }
                if (! is_writable($dir)) {
                    $out['unwritable'][] = $f['path'];
                }
            }
        }

        $out['installed'] = $out['changed'] === [] && $out['new'] === [] && $out['conflicts'] === [];

        return $out;
    }

    /** @param array $check Ergebnis von check() */
    public static function problems(array $check): array
    {
        $problems = [];
        if ($check['missing'] !== []) {
            $problems[] = 'Ein früheres Paket fehlt noch (nicht gefunden: '.implode(', ', $check['missing']).'). Bitte die Pakete in der richtigen Reihenfolge einspielen.';
        }
        if ($check['conflicts'] !== []) {
            $problems[] = 'Diese Dateien wurden seit der Erstellung des Pakets verändert: '.implode(', ', $check['conflicts']).'. Das Paket wird nicht eingespielt, damit nichts überschrieben wird.';
        }
        if ($check['unwritable'] !== []) {
            $problems[] = 'Diese Dateien darf das System nicht schreiben: '.implode(', ', $check['unwritable']).'.';
        }
        if ($check['installed']) {
            $problems[] = 'Dieses Paket ist schon eingespielt.';
        }

        return $problems;
    }

    /** @throws RuntimeException */
    public static function install(UpdatePackage $p, User $user): SystemUpdate
    {
        @set_time_limit(300);
        ignore_user_abort(true);

        $check = self::check($p);
        $problems = self::problems($check);
        if ($problems !== []) {
            throw new RuntimeException(implode(' ', $problems));
        }

        $log = [];

        // 1. Datenbanksicherung
        try {
            $b = (new BackupService)->run();
            $log[] = 'Sicherung erstellt: '.$b['file'];
        } catch (Throwable $e) {
            throw new RuntimeException('Die Datenbanksicherung hat nicht geklappt, deshalb wurde nichts eingespielt: '.$e->getMessage());
        }

        // 2. Alte Dateien sichern
        $backup = self::storageDir().'/backup-'.$p->name.'-'.date('Ymd-His');
        foreach ($check['changed'] as $path) {
            self::writeFile($backup.'/files/'.$path, (string) file_get_contents(self::root().'/'.$path));
        }
        self::writeFile($backup.'/new-files.json', (string) json_encode($check['new']));
        $log[] = 'Alte Dateien gesichert ('.count($check['changed']).')';

        $update = SystemUpdate::create([
            'name' => $p->name,
            'title' => $p->title,
            'package_sha256' => $p->sha256,
            'status' => 'fehlgeschlagen',
            'backup_dir' => substr($backup, strlen(storage_path()) + 1),
            'files_changed' => $check['changed'],
            'files_new' => $check['new'],
            'migrations' => $p->migrations,
            'selftest' => $p->selftest,
            'installed_by' => $user->id,
            'installed_at' => now(),
        ]);

        // 3. Dateien schreiben und Tabellen anlegen
        try {
            foreach ($p->files as $f) {
                if (in_array($f['path'], $check['same'], true)) {
                    continue;
                }
                self::writeFile(self::root().'/'.$f['path'], $f['content']);
            }
            $log[] = 'Dateien geschrieben ('.(count($check['changed']) + count($check['new'])).')';

            if ($p->migrations !== []) {
                $code = Artisan::call('migrate', ['--force' => true, '--path' => $p->migrations]);
                $log[] = trim(Artisan::output());
                if ($code !== 0) {
                    throw new RuntimeException('Die Tabellen konnten nicht angelegt werden.');
                }
            }
        } catch (Throwable $e) {
            $log[] = 'FEHLER: '.$e->getMessage();
            self::restoreFiles($update);
            self::rollbackMigrations($p->migrations, $log);
            self::clearCaches();
            $update->update(['output' => implode("\n", $log)]);
            Audit::log('update.failed', $update, 'Update fehlgeschlagen: '.$p->title.' ('.$e->getMessage().')');

            throw new RuntimeException('Das Einspielen ist fehlgeschlagen, der alte Stand wurde wiederhergestellt: '.$e->getMessage());
        }

        self::clearCaches();
        $update->update(['status' => 'installiert', 'output' => implode("\n", $log)]);
        Audit::log('update.installed', $update, 'Update eingespielt: '.$p->title);

        return $update;
    }

    /** Selbsttest des Pakets (läuft in einer neuen Anfrage mit dem neuen Programmcode). */
    public static function selftest(SystemUpdate $update): array
    {
        $command = $update->selftest;
        if ($command === null) {
            return ['ok' => true, 'output' => 'Kein Selbsttest im Paket.'];
        }
        try {
            $code = Artisan::call($command);
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            $code = 1;
            $output = 'Selbsttest konnte nicht laufen: '.$e->getMessage();
        }
        $ok = $code === 0;
        $update->update([
            'status' => $ok ? 'installiert' : 'test_fehler',
            'output' => trim((string) $update->output."\n\n".$output),
        ]);

        return ['ok' => $ok, 'output' => $output];
    }

    public static function rollback(SystemUpdate $update): void
    {
        @set_time_limit(300);
        $log = [];
        self::rollbackMigrations(array_reverse((array) $update->migrations), $log);
        self::restoreFiles($update);
        self::clearCaches();
        $update->update([
            'status' => 'zurueckgenommen',
            'rolled_back_at' => now(),
            'output' => trim((string) $update->output."\n\nZurückgenommen:\n".implode("\n", $log)),
        ]);
        Audit::log('update.rolled_back', $update, 'Update zurückgenommen: '.$update->title);
    }

    private static function restoreFiles(SystemUpdate $update): void
    {
        $backup = storage_path((string) $update->backup_dir);
        foreach ((array) $update->files_changed as $path) {
            $src = $backup.'/files/'.$path;
            if (is_file($src)) {
                self::writeFile(self::root().'/'.$path, (string) file_get_contents($src));
            }
        }
        foreach ((array) $update->files_new as $path) {
            UpdatePackage::assertPath($path);
            $abs = self::root().'/'.$path;
            if (is_file($abs)) {
                @unlink($abs);
                self::invalidate($abs);
            }
            // Leer gewordene Ordner, die das Update angelegt hat, wieder entfernen
            $dir = dirname($abs);
            while ($dir !== self::root() && str_starts_with($dir, self::root().'/') && @rmdir($dir)) {
                $dir = dirname($dir);
            }
        }
    }

    private static function rollbackMigrations(array $migrations, array &$log): void
    {
        foreach ($migrations as $m) {
            $name = basename($m, '.php');
            if (! DB::table('migrations')->where('migration', $name)->exists()) {
                continue;
            }
            try {
                Artisan::call('migrate:rollback', ['--force' => true, '--path' => [$m]]);
                $log[] = trim(Artisan::output());
            } catch (Throwable $e) {
                $log[] = 'Tabellen von '.$name.' konnten nicht entfernt werden: '.$e->getMessage();
            }
        }
    }

    public static function clearCaches(): void
    {
        foreach (['view:clear', 'route:clear', 'config:clear'] as $cmd) {
            try {
                Artisan::call($cmd);
            } catch (Throwable) {
            }
        }
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /** Schreibt erst in eine Hilfsdatei und benennt sie dann um, damit nie eine halbe Datei entsteht. */
    private static function writeFile(string $abs, string $content): void
    {
        $dir = dirname($abs);
        if (! is_dir($dir) && ! @mkdir($dir, 0705, true) && ! is_dir($dir)) {
            throw new RuntimeException('Ordner kann nicht angelegt werden: '.$dir);
        }
        $tmp = $abs.'.tmp-'.bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, $content) !== strlen($content)) {
            @unlink($tmp);
            throw new RuntimeException('Datei kann nicht geschrieben werden: '.$abs);
        }
        @chmod($tmp, is_file($abs) ? (fileperms($abs) & 0777) : 0644);
        if (! @rename($tmp, $abs)) {
            @unlink($tmp);
            throw new RuntimeException('Datei kann nicht ersetzt werden: '.$abs);
        }
        self::invalidate($abs);
    }

    private static function invalidate(string $abs): void
    {
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($abs, true);
        }
    }
}
