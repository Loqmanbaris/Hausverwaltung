<?php

namespace App\Console\Commands;

use App\Support\Nk\NkCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest der Nebenkostenabrechnung (Paket 7). */
class NkSelbsttest extends Command
{
    protected $signature = 'nk:selbsttest';

    protected $description = 'Selbsttest der Nebenkostenabrechnung (Paket 7)';

    private int $failed = 0;

    public function handle(): int
    {
        $this->line('Selbsttest Paket 7 (Nebenkostenabrechnung)');

        foreach (['nk_settings', 'nk_cost_keys', 'lease_persons', 'meters', 'meter_readings', 'nk_settlements', 'nk_settlement_costs'] as $t) {
            $this->check('Tabelle '.$t, Schema::hasTable($t));
        }
        $this->check('Mieterkonto kennt Nebenkosten-Posten', Schema::hasColumns('rent_charges', ['kind', 'nk_settlement_id', 'label']) && Schema::hasColumn('rent_payments', 'nk_settlement_id'));
        foreach (['nk.index', 'nk.show', 'nk.print', 'nk.finalize', 'nk.settings', 'nk.meters', 'leases.persons.store'] as $r) {
            $this->check('Seite '.$r, Route::has($r));
        }
        foreach (['nk.index', 'nk.show', 'nk.print', 'nk.settings', 'nk.meters'] as $v) {
            $this->check('Vorlage '.$v, view()->exists($v));
        }

        // Rechenbeispiel: 2 Wohnungen (60 und 40 m²), eine halbes Jahr leer, Gewerbe mit Vorwegabzug, Heizung 70/30
        $units = [
            1 => ['label' => 'W1', 'area' => 60, 'commercial' => false, 'consumption' => ['wasser' => 30, 'warmwasser' => 10, 'heizung' => 600]],
            2 => ['label' => 'W2', 'area' => 40, 'commercial' => false, 'consumption' => ['wasser' => 10, 'warmwasser' => 5, 'heizung' => 400]],
            3 => ['label' => 'Laden', 'area' => 100, 'commercial' => true, 'consumption' => ['wasser' => 0, 'warmwasser' => 0, 'heizung' => 0]],
        ];
        $segments = [
            ['unit' => 1, 'lease' => 11, 'label' => 'A', 'days' => 365, 'person_days' => 730.0],
            ['unit' => 2, 'lease' => 12, 'label' => 'B', 'days' => 183, 'person_days' => 183.0],
            ['unit' => 2, 'lease' => null, 'label' => 'Leerstand', 'days' => 182, 'person_days' => null],
            ['unit' => 3, 'lease' => 13, 'label' => 'C', 'days' => 365, 'person_days' => 0.0],
        ];
        $lines = [
            ['id' => 'g', 'cost_key' => 'grundsteuer', 'label' => 'Grundsteuer', 'amount' => '1000.00', 'key_type' => 'flaeche', 'commercial_percent' => null, 'unit' => null],
            ['id' => 'm', 'cost_key' => 'muell', 'label' => 'Müll', 'amount' => '600.00', 'key_type' => 'personen', 'commercial_percent' => '50', 'unit' => null],
            ['id' => 'h', 'cost_key' => 'heizung', 'label' => 'Heizung', 'amount' => '1000.00', 'key_type' => 'heizung', 'commercial_percent' => null, 'unit' => null],
        ];
        $r = NkCalculator::calculate(365, $units, $segments, $lines, 70);
        $g = $r['lines'][0]['alloc'];
        $this->check('Fläche: 1.000 € auf 60/40/100 m², Leerstand anteilig', $g[0] === 30000 && $g[3] === 50000 && $g[1] + $g[2] === 20000 && $g[2] === 9973);
        $mc = $r['lines'][1]['alloc'];
        $mr = $r['lines'][2]['alloc'];
        $this->check('Vorwegabzug Gewerbe 50 %: Laden 300 € (nach Fläche), Rest nach Personen', ($mc[3] ?? 0) === 30000 && count($mc) === 1 && ($mr[0] ?? 0) + ($mr[1] ?? 0) === 30000 && ($mr[0] ?? 0) === 23987);
        $hv = $r['lines'][3]['alloc'];
        $this->check('Heizung 70 % nach Verbrauch (600/400 Einheiten)', ($hv[0] ?? 0) === 42000 && ($hv[1] ?? 0) + ($hv[2] ?? 0) === 28000);
        $sum = 0;
        foreach ($r['lines'] as $l) {
            $sum += array_sum($l['alloc']);
        }
        $this->check('Summe der verteilten Beträge stimmt auf den Cent', $sum === 260000);

        if ($this->failed > 0) {
            $this->error($this->failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }

    private function check(string $label, bool $ok): void
    {
        if (! $ok) {
            $this->failed++;
        }
        $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
    }
}
