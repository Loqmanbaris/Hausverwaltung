<?php

namespace App\Console\Commands;

use App\Support\Tax\Afa;
use App\Support\Tax\AnlageV;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 29 (AfA in der Anlage V). */
class AfaSelbsttest extends Command
{
    protected $signature = 'afa:selbsttest';

    protected $description = 'Selbsttest Gebäude-AfA (Paket 29)';

    public function handle(): int
    {
        $full = Afa::schedule(['basis' => 40000000, 'rate' => 2.0, 'start' => '2024-03-15'], 2026);
        $manual = Afa::schedule(['basis' => 40000000, 'rate' => 3.0, 'start' => '2023-01-01'], 2025, [2024 => 2000000]);
        $capped = Afa::schedule(['basis' => 1000000, 'rate' => 50.0, 'start' => '2020-01-01'], 2023);
        $checks = [
            'Tabellen' => Schema::hasTable('property_afa') && Schema::hasTable('property_afa_years'),
            'Erstes Jahr anteilig' => $full[2024]['months'] === 10 && $full[2024]['cents'] === 666667 && $full[2025]['cents'] === 800000,
            'Jahresbetrag von Hand' => $manual[2024]['cents'] === 2000000 && $manual[2024]['source'] === 'manuell' && $manual[2025]['cents'] === 1200000,
            'Grundlage wird nicht überschritten' => $capped[2021]['cents'] === 500000 && $capped[2022]['cents'] === 0,
            'Anlage V kennt die AfA' => isset(AnlageV::COSTS['afa']),
            'Seiten' => Route::has('afa.edit') && Route::has('afa.update') && Route::has('afa.year.store'),
            'Ansicht' => view()->exists('afa.edit'),
        ];
        $this->line('Selbsttest Paket 29 (AfA)');
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
