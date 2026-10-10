<?php

namespace App\Console\Commands;

use App\Models\Lease;
use App\Models\RentAllocation;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Support\Tax\AnlageV;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

/** Selbsttest Paket 8 (Anlage V, Programmstand). */
class SteuerSelbsttest extends Command
{
    protected $signature = 'steuer:selbsttest';

    protected $description = 'Selbsttest Anlage V und Programmstand-Download (Paket 8)';

    private int $failed = 0;

    public function handle(): int
    {
        $this->line('Selbsttest Paket 8 (Anlage V, Programmstand)');
        foreach (['anlage-v.index', 'anlage-v.print', 'anlage-v.csv', 'updates.source'] as $r) {
            $this->check('Seite '.$r, Route::has($r));
        }
        foreach (['anlage-v.index', 'anlage-v.print'] as $v) {
            $this->check('Vorlage '.$v, view()->exists($v));
        }

        // Gewerbe mit 19 %: 1.487,50 = Kalt 1.000 + NK 250 + USt 237,50
        $lease = new Lease(['lease_type' => 'gewerbe', 'vat_rate' => 19]);
        $charge = new RentCharge(['kind' => 'miete', 'rent_cold' => 1000, 'prepay_operating' => 250, 'vat_amount' => 237.5, 'total' => 1487.5]);
        $alloc = new RentAllocation(['amount' => 1487.5]);
        $alloc->setRelation('charge', $charge);
        $pay = new RentPayment(['amount' => 1500, 'source' => 'manual']);
        $pay->setRelation('allocations', collect([$alloc]));
        $s = AnlageV::splitPayment($pay, $lease);
        $this->check('Gewerbe-Zahlung: 1.012,50 Miete (mit 12,50 Vorauszahlung), 250 Umlagen, 237,50 USt', $s['gewerbe'] === 101250 && $s['umlagen'] === 25000 && $s['ust'] === 23750 && array_sum($s) === 150000);

        $credit = new RentPayment(['amount' => 80, 'source' => 'nk']);
        $credit->setRelation('allocations', collect());
        $this->check('Guthaben aus Nebenkostenabrechnung zählt als Erstattung', AnlageV::splitPayment($credit, $lease)['erstattung'] === -8000);
        $this->check('Zuordnung der Kostenarten', AnlageV::costGroup('reparatur') === 'erhaltung' && AnlageV::costGroup('darlehen_zinsen') === 'zinsen');

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
