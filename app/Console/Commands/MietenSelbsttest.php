<?php

namespace App\Console\Commands;

use App\Support\Rent\RentLedger;
use App\Support\Rent\RentMath;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Prüft nach dem Einspielen von Paket 5a, ob Tabellen, Seiten und Rechenlogik stimmen. */
class MietenSelbsttest extends Command
{
    protected $signature = 'mieten:selbsttest';

    protected $description = 'Selbsttest für Mietverträge und Mieteingang (Paket 5a)';

    private int $failed = 0;

    public function handle(): int
    {
        $this->line('Selbsttest Paket 5a/5b/5c (Mietverträge, Mieteingang, Kontoauszug-Import, DATEV Mieten)');

        // 1. Tabellen
        $tables = [
            'leases' => ['owner_id', 'unit_id', 'tenant_id', 'start_date', 'vat_rate', 'adjustment_type'],
            'lease_rents' => ['lease_id', 'valid_from', 'rent_cold', 'prepay_operating', 'prepay_heating', 'parking', 'garage', 'furniture'],
            'rent_charges' => ['lease_id', 'period', 'due_date', 'total', 'paid_amount', 'vat_amount'],
            'rent_payments' => ['lease_id', 'paid_on', 'amount', 'allocated_amount', 'bank_transaction_id', 'income_kind', 'for_period'],
            'bank_transaction_items' => ['bank_transaction_id', 'lease_id', 'kind', 'amount', 'deposit_entry_id'],
            'rent_allocations' => ['rent_payment_id', 'rent_charge_id', 'amount'],
            'bank_imports' => ['owner_bank_account_id', 'filename', 'rows_new'],
            'bank_transactions' => ['owner_bank_account_id', 'dedupe_key', 'booking_date', 'amount', 'counterparty_iban_hash', 'status'],
            'lease_payer_accounts' => ['lease_id', 'iban_hash', 'iban'],
            'datev_rent_accounts' => ['rent_key', 'account', 'bu_key'],
            'datev_export_rent_items' => ['datev_export_id', 'rent_payment_id', 'account', 'amount'],
            'datev_exports' => ['kind'],
        ];
        foreach ($tables as $table => $cols) {
            $ok = Schema::hasTable($table) && Schema::hasColumns($table, $cols);
            $this->check('Tabelle '.$table, $ok);
        }

        // 2. Seiten
        foreach (['leases.index', 'leases.create', 'leases.show', 'leases.rent.create', 'leases.rent.edit', 'leases.rent.update', 'rent.month', 'rent.open', 'rent.quick-pay', 'payments.destroy', 'bank.index', 'bank.store', 'bank.transactions', 'bank.assign', 'bank.split', 'bank.item.remove', 'bank.accept', 'bank.edit', 'bank.update', 'bank.payment.update', 'bank.payment.remove', 'datev.rent', 'datev.rent.export', 'datev.rent.accounts'] as $name) {
            $this->check('Seite '.$name, Route::has($name));
        }
        foreach (['leases.index', 'leases.form', 'leases.show', 'leases.rent', 'leases.rent-edit', 'rent.month', 'rent.open', 'partials.rent-dashboard', 'partials.rent-styles', 'partials.bank-tabs', 'partials.bank-filter', 'bank.index', 'bank.transactions', 'bank.expenses', 'bank.split-dialog', 'bank.edit', 'datev.rent', 'datev.rent-accounts'] as $view) {
            $this->check('Vorlage '.$view, view()->exists($view));
        }
        // Eingebundene Vorlagen erben kein „use“ der Hauptseite: wer Money:: nutzt, muss die Klasse selbst einbinden
        foreach (glob(resource_path('views/{bank,leases,rent,partials}/*.blade.php'), GLOB_BRACE) ?: [] as $file) {
            $src = (string) file_get_contents($file);
            if (preg_match('/(?<![\\\\\w])Money::/', $src) && ! str_contains($src, 'App\Support\Money')) {
                $this->check('Vorlage '.basename($file).' bindet Money ein', false);
            }
        }

        $opts = ['2026-10' => 'Oktober 2026', '2026-09' => 'September 2026', '2026-08' => 'August 2026'];
        $this->check('Monatsauswahl Kontoauszüge (Pfeile)', \App\Support\Bank\Monatsfilter::neighbours($opts, '2026-09') === ['2026-08', '2026-10']
            && \App\Support\Bank\Monatsfilter::neighbours($opts, null) === [null, null]);

        // 3. Rechenlogik
        $rents = [['valid_from' => '2026-09-15', 'rent_cold' => '600', 'prepay_operating' => '150']];
        $lease = ['start' => '2026-09-15', 'end' => null, 'due_day' => 3, 'term_days' => null, 'vat_rate' => '0'];
        $c = RentMath::charge($lease, $rents, '2026-09-01');
        $this->check('Anteiliger Monat (16 von 30 Tagen = 400,00 €)', $c !== null && $c['total'] === '400.00' && $c['due_date'] === '2026-09-15');
        $c = RentMath::charge($lease, $rents, '2026-10-01');
        $this->check('Voller Monat, fällig am 3.', $c !== null && $c['total'] === '750.00' && $c['due_date'] === '2026-10-03');

        $g = RentMath::charge(['start' => '2026-10-01', 'end' => null, 'due_day' => 3, 'term_days' => null, 'vat_rate' => '19'],
            [['valid_from' => '2026-10-01', 'rent_cold' => '1000', 'prepay_operating' => '250']], '2026-10-01');
        $this->check('Gewerbe mit 19 % (1.250,00 + 237,50)', $g !== null && $g['vat_amount'] === '237.50' && $g['total'] === '1487.50');

        $m = RentMath::charge(['start' => '2026-10-20', 'end' => '2026-12-05', 'due_day' => 3, 'term_days' => 7, 'vat_rate' => '7'],
            [['valid_from' => '2026-10-20', 'rent_cold' => '900', 'furniture' => '100']], '2026-10-01');
        $this->check('Montage-Wohnung, im Voraus, Ziel 7 Tage', $m !== null && $m['due_date'] === '2026-10-27' && $m['total'] === '414.20');

        $a = RentMath::allocate(
            [['id' => 1, 'total' => '500'], ['id' => 2, 'total' => '750'], ['id' => 3, 'total' => '800']],
            [['id' => 10, 'amount' => '750'], ['id' => 11, 'amount' => '800'], ['id' => 12, 'amount' => '-800'], ['id' => 13, 'amount' => '900']]
        );
        $this->check('Zuordnung älteste zuerst, Rückbuchung verrechnet', $a['paid'] === [1 => '500.00', 2 => '750.00', 3 => '400.00'] && $a['credit'] === '0.00');

        $t = RentMath::allocate(
            [['id' => 1, 'total' => '900'], ['id' => 2, 'total' => '900'], ['id' => 3, 'total' => '50']],
            [['id' => 10, 'amount' => '900', 'target' => [2]], ['id' => 11, 'amount' => '50', 'target' => [3]], ['id' => 12, 'amount' => '100']]
        );
        $this->check('Aufteilung: Miete auf gewählten Monat, Gebühr auf Gebühr, Rest älteste zuerst', $t['paid'] === [1 => '100.00', 2 => '900.00', 3 => '50.00'] && $t['credit'] === '0.00');

        $i = RentMath::indexProposal('650.00', '117.40', '121.90', '2025-03-01', new DateTimeImmutable('2026-10-08'));
        $this->check('Indexanpassung (650,00 auf 674,91, ab 01.12.2026)', $i['new_cold'] === '674.91' && $i['earliest'] === '2026-12-01');

        // 4. Kontoauszug (Sparkasse CSV-CAMT, ANSI, mit vorgemerktem Umsatz)
        try {
            $csv = mb_convert_encoding(
                '"Auftragskonto";"Buchungstag";"Valutadatum";"Buchungstext";"Verwendungszweck";"Glaeubiger ID";"Mandatsreferenz";"Kundenreferenz (End-to-End)";"Sammlerreferenz";"Lastschrift Ursprungsbetrag";"Auslagenersatz Ruecklastschrift";"Beguenstigter/Zahlungspflichtiger";"Kontonummer/IBAN";"BIC (SWIFT-Code)";"Betrag";"Waehrung";"Info"'."\r\n"
                .'"DE02120300000000202051";"05.10.26";"05.10.26";"GUTSCHR. UEBERWEISUNG";"Miete für Oktober Musterstraße 1";"";"";"";"";"";"";"Max Mustermann";"DE02100100100006820101";"PBNKDEFF";"750,00";"EUR";"Umsatz gebucht"'."\r\n"
                .'"DE02120300000000202051";"05.10.26";"05.10.26";"GUTSCHR. UEBERWEISUNG";"Miete für Oktober Musterstraße 1";"";"";"";"";"";"";"Max Mustermann";"DE02100100100006820101";"PBNKDEFF";"750,00";"EUR";"Umsatz gebucht"'."\r\n"
                .'"DE02120300000000202051";"06.10.26";"06.10.26";"LASTSCHRIFT";"Strom";"";"";"";"";"";"";"Stadtwerke";"DE02300209000106531065";"";"-50,00";"EUR";"Umsatz vorgemerkt"'."\r\n",
                'Windows-1252', 'UTF-8');
            $p = \App\Support\Bank\BankCsvParser::parse($csv);
            $ok = $p['account_iban'] === 'DE02120300000000202051' && count($p['rows']) === 2 && $p['skipped'] === 1
                && $p['rows'][0]['amount'] === '750.00' && $p['rows'][0]['booking_date'] === '2026-10-05'
                && str_contains((string) $p['rows'][0]['purpose'], 'für') && $p['rows'][0]['dedupe_key'] !== $p['rows'][1]['dedupe_key'];
            $this->check('Kontoauszug lesen (Sparkasse CSV-CAMT, Umlaute, vorgemerkt, doppelte Zeilen)', $ok);
        } catch (Throwable $e) {
            $this->check('Kontoauszug lesen: '.$e->getMessage(), false);
        }
        $score = \App\Support\Bank\BankMatcher::score(
            ['amount' => '980.00', 'text' => 'Dana-Elena Petreanu Miete für Oktober Vogteistrasse 23', 'iban_hash' => 'x'],
            ['payer_hashes' => ['x'], 'names' => ['Petreanu, Dana-Elena'], 'streets' => [['vogteistrasse', '23']], 'amounts' => ['980.00']]
        );
        $this->check('Zuordnungsvorschlag (Konto, Name, Betrag, Anschrift = 130 Punkte)', $score['score'] === 130);

        $split = \App\Support\Datev\DatevRentBookings::split('1000.00', [['amount' => '991.66', 'charge' => ['rent_cold' => '600', 'prepay_operating' => '150', 'prepay_heating' => '83.33', 'vat_rate' => '19']]]);
        $this->check('DATEV Mieten: Aufteilung einer Zahlung (Miete, NK, Heizung, Rest)', $split === ['miete' => '722.34', 'nk' => '178.50', 'heiz' => '99.16']);

        // 5. Soll-Stellung für vorhandene Verträge
        try {
            $r = RentLedger::syncAll();
            $this->check('Soll-Stellung: '.$r['leases'].' Verträge geprüft, '.$r['created'].' neue Soll-Posten', true);
        } catch (Throwable $e) {
            $this->check('Soll-Stellung: '.$e->getMessage(), false);
        }

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
