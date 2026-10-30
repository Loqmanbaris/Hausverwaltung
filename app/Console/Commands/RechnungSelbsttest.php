<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Support\ERechnung\XRechnung;
use App\Support\Rechnung\Berechnung;
use App\Support\Rechnung\Pflicht;
use App\Support\Rechnung\RechnungXml;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 45 (Rechnungen schreiben). */
class RechnungSelbsttest extends Command
{
    protected $signature = 'rechnung:selbsttest';

    protected $description = 'Selbsttest Rechnungen schreiben (Paket 45)';

    public function handle(): int
    {
        $seller = ['name' => 'Test Hausverwaltung', 'street' => 'Weg 1', 'zip' => '38440', 'city' => 'Wolfsburg', 'phone' => '05361 1', 'email' => 'a@b.de', 'vat_id' => 'DE123456789',
            'tax_number' => '', 'iban' => 'DE89370400440532013000', 'bic' => '', 'holder' => 'Test', 'note' => '', 'tax_mode' => 'regel', 'due_days' => 14];
        $inv = new Invoice(['number' => 'RE-2026-0001', 'type' => '380', 'buyer' => ['name' => 'Muster GmbH', 'street' => 'Str. 1', 'zip' => '38100', 'city' => 'Braunschweig', 'email' => 'x@y.de', 'vat_id' => '', 'business' => true],
            'seller' => $seller, 'issued_on' => '2026-11-02', 'due_on' => '2026-11-16', 'delivery_from' => '2026-10-01', 'delivery_to' => '2026-10-31']);
        $mk = fn (string $name, string $qty, string $price, int $rate, ?string $ex = null) => new InvoiceLine(['name' => $name, 'quantity' => $qty, 'unit' => 'C62', 'unit_net' => $price, 'vat_rate' => $rate, 'exemption' => $ex]);
        $inv->setRelation('lines', collect([$mk('Honorar', '1', '250.00', 19), $mk('Beherbergung', '14', '45.50', 7), $mk('Auslagen', '1', '20.00', 0, 'Steuerfrei nach § 4 Nr. 12 UStG (Vermietung)')]));
        $xml = RechnungXml::build($inv);
        $sum = Berechnung::summary([['net' => 25000, 'rate' => 19], ['net' => 63700, 'rate' => 7], ['net' => 2000, 'rate' => 0, 'exemption' => 'x']]);
        $bad = new Invoice(['buyer' => ['name' => '', 'business' => true], 'delivery_from' => null]);
        $bad->setRelation('lines', collect([$mk('', '0', '1', 5), $mk('Frei', '1', '1', 0)]));
        $problems = implode(' | ', Pflicht::check($bad, $seller, '2026-11-02'));
        $checks = [
            'Tabellen' => Schema::hasTable('invoices') && Schema::hasTable('invoice_lines'),
            'Steuer je Satz' => $sum['groups'][0]['vat'] === 4750 && $sum['groups'][1]['vat'] === 4459 && $sum['net'] === 90700 && $sum['gross'] === 99909,
            'Runden auf Cent' => Berechnung::lineNet(1, '1.005') === 101 && Berechnung::lineNet('2.5', '0.33') === 83,
            'IBAN und USt-ID prüfen' => Berechnung::validIban('DE89 3704 0044 0532 0130 00') && ! Berechnung::validIban('DE89370400440532013001') && Berechnung::validVatId('DE123456789') && ! Berechnung::validVatId('DE1234'),
            'E-Rechnung mit drei Steuersätzen gültig' => XRechnung::schemaErrors($xml) === [] && substr_count($xml, '<cac:TaxSubtotal>') === 3,
            'Pflichtangaben werden geprüft' => str_contains($problems, 'Empfänger: Name fehlt') && str_contains($problems, 'Leistungsdatum') && str_contains($problems, 'Steuersatz muss 19, 7 oder 0') && str_contains($problems, 'Grund der Steuerbefreiung'),
            'Seiten' => Route::has('invoices.index') && Route::has('invoices.finalize') && Route::has('invoices.credit') && Route::has('invoices.pdf') && Route::has('invoices.xml'),
            'Ansichten' => view()->exists('invoices.index') && view()->exists('invoices.form') && view()->exists('invoices.show') && view()->exists('invoices.print') && view()->exists('invoices.settings'),
        ];
        $this->line('Selbsttest Paket 45 (Rechnungen schreiben)');
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
