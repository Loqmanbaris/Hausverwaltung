<?php

namespace App\Console\Commands;

use App\Support\ERechnung\XRechnung;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 37 (E-Rechnung). */
class ERechnungSelbsttest extends Command
{
    protected $signature = 'erechnung:selbsttest';

    protected $description = 'Selbsttest E-Rechnung (Paket 37)';

    public function handle(): int
    {
        $d = [
            'type' => 380, 'number' => 'R2027-0001', 'issued_on' => '2027-01-02', 'due_on' => '2027-01-03', 'period_from' => '2027-01-01', 'period_to' => '2027-01-31',
            'buyer_reference' => 'Mietvertrag 1', 'note' => 'Miete Januar 2027',
            'seller' => ['name' => 'Eigentümer GmbH', 'street' => 'Weg 1', 'zip' => '10115', 'city' => 'Berlin', 'vat_id' => 'DE123456789', 'tax_number' => '', 'contact_name' => 'Verwaltung', 'phone' => '030 123456', 'email' => 'info@example.de'],
            'buyer' => ['name' => 'Firma Müller & Söhne GmbH', 'street' => 'Str. 2', 'zip' => '10117', 'city' => 'Berlin', 'email' => 'buchhaltung@example.de', 'vat_id' => ''],
            'iban' => 'DE02120300000000202051', 'bic' => 'BYLADEM1001', 'account_holder' => 'Eigentümer GmbH', 'rate' => 19.0,
            'lines' => [['name' => 'Kaltmiete 01/2027', 'net' => 100000], ['name' => 'Nebenkosten-Vorauszahlung 01/2027', 'net' => 25000]],
        ];
        $xml = XRechnung::build($d);
        $credit = XRechnung::build(['type' => 381, 'number' => 'G2027-0001', 'original' => ['number' => 'R2027-0001', 'issued_on' => '2027-01-02']] + $d);
        $totals = XRechnung::totals($d['lines'], 19.0);
        $bad = $d;
        $bad['iban'] = '';
        $bad['seller']['phone'] = '';
        $checks = [
            'Tabelle' => Schema::hasTable('issued_invoices'),
            'Schema vorhanden' => is_file(\App\Support\ERechnung\UblSchema::mainPath()),
            'Rechnung besteht die Schema-Prüfung' => XRechnung::schemaErrors($xml) === [],
            'Gutschrift besteht die Schema-Prüfung' => XRechnung::schemaErrors($credit) === [],
            'Kaputte Datei wird erkannt' => XRechnung::schemaErrors(str_replace('<cbc:IssueDate>', '<cbc:Unsinn>', $xml)) !== [],
            'Summen' => $totals === ['net' => 125000, 'vat' => 23750, 'gross' => 148750],
            'Fehlende Pflichtangaben' => XRechnung::missing($d) === [] && count(XRechnung::missing($bad)) === 2,
            'Seiten' => Route::has('erechnung.index') && Route::has('erechnung.xml') && Route::has('erechnung.credit'),
            'Ansichten' => view()->exists('erechnung.index') && view()->exists('erechnung.print'),
        ];
        $this->line('Selbsttest Paket 37 (E-Rechnung)');
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
