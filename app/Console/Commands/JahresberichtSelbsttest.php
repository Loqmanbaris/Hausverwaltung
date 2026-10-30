<?php

namespace App\Console\Commands;

use App\Support\Report\Jahresbericht;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 28 (Jahresbericht). */
class JahresberichtSelbsttest extends Command
{
    protected $signature = 'jahresbericht:selbsttest';

    protected $description = 'Selbsttest Jahresbericht je Eigentümer (Paket 28)';

    public function handle(): int
    {
        $checks = [
            'Stichtag laufendes Jahr' => Jahresbericht::stichtag(2026, '2026-10-11') === '2026-10-11',
            'Stichtag Vorjahr' => Jahresbericht::stichtag(2025, '2026-10-11') === '2025-12-31',
            'Seiten' => Route::has('report.index') && Route::has('report.print') && Route::has('report.pdf'),
            'Ansichten' => view()->exists('report.index') && view()->exists('report.print'),
        ];
        $this->line('Selbsttest Paket 28 (Jahresbericht)');
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
