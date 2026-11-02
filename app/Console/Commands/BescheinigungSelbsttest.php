<?php

namespace App\Console\Commands;

use App\Support\Bescheinigung\Bescheinigung;
use App\Support\Bescheinigung\Kuendigung;
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
            'Ostern und 3. Werktag' => Kuendigung::ostern(2026)->format('Y-m-d') === '2026-04-05' && Kuendigung::dritterWerktag('2026-11-20') === '2026-11-04' && Kuendigung::dritterWerktag('2027-01-20') === '2027-01-05',
            'Kündigungsfrist Wohnraum' => Kuendigung::ordentlichesEnde('wohnen', '2024-03-01', '2026-11-04')['ende'] === '2027-01-31'
                && Kuendigung::ordentlichesEnde('wohnen', '2024-03-01', '2026-11-05')['ende'] === '2027-02-28'
                && Kuendigung::ordentlichesEnde('wohnen', '2018-03-01', '2026-11-02')['ende'] === '2027-07-31',
            'Kündigungsfrist Gewerbe' => Kuendigung::ordentlichesEnde('gewerbe', '2020-01-01', '2026-10-05')['ende'] === '2027-03-31'
                && Kuendigung::ordentlichesEnde('gewerbe', '2020-01-01', '2026-10-06')['ende'] === '2027-06-30',
            'Zahlungsverzug' => Kuendigung::zahlungsverzug([500, 500], 800)['ok'] && ! Kuendigung::zahlungsverzug([300, 0, 300], 800)['ok'] && Kuendigung::zahlungsverzug([800, 800], 800)['ok'],
            'Seiten' => Route::has('bescheinigung.choose') && Route::has('bescheinigung.index') && Route::has('bescheinigung.wohnungsgeber') && Route::has('bescheinigung.mietschuldenfrei') && Route::has('bescheinigung.abmahnung') && Route::has('bescheinigung.kuendigung'),
            'Ansichten' => $compiles('bescheinigung.choose') && $compiles('bescheinigung.index') && $compiles('bescheinigung.wohnungsgeber') && $compiles('bescheinigung.mietschuldenfrei') && $compiles('bescheinigung._druck') && $compiles('bescheinigung._brief') && $compiles('bescheinigung.abmahnung') && $compiles('bescheinigung.kuendigung'),
        ];
        $this->line('Selbsttest Paket 44/45 (Bescheinigungen, Abmahnung, Kündigung)');
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
