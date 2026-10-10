<?php

namespace App\Console\Commands;

use App\Support\Nk\Co2Split;
use App\Support\Nk\MeterImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 26 (CO2-Kosten und Heizkosten-Zähler). */
class HeizungSelbsttest extends Command
{
    protected $signature = 'heizung:selbsttest';

    protected $description = 'Selbsttest CO2-Kostenaufteilung und Zähler (Paket 26)';

    public function handle(): int
    {
        $a = Co2Split::calculate(4000, 261.80, 160, 365);
        $checks = [
            'Tabellen und Spalten' => Schema::hasTable('co2_claims') && Schema::hasTable('consumption_notices') && Schema::hasColumn('meters', 'remote') && Schema::hasColumn('nk_settlements', 'co2_kg') && Schema::hasColumn('nk_settings', 'fuel'),
            'Stufenmodell (25 kg/m² = 30 % Vermieter)' => $a['step'] === 4 && $a['landlord_cents'] === 7854,
            'Grenzen 12 und 52 kg' => Co2Split::calculate(1200, 100, 100, 365)['landlord_cents'] === 1000 && Co2Split::calculate(5200, 100, 100, 365)['landlord_cents'] === 9500,
            'Nichtwohngebäude 50 %' => Co2Split::calculate(10, 100, 100, 365, 'nichtwohn')['landlord_cents'] === 5000,
            'Zahlen und Datum im Import' => MeterImport::number('1.150,5') === '1150.5' && MeterImport::date('31.10.2026') === '2026-10-31',
            'Seiten' => Route::has('co2.index') && Route::has('consumption.index') && Route::has('nk.co2') && view()->exists('nk.verbrauch-print') && view()->exists('co2.print'),
        ];
        $this->line('Selbsttest Paket 26 (CO2-Kosten und Zähler)');
        $failed = 0;
        foreach ($checks as $label => $pass) {
            $failed += $pass ? 0 : 1;
            $this->line(($pass ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
