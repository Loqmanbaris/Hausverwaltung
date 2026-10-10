<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Backup\BackupService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Sicherungen verwalten. Nur für Administratoren (über die Route geschützt).
 */
class BackupController extends Controller
{
    public function index()
    {
        $publicKey = Settings::get('backup_public_key');
        $status = null;
        if (is_file(BackupService::statusFile())) {
            $decoded = json_decode((string) file_get_contents(BackupService::statusFile()), true);
            $status = is_array($decoded) ? $decoded : null;
        }

        return view('backups.index', [
            'hasKey' => $publicKey !== null,
            'fingerprint' => BackupService::keyFingerprint($publicKey),
            'backups' => BackupService::list(),
            'status' => $status,
            'lastDownload' => BackupService::lastDownloadAt(),
            'attention' => BackupService::attention(),
        ]);
    }

    public function run()
    {
        @set_time_limit(300);

        try {
            $result = app(BackupService::class)->run();
            BackupService::prune(BackupService::directory());
        } catch (Throwable $e) {
            return redirect()->route('backups.index')->withErrors(['backup' => 'Die Sicherung ist fehlgeschlagen: '.$e->getMessage()]);
        }

        return redirect()->route('backups.index')->with('status', 'Sicherung erstellt: '.$result['file'].' ('.BackupService::formatSize($result['size']).').');
    }

    public function saveKey(Request $request)
    {
        $request->validate(['public_key' => ['required', 'string', 'max:4000']], [
            'required' => 'Bitte den öffentlichen Schlüssel einfügen.',
            'max' => 'Der Text ist zu lang. Bitte nur den öffentlichen Schlüssel einfügen.',
        ], ['public_key' => 'Öffentlicher Schlüssel']);

        $pem = trim((string) $request->input('public_key'));
        $key = @openssl_pkey_get_public($pem);
        $details = $key ? openssl_pkey_get_details($key) : null;

        if (! $details || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA || ($details['bits'] ?? 0) < 3072) {
            throw ValidationException::withMessages([
                'public_key' => 'Das ist kein gültiger öffentlicher Schlüssel. Bitte den Text aus dem Sicherungs-Werkzeug einfügen, mit den Zeilen BEGIN und END.',
            ]);
        }

        $changed = Settings::get('backup_public_key') !== null;
        Settings::putMany(['backup_public_key' => $pem."\n"]);

        Audit::log(
            'backup.key_set',
            'Öffentlicher Schlüssel für Sicherungen '.($changed ? 'geändert' : 'hinterlegt').' (Fingerabdruck '.BackupService::keyFingerprint($pem).')'
        );

        return redirect()->route('backups.index')->with('status', 'Der Schlüssel wurde gespeichert. Neue Sicherungen werden damit verschlüsselt.');
    }

    public function download(string $file)
    {
        abort_unless(preg_match(BackupService::FILE_PATTERN, $file) === 1, 404);

        $path = BackupService::directory().'/'.$file;
        abort_unless(is_file($path), 404);

        Audit::log('backup.downloaded', 'Sicherung heruntergeladen: '.$file);

        return response()->download($path, $file, ['Content-Type' => 'application/octet-stream']);
    }

    public function tool()
    {
        return response()->download(
            resource_path('backup-tool/sicherungs-werkzeug.html'),
            'sicherungs-werkzeug.html',
            ['Content-Type' => 'text/html; charset=utf-8']
        );
    }
}
