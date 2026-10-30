<?php

namespace App\Support\ERechnung;

use App\Models\IssuedInvoice;
use DOMDocument;
use DOMXPath;

/** Daten für die lesbare Ansicht einer E-Rechnung, direkt aus der XML-Datei gelesen (Paket 37). */
class Ansicht
{
    public static function data(IssuedInvoice $inv): array
    {
        $doc = new DOMDocument();
        @$doc->loadXML($inv->xml);
        $x = new DOMXPath($doc);
        $x->registerNamespace('cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $x->registerNamespace('cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $v = fn (string $q) => (string) $x->evaluate('string('.$q.')');
        $party = fn (string $role) => [
            'name' => $v('//cac:'.$role.'/cac:Party/cac:PartyName/cbc:Name'),
            'street' => $v('//cac:'.$role.'/cac:Party/cac:PostalAddress/cbc:StreetName'),
            'zip' => $v('//cac:'.$role.'/cac:Party/cac:PostalAddress/cbc:PostalZone'),
            'city' => $v('//cac:'.$role.'/cac:Party/cac:PostalAddress/cbc:CityName'),
            'vat' => $v('//cac:'.$role.'/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="VAT"]/cbc:CompanyID'),
            'tax' => $v('//cac:'.$role.'/cac:Party/cac:PartyTaxScheme[cac:TaxScheme/cbc:ID="FC"]/cbc:CompanyID'),
            'email' => $v('//cac:'.$role.'/cac:Party/cbc:EndpointID'),
            'phone' => $v('//cac:'.$role.'/cac:Party/cac:Contact/cbc:Telephone'),
        ];

        return [
            'seller' => $party('AccountingSupplierParty'), 'buyer' => $party('AccountingCustomerParty'),
            'iban' => $v('//cac:PaymentMeans/cac:PayeeFinancialAccount/cbc:ID'), 'bic' => $v('//cac:PaymentMeans/cac:PayeeFinancialAccount/cac:FinancialInstitutionBranch/cbc:ID'),
            'reference' => $v('//cbc:BuyerReference'), 'note' => $v('/*/cbc:Note'), 'lines' => Rechnungen::linesOf($inv),
            'original' => $v('//cac:BillingReference/cac:InvoiceDocumentReference/cbc:ID'),
        ];
    }
}
