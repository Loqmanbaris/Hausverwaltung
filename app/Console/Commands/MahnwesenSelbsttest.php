<?php

namespace App\Console\Commands;

use App\Models\DunningNotice;
use App\Support\Dunning\Mahnwesen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Selbsttest Paket 10 (Mahnwesen). */
class MahnwesenSelbsttest extends Command
{
    protected $signature = 'mahnwesen:selbsttest';

    protected $description = 'Selbsttest Mahnwesen (Paket 10)';

    public function handle(): int
    {
        $count = null;
        try {
            $count = DunningNotice::query()->count();
        } catch (Throwable) {
        }
        $checks = [
            'Tabelle dunning_notices' => Schema::hasTable('dunning_notices') && Schema::hasColumns('dunning_notices', ['lease_id', 'level', 'issued_on', 'pay_by', 'items', 'fee_charge_id']),
            'Mahnungen lesbar' => $count !== null,
            'Seiten Mahnwesen' => Route::has('dunning.index') && Route::has('dunning.store') && Route::has('dunning.print') && Route::has('dunning.cancel') && Route::has('dunning.settings'),
            'Vorlagen' => view()->exists('dunning.index') && view()->exists('dunning.print'),
            'Einstellungen' => Mahnwesen::days('dunning_pay_days') >= 0 && Mahnwesen::fee(1) !== '' && count(DunningNotice::LEVELS) === 3,
        ];
        $this->line('Selbsttest Paket 10 (Mahnwesen)');
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
