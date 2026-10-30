<?php

namespace App\Console\Commands;

use App\Support\Kaution\Kaution;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 34 (Kautionsverwaltung). */
class KautionSelbsttest extends Command
{
    protected $signature = 'kaution:selbsttest';

    protected $description = 'Selbsttest Kautionsverwaltung (Paket 34)';

    public function handle(): int
    {
        $three = Kaution::schedule(100000, '2026-01-31', 3);
        $paid = Kaution::settlement(180000, [['reason' => 'Schäden', 'cents' => 50000], ['reason' => 'Miete', 'cents' => 20000]]);
        $claim = Kaution::settlement(100000, [['reason' => 'Renovierung', 'cents' => 130000]]);
        $checks = [
            'Tabellen' => Schema::hasTable('deposits') && Schema::hasTable('deposit_entries'),
            'Raten' => array_column($three, 'cents') === [33334, 33333, 33333] && array_sum(array_column($three, 'cents')) === 100000 && $three[1]['due_on'] === '2026-02-28',
            'Eine bis drei Raten' => count(Kaution::schedule(150000, '2026-05-01', 1)) === 1 && count(Kaution::schedule(150000, '2026-05-01', 9)) === 3,
            'Zinsen' => Kaution::interestCents(180000, 1.0, '2026-01-01', '2027-01-01') === 1800,
            'Abrechnung mit Auszahlung' => $paid['payout'] === 110000 && $paid['withheld'] === 70000 && $paid['claim'] === 0,
            'Abrechnung mit Nachforderung' => $claim['payout'] === 0 && $claim['claim'] === 30000,
            'Seiten' => Route::has('deposit.index') && Route::has('deposit.show') && Route::has('deposit.settle'),
            'Ansichten' => view()->exists('deposits.index') && view()->exists('deposits.show') && view()->exists('deposits.letter'),
        ];
        $this->line('Selbsttest Paket 34 (Kautionsverwaltung)');
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
