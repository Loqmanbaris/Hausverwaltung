<?php

namespace App\Support\ERechnung;

use DOMDocument;
use XMLWriter;

/**
 * Erzeugt eine E-Rechnung im Format XRechnung 3.0 (UBL 2.1) für die Vermietung (Paket 37).
 * Reine Funktionen ohne Datenbank. Beträge in Cent.
 *
 * Datenaufbau ($d):
 *  type (380 Rechnung | 381 Gutschrift), number, issued_on, due_on, period_from, period_to, buyer_reference, note, original (number, issued_on) bei Gutschrift
 *  seller: name, street, zip, city, vat_id, tax_number, contact_name, phone, email
 *  buyer: name, street, zip, city, email, vat_id
 *  iban, bic, account_holder
 *  rate (Prozent), lines: [['name' => string, 'net' => Cent], ...]
 */
class XRechnung
{
    public const CUSTOMIZATION = 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_3.0';

    public const PROFILE = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    /** Fehlende Pflichtangaben (nach EN 16931 und den deutschen Zusatzregeln BR-DE), in verständlichen Worten. */
    public static function missing(array $d): array
    {
        $e = [];
        $s = $d['seller'];
        $b = $d['buyer'];
        foreach (['name' => 'Name', 'street' => 'Straße', 'zip' => 'Postleitzahl', 'city' => 'Ort'] as $k => $l) {
            if (trim((string) ($s[$k] ?? '')) === '') {
                $e[] = 'Eigentümer: '.$l.' fehlt.';
            }
            if (trim((string) ($b[$k] ?? '')) === '') {
                $e[] = 'Mieter: '.$l.' fehlt.';
            }
        }
        if (trim((string) ($s['vat_id'] ?? '')) === '' && trim((string) ($s['tax_number'] ?? '')) === '') {
            $e[] = 'Eigentümer: Umsatzsteuer-Identifikationsnummer oder Steuernummer fehlt.';
        }
        if (trim((string) ($s['phone'] ?? '')) === '' || trim((string) ($s['email'] ?? '')) === '') {
            $e[] = 'Eigentümer: Telefonnummer und E-Mail-Adresse als Ansprechpartner fehlen.';
        }
        if (trim((string) ($d['iban'] ?? '')) === '') {
            $e[] = 'Eigentümer: Bankverbindung (IBAN) fehlt.';
        }
        if (trim((string) ($b['email'] ?? '')) === '') {
            $e[] = 'Mieter: E-Mail-Adresse fehlt (für die elektronische Adresse der Rechnung).';
        }
        if (($d['lines'] ?? []) === []) {
            $e[] = 'Die Rechnung hat keine Positionen.';
        }
        if (! in_array((int) ($d['type'] ?? 380), [380, 381], true)) {
            $e[] = 'Unbekannte Rechnungsart.';
        }

        return $e;
    }

    /** Summen in Cent: [net, vat, gross]. Die Steuer wird auf die Summe gerechnet und auf Cent gerundet (BR-S-08). */
    public static function totals(array $lines, float $rate): array
    {
        $net = (int) array_sum(array_column($lines, 'net'));
        $vat = (int) round($net * $rate / 100);

        return ['net' => $net, 'vat' => $vat, 'gross' => $net + $vat];
    }

    private static function amt(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    public static function build(array $d): string
    {
        $rate = (float) $d['rate'];
        $t = self::totals($d['lines'], $rate);
        $cat = $rate > 0 ? 'S' : 'E';
        $rateStr = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.') ?: '0';
        $s = $d['seller'];
        $b = $d['buyer'];

        $w = new XMLWriter();
        $w->openMemory();
        $w->setIndent(true);
        $w->setIndentString('  ');
        $w->startDocument('1.0', 'UTF-8');
        $w->startElementNs('ubl', 'Invoice', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $w->writeAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $w->writeAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');

        $el = function (string $name, string $value, array $attr = []) use ($w) {
            $w->startElement($name);
            foreach ($attr as $k => $v) {
                $w->writeAttribute($k, $v);
            }
            $w->text($value);
            $w->endElement();
        };

        $el('cbc:CustomizationID', self::CUSTOMIZATION);
        $el('cbc:ProfileID', self::PROFILE);
        $el('cbc:ID', (string) $d['number']);
        $el('cbc:IssueDate', (string) $d['issued_on']);
        $el('cbc:DueDate', (string) $d['due_on']);
        $el('cbc:InvoiceTypeCode', (string) ((int) ($d['type'] ?? 380)));
        if (trim((string) ($d['note'] ?? '')) !== '') {
            $el('cbc:Note', trim((string) $d['note']));
        }
        $el('cbc:DocumentCurrencyCode', 'EUR');
        $el('cbc:BuyerReference', (string) $d['buyer_reference']);

        if (! empty($d['period_from']) && ! empty($d['period_to'])) {
            $w->startElement('cac:InvoicePeriod');
            $el('cbc:StartDate', (string) $d['period_from']);
            $el('cbc:EndDate', (string) $d['period_to']);
            $w->endElement();
        }
        if (! empty($d['original'])) {
            $w->startElement('cac:BillingReference');
            $w->startElement('cac:InvoiceDocumentReference');
            $el('cbc:ID', (string) $d['original']['number']);
            $el('cbc:IssueDate', (string) $d['original']['issued_on']);
            $w->endElement();
            $w->endElement();
        }

        // Verkäufer
        $w->startElement('cac:AccountingSupplierParty');
        $w->startElement('cac:Party');
        $el('cbc:EndpointID', (string) $s['email'], ['schemeID' => 'EM']);
        $w->startElement('cac:PartyName');
        $el('cbc:Name', (string) $s['name']);
        $w->endElement();
        self::address($w, $el, $s);
        if (trim((string) ($s['vat_id'] ?? '')) !== '') {
            self::taxScheme($w, $el, (string) $s['vat_id'], 'VAT');
        }
        if (trim((string) ($s['tax_number'] ?? '')) !== '') {
            self::taxScheme($w, $el, (string) $s['tax_number'], 'FC');
        }
        $w->startElement('cac:PartyLegalEntity');
        $el('cbc:RegistrationName', (string) $s['name']);
        $w->endElement();
        $w->startElement('cac:Contact');
        $el('cbc:Name', (string) ($s['contact_name'] ?: $s['name']));
        $el('cbc:Telephone', (string) $s['phone']);
        $el('cbc:ElectronicMail', (string) $s['email']);
        $w->endElement();
        $w->endElement();
        $w->endElement();

        // Käufer
        $w->startElement('cac:AccountingCustomerParty');
        $w->startElement('cac:Party');
        $el('cbc:EndpointID', (string) $b['email'], ['schemeID' => 'EM']);
        $w->startElement('cac:PartyName');
        $el('cbc:Name', (string) $b['name']);
        $w->endElement();
        self::address($w, $el, $b);
        if (trim((string) ($b['vat_id'] ?? '')) !== '') {
            self::taxScheme($w, $el, (string) $b['vat_id'], 'VAT');
        }
        $w->startElement('cac:PartyLegalEntity');
        $el('cbc:RegistrationName', (string) $b['name']);
        $w->endElement();
        $w->endElement();
        $w->endElement();

        // Zahlung
        $w->startElement('cac:PaymentMeans');
        $el('cbc:PaymentMeansCode', '58');
        $w->startElement('cac:PayeeFinancialAccount');
        $el('cbc:ID', preg_replace('/\s+/', '', (string) $d['iban']) ?? '');
        if (trim((string) ($d['account_holder'] ?? '')) !== '') {
            $el('cbc:Name', (string) $d['account_holder']);
        }
        if (trim((string) ($d['bic'] ?? '')) !== '') {
            $w->startElement('cac:FinancialInstitutionBranch');
            $el('cbc:ID', preg_replace('/\s+/', '', (string) $d['bic']) ?? '');
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
        $w->startElement('cac:PaymentTerms');
        $el('cbc:Note', 'Zahlbar bis '.date('d.m.Y', strtotime((string) $d['due_on'])).' ohne Abzug.');
        $w->endElement();

        // Steuer
        $w->startElement('cac:TaxTotal');
        $el('cbc:TaxAmount', self::amt($t['vat']), ['currencyID' => 'EUR']);
        $w->startElement('cac:TaxSubtotal');
        $el('cbc:TaxableAmount', self::amt($t['net']), ['currencyID' => 'EUR']);
        $el('cbc:TaxAmount', self::amt($t['vat']), ['currencyID' => 'EUR']);
        $w->startElement('cac:TaxCategory');
        $el('cbc:ID', $cat);
        $el('cbc:Percent', $rateStr);
        if ($cat === 'E') {
            $el('cbc:TaxExemptionReason', 'Umsatzsteuerfreie Vermietung nach § 4 Nr. 12 UStG');
        }
        $w->startElement('cac:TaxScheme');
        $el('cbc:ID', 'VAT');
        $w->endElement();
        $w->endElement();
        $w->endElement();
        $w->endElement();

        $w->startElement('cac:LegalMonetaryTotal');
        $el('cbc:LineExtensionAmount', self::amt($t['net']), ['currencyID' => 'EUR']);
        $el('cbc:TaxExclusiveAmount', self::amt($t['net']), ['currencyID' => 'EUR']);
        $el('cbc:TaxInclusiveAmount', self::amt($t['gross']), ['currencyID' => 'EUR']);
        $el('cbc:PayableAmount', self::amt($t['gross']), ['currencyID' => 'EUR']);
        $w->endElement();

        foreach (array_values($d['lines']) as $i => $line) {
            $w->startElement('cac:InvoiceLine');
            $el('cbc:ID', (string) ($i + 1));
            $el('cbc:InvoicedQuantity', '1', ['unitCode' => 'C62']);
            $el('cbc:LineExtensionAmount', self::amt((int) $line['net']), ['currencyID' => 'EUR']);
            $w->startElement('cac:Item');
            $el('cbc:Name', (string) $line['name']);
            $w->startElement('cac:ClassifiedTaxCategory');
            $el('cbc:ID', $cat);
            $el('cbc:Percent', $rateStr);
            $w->startElement('cac:TaxScheme');
            $el('cbc:ID', 'VAT');
            $w->endElement();
            $w->endElement();
            $w->endElement();
            $w->startElement('cac:Price');
            $el('cbc:PriceAmount', self::amt((int) $line['net']), ['currencyID' => 'EUR']);
            $w->endElement();
            $w->endElement();
        }

        $w->endElement();
        $w->endDocument();

        return $w->outputMemory();
    }

    private static function address(XMLWriter $w, callable $el, array $p): void
    {
        $w->startElement('cac:PostalAddress');
        $el('cbc:StreetName', (string) $p['street']);
        $el('cbc:CityName', (string) $p['city']);
        $el('cbc:PostalZone', (string) $p['zip']);
        $w->startElement('cac:Country');
        $el('cbc:IdentificationCode', 'DE');
        $w->endElement();
        $w->endElement();
    }

    private static function taxScheme(XMLWriter $w, callable $el, string $id, string $scheme): void
    {
        $w->startElement('cac:PartyTaxScheme');
        $el('cbc:CompanyID', trim($id));
        $w->startElement('cac:TaxScheme');
        $el('cbc:ID', $scheme);
        $w->endElement();
        $w->endElement();
    }

    /**
     * Prüft die XML-Datei gegen das UBL-2.1-Schema. Gibt Fehler im Klartext zurück, leer = gültig.
     * Die Geschäftsregeln der KoSIT (Schematron) prüft das CRM nicht. Vor dem ersten Einsatz bitte mit einem Prüfdienst testen.
     *
     * @return array<int, string>
     */
    public static function schemaErrors(string $xml): array
    {
        try {
            $xsd = UblSchema::mainPath();
        } catch (\Throwable $e) {
            return ['Das Schema für die Prüfung konnte nicht bereitgestellt werden: '.$e->getMessage()];
        }
        $prev = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $doc = new DOMDocument();
        $errors = [];
        if (! @$doc->loadXML($xml)) {
            $errors[] = 'Die Datei ist kein gültiges XML.';
        } elseif (! @$doc->schemaValidate($xsd)) {
            foreach (array_slice(libxml_get_errors(), 0, 5) as $e) {
                $errors[] = trim($e->message);
            }
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        return $errors;
    }
}
