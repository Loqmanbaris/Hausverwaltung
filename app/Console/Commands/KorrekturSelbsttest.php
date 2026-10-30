<?php

namespace App\Console\Commands;

use App\Support\Tax\Afa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 40 (Kaution zurücknehmen, 15-%-Grenze bei der AfA). */
class KorrekturSelbsttest extends Command
{
    protected $signature = 'korrektur:selbsttest';

    protected $description = 'Selbsttest Paket 40 (Kaution zurücknehmen, 15-%-Grenze)';

    public function handle(): int
    {
        $ok = Afa::nearPurchaseStatus(1000000, 10000000);
        $near = Afa::nearPurchaseStatus(1200000, 10000000);
        $over = Afa::nearPurchaseStatus(1500001, 10000000);
        $checks = [
            'Spalte settlement' => Schema::hasColumn('deposit_entries', 'settlement'),
            'Grenze nicht erreicht' => $ok['status'] === 'ok' && $ok['limit'] === 1500000,
            'Grenze fast erreicht' => $near['status'] === 'nah',
            'Grenze überschritten' => $over['status'] === 'ueber' && $over['percent'] === 15.0,
            'Ohne Grundlage' => Afa::nearPurchase(0, null, null) === null && Afa::nearPurchase(0, '2026-01-01', 0) === null,
            'Ansicht' => view()->exists('afa.edit'),
        ];
        $this->line('Selbsttest Paket 40 (Kaution zurücknehmen, 15-%-Grenze)');
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
