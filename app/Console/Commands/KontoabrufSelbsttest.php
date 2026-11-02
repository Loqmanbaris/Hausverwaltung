<?php

namespace App\Console\Commands;

use App\Support\Bank\Kontoabruf;
use Fhp\Model\StatementOfAccount\Transaction as T;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 48 (automatischer Kontoabruf per FinTS). */
class KontoabrufSelbsttest extends Command
{
    protected $signature = 'kontoabruf:selbsttest';

    protected $description = 'Selbsttest automatischer Kontoabruf (Paket 48)';

    public function handle(): int
    {
        $credit = (new T)->setBooked(true)->setAmount(850.0)->setCreditDebit(T::CD_CREDIT)->setBookingDate(new \DateTime('2026-11-02'))
            ->setBookingText('GUTSCHR. UEBERWEISUNG')->setName('Max Muster')->setAccountNumber('DE89 3704 0044 0532 0130 00')
            ->setBankCode('COBADEFFXXX')->setStructuredDescription(['SVWZ' => 'Miete November']);
        $debit = (new T)->setBooked(true)->setAmount(45.1)->setCreditDebit(T::CD_DEBIT)->setBookingDate(new \DateTime('2026-11-03'))
            ->setBookingText('LASTSCHRIFT')->setName('Stadtwerke')->setAccountNumber('DE02120300000000202051')
            ->setStructuredDescription(['SVWZ' => 'Strom', 'MREF' => 'M-1', 'CRED' => 'DE98ZZZ09999999999']);
        $c = Kontoabruf::zeile($credit);
        $d = Kontoabruf::zeile($debit);
        [$rest, $dupes] = Kontoabruf::filterVorhandene([$c, $c, $d], ['2026-11-02|850.00|'.(\App\Support\Bank\BankMatcher::ibanHash($c['iban']) ?? '') => 1]);
        $compiles = function (string $view): bool {
            try {
                $php = Blade::compileString((string) file_get_contents(view()->getFinder()->find($view)));

                return preg_match('/<\?php\(|@php\b|@endphp/', $php) !== 1;
            } catch (\Throwable) {
                return false;
            }
        };

        $checks = [
            'Bibliothek php-fints' => class_exists(\Fhp\FinTs::class),
            'Tabelle' => Schema::hasTable('bank_connections') && Schema::hasColumns('bank_connections', ['url', 'bank_code', 'username', 'pin', 'tan_mode', 'state', 'pending', 'status', 'active', 'last_to']),
            'Buchung umwandeln' => $c['amount'] === '850.00' && $c['booking_date'] === '2026-11-02' && $c['iban'] === 'DE89370400440532013000' && $c['purpose'] === 'Miete November'
                && $d['amount'] === '-45.10' && $d['mandate_ref'] === 'M-1' && $d['creditor_id'] === 'DE98ZZZ09999999999',
            'Doppelte erkennen' => $dupes === 1 && count($rest) === 2,
            'Bankleitzahl aus IBAN' => Kontoabruf::blzAusIban('DE12 5001 0517 0648 4898 90') === '50010517' && Kontoabruf::blzAusIban('AT611904300234573201') === '',
            'Seiten' => Route::has('bank.abruf') && Route::has('bank.abruf.save') && Route::has('bank.abruf.run') && Route::has('bank.abruf.weiter'),
            'Ansicht' => view()->exists('bank.kontoabruf') && $compiles('bank.kontoabruf'),
        ];
        $this->line('Selbsttest Paket 48 (Kontoabruf)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alles in Ordnung.');

        return self::SUCCESS;
    }
}
