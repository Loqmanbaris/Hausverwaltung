<?php

namespace App\Console\Commands;

use App\Support\Vertraege\Laufzeit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 32 (Laufende Verträge). */
class LaufvertraegeSelbsttest extends Command
{
    protected $signature = 'laufvertraege:selbsttest';

    protected $description = 'Selbsttest laufende Verträge und Kündigungserinnerung (Paket 32)';

    public function handle(): int
    {
        $base = ['status' => 'aktiv', 'term_end' => '2027-03-31', 'auto_renew' => true, 'renew_months' => 12, 'notice_value' => 3, 'notice_unit' => 'monate', 'remind_days' => 30];
        $ok = Laufzeit::info($base, '2026-10-12');
        $soon = Laufzeit::info($base, '2026-12-05');
        $urgent = Laufzeit::info($base, '2026-12-25');
        $missed = Laufzeit::info($base, '2027-01-10');
        $ends = Laufzeit::info(['auto_renew' => false] + $base, '2027-04-10');
        $checks = [
            'Tabelle' => Schema::hasTable('service_contracts'),
            'Kündigungstermin' => Laufzeit::deadline('2026-12-31', 3, 'monate') === '2026-09-29' && Laufzeit::deadline('2026-12-31', 4, 'wochen') === '2026-12-02',
            'Erinnerungsstufen' => $ok['state'] === 'ok' && $soon['state'] === 'bald' && $urgent['state'] === 'dringend',
            'Verpasste Kündigung verlängert' => $missed['missed_end'] === '2027-03-31' && $missed['next_end'] === '2028-03-31' && $missed['deadline'] === '2027-12-30',
            'Vertrag ohne Verlängerung läuft ab' => $ends['state'] === 'abgelaufen',
            'Kosten im Jahr' => Laufzeit::annualCents(8900, 'monatlich') === 106800 && Laufzeit::annualCents(8900, 'einmalig') === 0,
            'Seiten' => Route::has('svc.index') && Route::has('svc.show') && Route::has('svc.cancel'),
            'Ansichten' => view()->exists('svc.index') && view()->exists('svc.show') && view()->exists('svc.form'),
        ];
        $this->line('Selbsttest Paket 32 (Laufende Verträge)');
        $failed = 0;
        foreach ($checks as $label => $res) {
            $failed += $res ? 0 : 1;
            $this->line(($res ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
