<?php

namespace App\Console\Commands;

use App\Support\Rent\Erhoehung\Regeln;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 27 (Mieterhöhungen). */
class ErhoehungSelbsttest extends Command
{
    protected $signature = 'erhoehung:selbsttest';

    protected $description = 'Selbsttest Mieterhöhungen (Paket 27)';

    public function handle(): int
    {
        $tl = Regeln::timeline('vergleich', '2026-10-15');
        $rents = [['from' => '2022-01-01', 'reason' => 'beginn', 'cold' => 60000]];
        $v = Regeln::vergleich(['lease_type' => 'wohnen', 'adjustment_type' => 'fest', 'rents' => $rents, 'notice_on' => '2026-10-15', 'area_sqm' => 60.0, 'cap_percent' => 20, 'new_cold' => 70000, 'comparable_sqm' => 1200, 'lease_end' => null]);
        $tooHigh = Regeln::vergleich(['new_cold' => 73000, 'lease_type' => 'wohnen', 'adjustment_type' => 'fest', 'rents' => $rents, 'notice_on' => '2026-10-15', 'area_sqm' => 60.0, 'cap_percent' => 20, 'comparable_sqm' => 1200, 'lease_end' => null]);
        $m = Regeln::modernisierung(['lease_type' => 'wohnen', 'notice_on' => '2026-10-15', 'area_sqm' => 60.0, 'old_cold' => 60000, 'costs' => 1200000, 'upkeep_percent' => 30.0, 'subsidies' => 0, 'heating' => false, 'simplified' => false, 'delayed' => false, 'prior_monthly' => 0, 'prior_heating_monthly' => 0]);
        $checks = [
            'Tabelle' => Schema::hasTable('rent_increases'),
            'Fristen' => $tl['effective'] === '2027-01-01' && $tl['consent_until'] === '2026-12-31' && $tl['suit_until'] === '2027-03-31',
            'Kappungsgrenze' => $v['ok'] && $v['max_allowed'] === 72000 && ! $tooHigh['ok'],
            'Modernisierung' => $m['monthly'] === 5600 && $m['new_cold'] === 65600,
            'Seiten' => Route::has('rent_increase.index') && Route::has('rent_increase.create') && Route::has('rent_increase.print') && Route::has('rent_increase.consent'),
            'Ansichten' => view()->exists('rentincrease.index') && view()->exists('rentincrease.print') && view()->exists('rentincrease.create'),
        ];
        $this->line('Selbsttest Paket 27 (Mieterhöhungen)');
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
