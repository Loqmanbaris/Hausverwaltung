<?php

namespace App\Support\Rechnung;

use App\Models\Invoice;
use App\Support\ERechnung\XRechnung;
use XMLWriter;

/**
 * XRechnung 3.0 (UBL 2.1) für beliebige Rechnungen der Hausverwaltung (Paket 45): mehrere Positionen, Mengen, mehrere Steuersätze,
 * Steuerbefreiung mit Grund. Die Prüfung gegen das Schema übernimmt XRechnung::schemaErrors().
 */
class RechnungXml
{
    private static function amt(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private static function qty(float $q): string
    {
        return rtrim(rtrim(number_format($q, 3, '.', ''), '0'), '.') ?: '0';
    }

    private static function rate(float $r): string
    {
        return rtrim(rtrim(number_format($r, 2, '.', ''), '0'), '.') ?: '0';
    }

    public static function build(Invoice $inv): string
    {
        $inv->loadMissing('lines');
        $s = (array) $inv->seller;
        $b = (array) $inv->buyer;
        $lines = [];
        foreach ($inv->lines as $l) {
            $lines[] = ['model' => $l, 'net' => Berechnung::lineNet((float) $l->quantity, (float) $l->unit_net), 'rate' => (float) $l->vat_rate, 'exemption' => $l->exemption];
        }
        $sum = Berechnung::summary($lines);
        $type = (int) $inv->type === 381 ? 381 : 380;
        $issued = $inv->issued_on->format('Y-m-d');
        $due = ($inv->due_on ?? $inv->issued_on)->format('Y-m-d');
        $note = trim((string) $inv->note);
        if (($s['tax_mode'] ?? 'regel') === 'klein') {
            $note = trim($note."\n".Berechnung::SMALL_BUSINESS);
        }

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

        $el('cbc:CustomizationID', XRechnung::CUSTOMIZATION);
        $el('cbc:ProfileID', XRechnung::PROFILE);
        $el('cbc:ID', (string) $inv->number);
        $el('cbc:IssueDate', $issued);
        $el('cbc:DueDate', $due);
        $el('cbc:InvoiceTypeCode', (string) $type);
        if ($note !== '') {
            $el('cbc:Note', $note);
        }
        $el('cbc:DocumentCurrencyCode', 'EUR');
        $el('cbc:BuyerReference', trim((string) $inv->buyer_reference) !== '' ? trim((string) $inv->buyer_reference) : (string) $inv->number);

        $from = $inv->delivery_from?->format('Y-m-d');
        $to = $inv->delivery_to?->format('Y-m-d');
        if ($from !== null && $to !== null && $to !== $from) {
            $w->startElement('cac:InvoicePeriod');
            $el('cbc:StartDate', $from);
            $el('cbc:EndDate', $to);
            $w->endElement();
        }
        if ($inv->original_id) {
            $orig = Invoice::query()->find($inv->original_id);
            if ($orig !== null && $orig->number !== null) {
                $w->startElement('cac:BillingReference');
                $w->startElement('cac:InvoiceDocumentReference');
                $el('cbc:ID', (string) $orig->number);
                $el('cbc:IssueDate', $orig->issued_on->format('Y-m-d'));
                $w->endElement();
                $w->endElement();
            }
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
        $el('cbc:Name', (string) $s['name']);
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

        // Leistungsdatum (bei einem einzelnen Tag)
        if ($from !== null && ($to === null || $to === $from)) {
            $w->startElement('cac:Delivery');
            $el('cbc:ActualDeliveryDate', $from);
            $w->endElement();
        }

        // Zahlung
        $w->startElement('cac:PaymentMeans');
        $el('cbc:PaymentMeansCode', '58');
        $w->startElement('cac:PayeeFinancialAccount');
        $el('cbc:ID', (string) $s['iban']);
        if (trim((string) ($s['holder'] ?? '')) !== '') {
            $el('cbc:Name', (string) $s['holder']);
        }
        if (trim((string) ($s['bic'] ?? '')) !== '') {
            $w->startElement('cac:FinancialInstitutionBranch');
            $el('cbc:ID', (string) $s['bic']);
            $w->endElement();
        }
        $w->endElement();
        $w->endElement();
        $w->startElement('cac:PaymentTerms');
        $el('cbc:Note', 'Zahlbar bis '.date('d.m.Y', strtotime($due)).' ohne Abzug.');
        $w->endElement();

        // Steuer je Satz
        $w->startElement('cac:TaxTotal');
        $el('cbc:TaxAmount', self::amt($sum['vat']), ['currencyID' => 'EUR']);
        foreach ($sum['groups'] as $g) {
            $w->startElement('cac:TaxSubtotal');
            $el('cbc:TaxableAmount', self::amt($g['net']), ['currencyID' => 'EUR']);
            $el('cbc:TaxAmount', self::amt($g['vat']), ['currencyID' => 'EUR']);
            $w->startElement('cac:TaxCategory');
            $el('cbc:ID', $g['rate'] > 0 ? 'S' : 'E');
            $el('cbc:Percent', self::rate($g['rate']));
            if ($g['rate'] <= 0) {
                $el('cbc:TaxExemptionReason', (string) $g['exemption']);
            }
            $w->startElement('cac:TaxScheme');
            $el('cbc:ID', 'VAT');
            $w->endElement();
            $w->endElement();
            $w->endElement();
        }
        $w->endElement();

        $w->startElement('cac:LegalMonetaryTotal');
        $el('cbc:LineExtensionAmount', self::amt($sum['net']), ['currencyID' => 'EUR']);
        $el('cbc:TaxExclusiveAmount', self::amt($sum['net']), ['currencyID' => 'EUR']);
        $el('cbc:TaxInclusiveAmount', self::amt($sum['gross']), ['currencyID' => 'EUR']);
        $el('cbc:PayableAmount', self::amt($sum['gross']), ['currencyID' => 'EUR']);
        $w->endElement();

        foreach ($lines as $i => $ln) {
            $l = $ln['model'];
            $cat = $ln['rate'] > 0 ? 'S' : 'E';
            $w->startElement('cac:InvoiceLine');
            $el('cbc:ID', (string) ($i + 1));
            $el('cbc:InvoicedQuantity', self::qty((float) $l->quantity), ['unitCode' => (string) $l->unit]);
            $el('cbc:LineExtensionAmount', self::amt($ln['net']), ['currencyID' => 'EUR']);
            $w->startElement('cac:Item');
            $el('cbc:Name', (string) $l->name);
            $w->startElement('cac:ClassifiedTaxCategory');
            $el('cbc:ID', $cat);
            $el('cbc:Percent', self::rate($ln['rate']));
            $w->startElement('cac:TaxScheme');
            $el('cbc:ID', 'VAT');
            $w->endElement();
            $w->endElement();
            $w->endElement();
            $w->startElement('cac:Price');
            $el('cbc:PriceAmount', number_format((float) $l->unit_net, 2, '.', ''), ['currencyID' => 'EUR']);
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
}
