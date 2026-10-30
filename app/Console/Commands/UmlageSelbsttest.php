<?php

namespace App\Console\Commands;

use App\Support\Rent\Erhoehung\Regeln;
use Illuminate\Console\Command;

/** Selbsttest Paket 41 (Heizungsumlage nach § 559e und Wärmepumpe nach § 559f BGB, Stand 29.07.2026). */
class UmlageSelbsttest extends Command
{
    protected $signature = 'umlage:selbsttest';

    protected $description = 'Selbsttest Heizungsumlage und Wärmepumpe (Paket 41)';

    public function handle(): int
    {
        $b = ['lease_type' => 'wohnen', 'notice_on' => '2026-10-15', 'area_sqm' => 60.0, 'old_cold' => 60000, 'costs' => 1200000, 'upkeep_percent' => 20.0,
            'subsidies' => 360000, 'heating' => false, 'simplified' => false, 'delayed' => false, 'prior_monthly' => 0, 'prior_heating_monthly' => 0];
        $plain = Regeln::modernisierung($b);
        $green = Regeln::modernisierung(['heating_type' => 'erneuerbar'] + $b);
        $fossil = Regeln::modernisierung(['heating_type' => 'fossil'] + $b);
        $half = Regeln::modernisierung(['heating_type' => 'waermepumpe'] + $b);
        $proof = Regeln::modernisierung(['heating_type' => 'waermepumpe', 'pump_proof' => 'nachweis'] + $b);
        $exception = Regeln::modernisierung(['heating_type' => 'waermepumpe', 'pump_proof' => 'ausnahme'] + $b);
        $old = Regeln::modernisierung(['heating' => true] + $b);
        $checks = [
            'Ohne Heizung unverändert' => $plain['percent'] === 8 && $plain['base'] === 600000 && $plain['allowed'] === 4000,
            'Erneuerbar: 15 % pauschal' => $green['upkeep_percent'] === 15.0 && $green['base'] === 660000 && $green['flat_upkeep'] === true,
            'Gas oder Öl: kein Pauschalabzug' => $fossil['upkeep_percent'] === 20.0 && $fossil['base'] === 600000 && $fossil['flat_upkeep'] === false,
            'Heizungsgrenze 0,50 € je m²' => $green['allowed'] === 3000 && $fossil['allowed'] === 3000,
            'Wärmepumpe ohne Nachweis: halbe Kosten' => $half['halved'] === true && $half['costs_base'] === 600000 && $half['base'] === 150000 && $half['allowed'] === 1250,
            'Wärmepumpe mit Nachweis oder Ausnahme' => $proof['halved'] === false && $proof['base'] === 660000 && $exception['halved'] === false,
            'Ältere Erfassung ohne Heizungsart' => $old['heating_type'] === 'alt' && $old['upkeep_percent'] === 20.0 && $old['percent'] === 10,
            'Ansicht' => view()->exists('rentincrease.create') && view()->exists('rentincrease.print'),
        ];
        $this->line('Selbsttest Paket 41 (Heizungsumlage und Wärmepumpe)');
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
