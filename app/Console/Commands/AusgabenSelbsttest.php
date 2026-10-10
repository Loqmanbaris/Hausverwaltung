<?php

namespace App\Console\Commands;

use App\Support\Bank\ExpenseMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 9 (Ausgaben mit Belegen abgleichen). */
class AusgabenSelbsttest extends Command
{
    protected $signature = 'ausgaben:selbsttest';

    protected $description = 'Selbsttest Abgleich der Abbuchungen mit Belegen (Paket 9)';

    public function handle(): int
    {
        $checks = [
            'Tabelle bank_transaction_receipts' => Schema::hasTable('bank_transaction_receipts') && Schema::hasColumns('bank_transaction_receipts', ['bank_transaction_id', 'receipt_id', 'amount']),
            'Seite bank.expenses' => Route::has('bank.expenses') && Route::has('bank.expenses.link') && Route::has('bank.expenses.none'),
            'Vorlage bank.expenses' => view()->exists('bank.expenses'),
            'Gründe für „ohne Beleg“' => count(ExpenseMatcher::NO_RECEIPT) === 5,
        ];
        $this->line('Selbsttest Paket 9 (Ausgaben mit Belegen abgleichen)');
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
