<?php

namespace App\Support\Rechnung;

use App\Support\Settings;

/** Firmendaten der Hausverwaltung als Rechnungsaussteller (Paket 45). Gespeichert in den Einstellungen, Vorbelegung aus dem Impressum. */
class Aussteller
{
    public const FIELDS = [
        'inv_name' => 'Name oder Firma', 'inv_street' => 'Straße und Hausnummer', 'inv_zip' => 'PLZ', 'inv_city' => 'Ort',
        'inv_phone' => 'Telefon', 'inv_email' => 'E-Mail-Adresse', 'inv_vat_id' => 'Umsatzsteuer-ID', 'inv_tax_number' => 'Steuernummer',
        'inv_iban' => 'IBAN', 'inv_bic' => 'BIC', 'inv_holder' => 'Kontoinhaber', 'inv_due_days' => 'Zahlungsziel in Tagen',
        'inv_tax_mode' => 'Steuerart', 'inv_note' => 'Hinweistext auf jeder Rechnung',
    ];

    /** @return array<string, string> */
    public static function data(): array
    {
        $imprint = ['inv_name' => 'imprint_name', 'inv_street' => 'imprint_street', 'inv_zip' => 'imprint_zip', 'inv_city' => 'imprint_city',
            'inv_phone' => 'imprint_phone', 'inv_email' => 'imprint_email', 'inv_vat_id' => 'imprint_vat_id'];
        $out = [];
        foreach (array_keys(self::FIELDS) as $k) {
            $default = isset($imprint[$k]) ? Settings::get($imprint[$k], '') : '';
            $out[$k] = trim((string) Settings::get($k, $default));
        }
        $out['inv_tax_mode'] = $out['inv_tax_mode'] === 'klein' ? 'klein' : 'regel';
        $out['inv_due_days'] = (string) max(0, min(180, (int) ($out['inv_due_days'] !== '' ? $out['inv_due_days'] : 14)));

        return $out;
    }

    /** Stand für das Festschreiben: genau das, was auf der Rechnung steht. */
    public static function snapshot(): array
    {
        $d = self::data();

        return [
            'name' => $d['inv_name'], 'street' => $d['inv_street'], 'zip' => $d['inv_zip'], 'city' => $d['inv_city'], 'phone' => $d['inv_phone'],
            'email' => $d['inv_email'], 'vat_id' => strtoupper(preg_replace('/\s+/', '', $d['inv_vat_id']) ?? ''), 'tax_number' => $d['inv_tax_number'],
            'iban' => strtoupper(preg_replace('/\s+/', '', $d['inv_iban']) ?? ''), 'bic' => strtoupper(preg_replace('/\s+/', '', $d['inv_bic']) ?? ''),
            'holder' => $d['inv_holder'] !== '' ? $d['inv_holder'] : $d['inv_name'], 'note' => $d['inv_note'], 'tax_mode' => $d['inv_tax_mode'],
            'due_days' => (int) $d['inv_due_days'],
        ];
    }

    /** Fehlende oder fehlerhafte Angaben in verständlichen Worten. */
    public static function issues(?array $s = null): array
    {
        $s ??= self::snapshot();
        $e = [];
        foreach (['name' => 'Name oder Firma', 'street' => 'Straße', 'zip' => 'PLZ', 'city' => 'Ort'] as $k => $l) {
            if (trim((string) ($s[$k] ?? '')) === '') {
                $e[] = 'Hausverwaltung: '.$l.' fehlt.';
            }
        }
        $vat = trim((string) ($s['vat_id'] ?? ''));
        $tax = trim((string) ($s['tax_number'] ?? ''));
        if ($vat === '' && $tax === '') {
            $e[] = 'Hausverwaltung: Umsatzsteuer-ID oder Steuernummer fehlt. Eine Rechnung braucht eine von beiden.';
        }
        if ($vat !== '' && ! Berechnung::validVatId($vat)) {
            $e[] = 'Hausverwaltung: Die Umsatzsteuer-ID sieht nicht richtig aus (richtig: DE und neun Ziffern).';
        }
        if (trim((string) ($s['phone'] ?? '')) === '' || trim((string) ($s['email'] ?? '')) === '') {
            $e[] = 'Hausverwaltung: Telefonnummer und E-Mail-Adresse fehlen (nötig als Ansprechpartner der E-Rechnung).';
        }
        $iban = (string) ($s['iban'] ?? '');
        if ($iban === '') {
            $e[] = 'Hausverwaltung: Bankverbindung (IBAN) fehlt.';
        } elseif (! Berechnung::validIban($iban)) {
            $e[] = 'Hausverwaltung: Die IBAN ist nicht gültig (Prüfsumme stimmt nicht).';
        }

        return $e;
    }
}
