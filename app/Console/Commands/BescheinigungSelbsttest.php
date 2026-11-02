<?php

namespace App\Console\Commands;

use App\Support\Bescheinigung\Bescheinigung;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 44 (Wohnungsgeberbestätigung, Mietschuldenfreiheitsbescheinigung). */
class BescheinigungSelbsttest extends Command
{
    protected $signature = 'bescheinigung:selbsttest';

    protected $description = 'Selbsttest Bescheinigungen zum Mietvertrag (Paket 44)';

    public function handle(): int
    {
        $compiles = function (string $view): bool {
            try {
                $php = Blade::compileString((string) file_get_contents(view()->getFinder()->find($view)));

                return preg_match('/<\?php\(|@php\b|@endphp/', $php) !== 1;
            } catch (\Throwable) {
                return false;
            }
        };
        $checks = [
            'Personen je Zeile' => Bescheinigung::personen("  Anna  Muster \n\nBen Muster\r\nAnna Muster\n") === ['Anna Muster', 'Ben Muster'],
            'Datum deutsch' => Bescheinigung::de('2026-11-02') === '02.11.2026' && Bescheinigung::de('x') === '',
            'Geschoss' => Bescheinigung::geschoss('2') === '2. OG' && Bescheinigung::geschoss('0') === 'EG' && Bescheinigung::geschoss('-1') === '1. UG' && Bescheinigung::geschoss('DG') === 'DG',
            'Seiten' => Route::has('bescheinigung.index') && Route::has('bescheinigung.wohnungsgeber') && Route::has('bescheinigung.mietschuldenfrei'),
            'Ansichten' => $compiles('bescheinigung.index') && $compiles('bescheinigung.wohnungsgeber') && $compiles('bescheinigung.mietschuldenfrei') && $compiles('bescheinigung._druck'),
        ];
        $this->line('Selbsttest Paket 44 (Bescheinigungen)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alles in Ordnung.');

        return self::SUCCESS;
    }
}
