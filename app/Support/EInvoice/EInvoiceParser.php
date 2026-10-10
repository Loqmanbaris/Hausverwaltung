<?php

namespace App\Support\EInvoice;

use DateTimeImmutable;
use DOMDocument;
use DOMNode;
use DOMXPath;

/**
 * Liest die für die Belegverwaltung wichtigen Angaben aus einer E-Rechnung.
 * Unterstützt XRechnung und ZUGFeRD/Factur-X (UN/CEFACT CII) sowie XRechnung als UBL (Invoice, CreditNote).
 * Es werden nur Angaben gelesen, nichts wird ausgeführt oder aus dem Netz nachgeladen.
 */
class EInvoiceParser
{
    /** Rechnungsarten (Code 1001), die eine Gutschrift bedeuten. */
    private const CREDIT_CODES = ['81', '83', '261', '262', '296', '308', '381', '396', '532'];

    private DOMXPath $xp;

    private function __construct(private DOMDocument $doc)
    {
        $this->xp = new DOMXPath($doc);
    }

    /**
     * @return array{
     *   syntax: string, format: string, invoice_number: ?string, issue_date: ?string, due_date: ?string,
     *   currency: ?string, is_credit: bool, gross: ?string, net: ?string, tax: ?string, buyer_name: ?string,
     *   seller: array{name: ?string, street: ?string, zip: ?string, city: ?string, email: ?string, phone: ?string, vat_id: ?string},
     *   warnings: string[]
     * }
     *
     * @throws EInvoiceException
     */
    public static function parse(string $xml): array
    {
        $xml = ltrim($xml, "\xEF\xBB\xBF \t\r\n");
        if ($xml === '' || $xml[0] !== '<') {
            throw new EInvoiceException('Die Datei ist keine XML-Datei.');
        }
        // UTF-16 enthält Nullbytes und würde die Prüfung unten umgehen. E-Rechnungen sind UTF-8.
        if (str_contains($xml, "\0")) {
            throw new EInvoiceException('Die XML-Datei hat eine ungewöhnliche Zeichenkodierung (kein UTF-8) und wird nicht gelesen.');
        }
        // Eigene Entitäten und Dokumenttypen brauchen E-Rechnungen nicht. Das verhindert Angriffe über XML.
        if (preg_match('/<!(DOCTYPE|ENTITY)/i', $xml) === 1) {
            throw new EInvoiceException('Die XML-Datei enthält unzulässige Anweisungen und wird nicht gelesen.');
        }

        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded || $doc->documentElement === null) {
            throw new EInvoiceException('Die XML-Datei ist beschädigt und kann nicht gelesen werden.');
        }

        $root = $doc->documentElement->localName;
        $parser = new self($doc);

        return match ($root) {
            'CrossIndustryInvoice' => $parser->cii(),
            'Invoice', 'CreditNote' => $parser->ubl($root),
            'CrossIndustryDocument' => throw new EInvoiceException('Das ist eine Rechnung im veralteten Format ZUGFeRD 1.0. Bitte als normalen Beleg (Foto oder PDF) erfassen.'),
            default => throw new EInvoiceException('Diese XML-Datei ist keine E-Rechnung (XRechnung oder ZUGFeRD).'),
        };
    }

    // ---------------------------------------------------------------- UBL

    private function ubl(string $root): array
    {
        $r = $this->doc->documentElement;
        $warnings = [];

        $currency = $this->upper($this->text($r, 'DocumentCurrencyCode'));
        $type = $this->text($r, $root === 'CreditNote' ? 'CreditNoteTypeCode' : 'InvoiceTypeCode');
        $isCredit = $root === 'CreditNote' || in_array((string) $type, self::CREDIT_CODES, true);

        $party = 'AccountingSupplierParty/Party';
        $vat = null;
        foreach ($this->nodes($r, $party.'/PartyTaxScheme') as $scheme) {
            if ($this->upper($this->text($scheme, 'TaxScheme/ID')) === 'VAT') {
                $vat = $this->text($scheme, 'CompanyID');
                break;
            }
        }

        $gross = $this->amount($this->text($r, 'LegalMonetaryTotal/TaxInclusiveAmount') ?? $this->text($r, 'LegalMonetaryTotal/PayableAmount'));
        $net = $this->amount($this->text($r, 'LegalMonetaryTotal/TaxExclusiveAmount'));

        $tax = null;
        foreach ($this->nodes($r, 'TaxTotal') as $total) {
            $value = $this->amount($this->text($total, 'TaxAmount'));
            $cur = $this->upper($this->attr($total, 'TaxAmount', 'currencyID'));
            if ($value !== null && ($tax === null || $cur === $currency)) {
                $tax = $value;
                if ($cur === $currency) {
                    break;
                }
            }
        }

        $customization = mb_strtolower((string) $this->text($r, 'CustomizationID'));
        $format = str_contains($customization, 'xrechnung') ? 'XRechnung'
            : (str_contains($customization, 'peppol') ? 'Peppol BIS (UBL)' : 'E-Rechnung (UBL)');

        return $this->finish([
            'syntax' => 'UBL',
            'format' => $format,
            'invoice_number' => $this->text($r, 'ID'),
            'issue_date' => $this->date($this->text($r, 'IssueDate')),
            'due_date' => $this->date($this->text($r, 'DueDate') ?? $this->text($r, 'PaymentMeans/PaymentDueDate')),
            'currency' => $currency,
            'is_credit' => $isCredit,
            'gross' => $gross,
            'net' => $net,
            'tax' => $tax,
            'buyer_name' => $this->text($r, 'AccountingCustomerParty/Party/PartyLegalEntity/RegistrationName')
                ?? $this->text($r, 'AccountingCustomerParty/Party/PartyName/Name'),
            'seller' => [
                'name' => $this->text($r, $party.'/PartyLegalEntity/RegistrationName') ?? $this->text($r, $party.'/PartyName/Name'),
                'street' => $this->text($r, $party.'/PostalAddress/StreetName'),
                'zip' => $this->text($r, $party.'/PostalAddress/PostalZone'),
                'city' => $this->text($r, $party.'/PostalAddress/CityName'),
                'email' => $this->text($r, $party.'/Contact/ElectronicMail'),
                'phone' => $this->text($r, $party.'/Contact/Telephone'),
                'vat_id' => $vat,
            ],
            'warnings' => $warnings,
        ]);
    }

    // ---------------------------------------------------------------- CII (XRechnung CII, ZUGFeRD, Factur-X)

    private function cii(): array
    {
        $r = $this->doc->documentElement;

        $agreement = 'SupplyChainTradeTransaction/ApplicableHeaderTradeAgreement';
        $settlement = 'SupplyChainTradeTransaction/ApplicableHeaderTradeSettlement';
        $sum = $settlement.'/SpecifiedTradeSettlementHeaderMonetarySummation';
        $seller = $agreement.'/SellerTradeParty';

        $currency = $this->upper($this->text($r, $settlement.'/InvoiceCurrencyCode'));
        $type = $this->text($r, 'ExchangedDocument/TypeCode');

        $vat = null;
        foreach ($this->nodes($r, $seller.'/SpecifiedTaxRegistration') as $reg) {
            if ($this->upper($this->attr($reg, 'ID', 'schemeID')) === 'VA') {
                $vat = $this->text($reg, 'ID');
                break;
            }
        }

        $gross = $this->amount($this->text($r, $sum.'/GrandTotalAmount') ?? $this->text($r, $sum.'/DuePayableAmount'));
        $net = $this->amount($this->text($r, $sum.'/TaxBasisTotalAmount'));

        $tax = null;
        foreach ($this->nodes($r, $sum.'/TaxTotalAmount') as $node) {
            $value = $this->amount(trim($node->textContent));
            $cur = $this->upper($node instanceof \DOMElement ? $node->getAttribute('currencyID') : null);
            if ($value !== null && ($tax === null || $cur === $currency)) {
                $tax = $value;
                if ($cur === $currency) {
                    break;
                }
            }
        }

        $guideline = mb_strtolower((string) $this->text($r, 'ExchangedDocumentContext/GuidelineSpecifiedDocumentContextParameter/ID'));
        $format = str_contains($guideline, 'xrechnung') ? 'XRechnung'
            : ((str_contains($guideline, 'zugferd') || str_contains($guideline, 'factur-x') || str_contains($guideline, 'facturx')) ? 'ZUGFeRD / Factur-X' : 'E-Rechnung (CII)');

        $email = $this->text($r, $seller.'/DefinedTradeContact/EmailURIUniversalCommunication/URIID')
            ?? $this->text($r, $seller.'/URIUniversalCommunication/URIID');

        return $this->finish([
            'syntax' => 'CII',
            'format' => $format,
            'invoice_number' => $this->text($r, 'ExchangedDocument/ID'),
            'issue_date' => $this->date($this->text($r, 'ExchangedDocument/IssueDateTime/DateTimeString')),
            'due_date' => $this->date($this->text($r, $settlement.'/SpecifiedTradePaymentTerms/DueDateDateTime/DateTimeString')),
            'currency' => $currency,
            'is_credit' => in_array((string) $type, self::CREDIT_CODES, true),
            'gross' => $gross,
            'net' => $net,
            'tax' => $tax,
            'buyer_name' => $this->text($r, $agreement.'/BuyerTradeParty/Name'),
            'seller' => [
                'name' => $this->text($r, $seller.'/Name'),
                'street' => $this->text($r, $seller.'/PostalTradeAddress/LineOne'),
                'zip' => $this->text($r, $seller.'/PostalTradeAddress/PostcodeCode'),
                'city' => $this->text($r, $seller.'/PostalTradeAddress/CityName'),
                'email' => $email,
                'phone' => $this->text($r, $seller.'/DefinedTradeContact/TelephoneUniversalCommunication/CompleteNumber'),
                'vat_id' => $vat,
            ],
            'warnings' => [],
        ]);
    }

    // ---------------------------------------------------------------- gemeinsam

    /** Gutschriften werden negativ geführt, und die Summen werden auf Stimmigkeit geprüft. */
    private function finish(array $d): array
    {
        if ($d['is_credit']) {
            foreach (['gross', 'net', 'tax'] as $key) {
                if ($d[$key] !== null && (float) $d[$key] > 0) {
                    $d[$key] = number_format(-1 * (float) $d[$key], 2, '.', '');
                }
            }
        }

        if ($d['gross'] !== null && $d['net'] !== null && $d['tax'] !== null
            && abs((float) $d['net'] + (float) $d['tax'] - (float) $d['gross']) > 0.02) {
            $d['warnings'][] = 'Die Summen in der Rechnung passen nicht zusammen (netto plus Steuer ergibt nicht brutto). Bitte Betrag prüfen.';
        }

        if ($d['gross'] === null) {
            $d['warnings'][] = 'In der Rechnung wurde kein Gesamtbetrag gefunden.';
        }

        return $d;
    }

    /** Pfad aus Elementnamen („A/B/C“, ohne Namensraum) als XPath. */
    private function path(string $path): string
    {
        $parts = array_map(fn ($p) => "*[local-name()='".$p."']", explode('/', $path));

        return './'.implode('/', $parts);
    }

    private function nodes(DOMNode $ctx, string $path): array
    {
        $list = $this->xp->query($this->path($path), $ctx);

        return $list === false ? [] : iterator_to_array($list);
    }

    private function text(DOMNode $ctx, string $path): ?string
    {
        $list = $this->xp->query($this->path($path), $ctx);
        if ($list === false || $list->length === 0) {
            return null;
        }
        $value = trim((string) preg_replace('/\s+/u', ' ', $list->item(0)->textContent));

        return $value === '' ? null : $value;
    }

    private function attr(DOMNode $ctx, string $path, string $name): ?string
    {
        $list = $this->xp->query($this->path($path), $ctx);
        if ($list === false || $list->length === 0 || ! $list->item(0) instanceof \DOMElement) {
            return null;
        }
        $value = trim($list->item(0)->getAttribute($name));

        return $value === '' ? null : $value;
    }

    private function upper(?string $value): ?string
    {
        return $value === null ? null : mb_strtoupper(trim($value));
    }

    /** „1234.56“ wird zu „1234.56“ mit zwei Stellen, alles andere zu null. */
    private function amount(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '' || preg_match('/^-?\d{1,12}(\.\d+)?$/', $value) !== 1) {
            return null;
        }

        return number_format(round((float) $value, 2), 2, '.', '');
    }

    /** 2026-10-08 oder 20261008 (auch mit Uhrzeit) wird zu 2026-10-08. */
    private function date(?string $value): ?string
    {
        $value = trim((string) $value);
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) !== 1 && preg_match('/^(\d{4})(\d{2})(\d{2})/', $value, $m) !== 1) {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $m[1].'-'.$m[2].'-'.$m[3]);

        return $date !== false && $date->format('Y-m-d') === $m[1].'-'.$m[2].'-'.$m[3] ? $date->format('Y-m-d') : null;
    }
}
