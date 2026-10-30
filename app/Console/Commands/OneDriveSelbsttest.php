<?php

namespace App\Console\Commands;

use App\Support\Backup\OneDrive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 40 (Sicherung bei OneDrive). Nutzt eine simulierte Microsoft-Schnittstelle, es wird nichts gesendet. */
class OneDriveSelbsttest extends Command
{
    protected $signature = 'onedrive:selbsttest';

    protected $description = 'Selbsttest OneDrive-Upload der Sicherung (Paket 40)';

    public function handle(): int
    {
        $cfg = ['tenant' => '11111111-2222-3333-4444-555555555555', 'client_id' => '66666666-7777-8888-9999-000000000000', 'secret' => 'geheim', 'user' => 'test@example.de', 'folder' => 'Sicherungen Baris/Haus', 'auto' => true];
        $plan = OneDrive::chunkPlan(12 * 1024 * 1024);
        $okPlan = count($plan) === 3 && $plan[0]['header'] === 'bytes 0-5242879/12582912' && $plan[2]['header'] === 'bytes 10485760-12582911/12582912' && OneDrive::CHUNK % 327680 === 0;

        // Kleine Datei: ein Upload. Große Datei: Upload-Sitzung mit Teilstücken, ohne Token bei den Teilstücken.
        $sentSmall = false;
        $tokenInChunk = false;
        $chunks = 0;
        Http::fake(function ($req) use (&$sentSmall, &$tokenInChunk, &$chunks) {
            $u = $req->url();
            if (str_contains($u, 'login.microsoftonline.com')) {
                return Http::response(['access_token' => 'tok', 'expires_in' => 3600], 200);
            }
            if (str_contains($u, ':/content')) {
                $sentSmall = true;

                return Http::response(['webUrl' => 'https://onedrive.example/klein'], 201);
            }
            if (str_contains($u, 'createUploadSession')) {
                return Http::response(['uploadUrl' => 'https://upload.example/session/abc'], 200);
            }
            if (str_contains($u, 'upload.example')) {
                $chunks++;
                $tokenInChunk = $tokenInChunk || $req->hasHeader('Authorization');

                return $chunks < 3 ? Http::response(['nextExpectedRanges' => ['x']], 202) : Http::response(['webUrl' => 'https://onedrive.example/gross'], 201);
            }

            return Http::response('', 404);
        });
        $small = tempnam(sys_get_temp_dir(), 'odS');
        file_put_contents($small, str_repeat('a', 1000));
        $big = tempnam(sys_get_temp_dir(), 'odB');
        $fh = fopen($big, 'wb');
        ftruncate($fh, 12 * 1024 * 1024);
        fclose($fh);
        $r1 = $r2 = null;
        $fail = null;
        try {
            $r1 = OneDrive::putFile($cfg, $small, 'sicherung-test-klein.bkp');
            $r2 = OneDrive::putFile($cfg, $big, 'sicherung-test-gross.bkp');
        } catch (\Throwable $e) {
            $fail = $e->getMessage();
        } finally {
            @unlink($small);
            @unlink($big);
        }
        $url = OneDrive::remoteUrl($cfg, 'a b.bkp', 'content');
        $bad = OneDrive::validate(['tenant' => 'x', 'client_id' => 'y', 'secret' => '', 'user' => 'kein-mail', 'folder' => '../x'], true);
        $good = OneDrive::validate($cfg, true);

        $checks = [
            'Stücke für große Dateien' => $okPlan,
            'Kleine Datei in einem Schritt' => $fail === null && $sentSmall && $r1 === 'https://onedrive.example/klein',
            'Große Datei in Teilstücken' => $fail === null && $chunks === 3 && $r2 === 'https://onedrive.example/gross',
            'Kein Token bei den Teilstücken' => ! $tokenInChunk,
            'Adresse mit Sonderzeichen' => str_contains($url, '/users/test%40example.de/drive/root:/Sicherungen%20Baris/Haus/a%20b.bkp:/content'),
            'Falsche Angaben abweisen' => count($bad) === 5 && $good === [],
            'Seiten' => Route::has('backups.onedrive.save') && Route::has('backups.onedrive.test'),
            'Ansicht' => view()->exists('backups.onedrive'),
        ];
        $this->line('Selbsttest Paket 40 (Sicherung bei OneDrive)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.'.($fail ? ' ('.$fail.')' : ''));

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
