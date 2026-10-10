<?php

namespace App\Console\Commands;

use App\Support\Interest\Raten;
use App\Support\Interest\Verzugszinsen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 14 (Verzugszinsen und Raten). */
class ZinsenSelbsttest extends Command
{
    protected $signature = 'zinsen:selbsttest';

    protected $description = 'Selbsttest Verzugszinsen und Ratenvereinbarungen (Paket 14)';

    public function handle(): int
    {
        $plan = Raten::schedule('1000.00', 3, null, '2026-01-31');
        $checks = [
            'Tabellen' => Schema::hasTable('base_interest_rates') && Schema::hasTable('interest_bookings') && Schema::hasTable('installment_plans') && Schema::hasTable('installments'),
            'Basiszinssätze' => Verzugszinsen::baseRateOn('2026-07-15') !== null,
            'Seiten' => Route::has('interest.index') && Route::has('installments.create') && Route::has('installments.print') && Route::has('interest.rates.store'),
            'Ratenplan' => array_column($plan, 'amount') === ['333.33', '333.33', '333.34'] && $plan[1]['due'] === '2026-02-28',
            'Ansichten' => view()->exists('interest.index') && view()->exists('interest.plan-print'),
        ];
        $this->line('Selbsttest Paket 14 (Verzugszinsen und Raten)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if (Verzugszinsen::missingCurrentRate()) {
            $this->line('  [HINWEIS] Basiszinssatz für das laufende Halbjahr fehlt (im Mahnwesen eintragen).');
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
