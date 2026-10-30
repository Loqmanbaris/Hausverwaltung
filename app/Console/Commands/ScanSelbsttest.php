<?php

namespace App\Console\Commands;

use App\Http\Controllers\ScanAssetController;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 47 (Beleg-Scanner: Zuschneiden, PDF, Texterkennung). */
class ScanSelbsttest extends Command
{
    protected $signature = 'scan:selbsttest';

    protected $description = 'Selbsttest Beleg-Scanner (Paket 47)';

    public function handle(): int
    {
        $c = new ScanAssetController();
        $files = ScanAssetController::names();
        $missing = array_values(array_filter($files, fn ($n) => ! is_file(ScanAssetController::path($n))));
        $gz = $c->show('deu.traineddata.gz')->getContent();
        $core = $c->show('tesseract-core-simd-lstm.wasm.js');
        $script = (string) @file_get_contents(ScanAssetController::path('scanner.js'));
        $unknown = false;
        try {
            $c->show('..%2F.env');
        } catch (\Throwable $e) {
            $unknown = true;
        }
        $checks = [
            'Alle Dateien vorhanden' => $missing === [] && count($files) === 6,
            'Sprachdaten sind gültig (gzip)' => strlen($gz) > 1000000 && substr($gz, 0, 2) === "\x1f\x8b",
            'Texterkennungs-Kern wird ausgeliefert' => $core->getStatusCode() === 200 && str_contains((string) $core->headers->get('Content-Type'), 'javascript') && strlen($core->getContent()) > 3000000,
            'Scanner-Skript enthält die Funktionen' => str_contains($script, 'BelegScanner') && str_contains($script, 'buildPdf') && str_contains($script, 'parseReceipt') && str_contains($script, 'detectCorners'),
            'Fremde Dateien werden nicht ausgeliefert' => $unknown,
            'Seite' => Route::has('scan.asset'),
            'Beleg-Formular bindet den Scanner ein' => str_contains((string) file_get_contents(resource_path('views/receipts/form.blade.php')), 'scan.asset'),
        ];
        $this->line('Selbsttest Paket 47 (Beleg-Scanner)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
