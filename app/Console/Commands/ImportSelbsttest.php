<?php

namespace App\Console\Commands;

use App\Support\Import\Vorlage;
use App\Support\Import\XlsxReader;
use App\Support\Import\XlsxWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 15 (Import aus Excel). */
class ImportSelbsttest extends Command
{
    protected $signature = 'import:selbsttest';

    protected $description = 'Selbsttest Excel-Import (Paket 15)';

    public function handle(): int
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xt');
        $ok = false;
        try {
            XlsxWriter::write($tmp, ['Test' => ['rows' => [['A', 'Ä ö'], ['1', '2']], 'header' => true]]);
            $r = XlsxReader::read((string) file_get_contents($tmp));
            $ok = ($r['Test'][1][2] ?? '') === 'Ä ö' && ($r['Test'][2][1] ?? '') === '1';
        } catch (\Throwable) {
        }
        @unlink($tmp);
        $checks = [
            'Excel schreiben und lesen' => $ok,
            'Seiten' => Route::has('import.index') && Route::has('import.template') && Route::has('import.check') && Route::has('import.run'),
            'Vorlage' => count(Vorlage::SHEETS) === 5,
            'Ansicht' => view()->exists('import.index'),
        ];
        $this->line('Selbsttest Paket 15 (Import aus Excel)');
        $failed = 0;
        foreach ($checks as $label => $c) {
            $failed += $c ? 0 : 1;
            $this->line(($c ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
