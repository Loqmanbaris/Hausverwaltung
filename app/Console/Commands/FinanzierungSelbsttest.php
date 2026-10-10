<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Support\Finance\LoanPlan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 23 (Finanzierung). */
class FinanzierungSelbsttest extends Command
{
    protected $signature = 'finanzierung:selbsttest';

    protected $description = 'Selbsttest Finanzierung (Paket 23)';

    public function handle(): int
    {
        $math = false;
        try {
            $l = new Loan(['kind' => 'annuitaet', 'principal' => 100000, 'interest_rate' => 3, 'initial_repayment' => 2, 'interval_months' => 1, 'first_payment_on' => '2026-01-31']);
            $s = LoanPlan::schedule($l);
            $math = abs($s[0]['interest'] - 250) < 0.01 && abs(array_sum(array_column($s, 'principal')) - 100000) < 0.05;
        } catch (\Throwable $e) {
            $this->line('  '.$e->getMessage());
        }
        $checks = [
            'Tabellen' => Schema::hasTable('loans') && Schema::hasTable('loan_events') && Schema::hasTable('property_values'),
            'Seiten' => Route::has('finance.index') && Route::has('loans.show') && Route::has('properties.values') && Route::has('bank.expenses.loan'),
            'Ansichten' => view()->exists('finance.index') && view()->exists('finance.loan-show') && view()->exists('dashboard-blocks.finance'),
            'Tilgungsplan rechnet richtig' => $math,
        ];
        $this->line('Selbsttest Paket 23 (Finanzierung)');
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
