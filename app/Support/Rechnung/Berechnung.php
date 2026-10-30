<?php

namespace App\Support\Rechnung;

/**
 * Rechenregeln für Rechnungen (Paket 45). Reine Funktionen ohne Datenbank, Beträge in Cent.
 * Die Steuer wird je Steuersatz auf die Summe der Nettobeträge gerechnet und auf Cent gerundet (BR-S-08).
 */
class Berechnung
{
    /** UN/ECE-Einheit => Bezeichnung */
    public const UNITS = ['C62' => 'Stück', 'DAY' => 'Tag', 'WEE' => 'Woche', 'MON' => 'Monat', 'HUR' => 'Stunde', 'LS' => 'pauschal'];

    public const RATES = [19, 7, 0];

    /** Vorschläge für den Grund bei Steuerfreiheit. */
    public const EXEMPTIONS = [
        'Steuerfrei nach § 4 Nr. 12 UStG (Vermietung)',
        'Kein Ausweis von Umsatzsteuer gemäß § 19 UStG (Kleinunternehmer)',
    ];

    public const SMALL_BUSINESS = 'Kein Ausweis von Umsatzsteuer gemäß § 19 UStG (Kleinunternehmer)';

    /** Nettobetrag einer Position in Cent: Menge mal Einzelpreis. */
    public static function lineNet(float|string $quantity, float|string $unitNet): int
    {
        $v = ((float) $quantity) * ((float) $unitNet) * 100;

        return (int) round($v + ($v >= 0 ? 1 : -1) * 1e-7); // 1,005 € soll 1,01 € ergeben, nicht 1,00 €
    }

    /**
     * @param  array<int, array{net: int, rate: float|int|string, exemption?: ?string}>  $lines
     * @return array{groups: array<int, array{rate: float, net: int, vat: int, exemption: ?string}>, net: int, vat: int, gross: int}
     */
    public static function summary(array $lines): array
    {
        $groups = [];
        foreach ($lines as $l) {
            $rate = round((float) $l['rate'], 2);
            $key = (string) $rate;
            $groups[$key] ??= ['rate' => $rate, 'net' => 0, 'vat' => 0, 'exemption' => null];
            $groups[$key]['net'] += (int) $l['net'];
            if ($rate <= 0 && trim((string) ($l['exemption'] ?? '')) !== '') {
                $groups[$key]['exemption'] = trim((string) $l['exemption']);
            }
        }
        krsort($groups, SORT_NUMERIC);
        $net = 0;
        $vat = 0;
        foreach ($groups as &$g) {
            $g['vat'] = (int) round($g['net'] * $g['rate'] / 100);
            $net += $g['net'];
            $vat += $g['vat'];
        }
        unset($g);

        return ['groups' => array_values($groups), 'net' => $net, 'vat' => $vat, 'gross' => $net + $vat];
    }

    /** IBAN-Prüfsumme (Modulo 97). */
    public static function validIban(string $iban): bool
    {
        $iban = strtoupper(preg_replace('/\s+/', '', $iban) ?? '');
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $iban) !== 1) {
            return false;
        }
        $moved = substr($iban, 4).substr($iban, 0, 4);
        $num = '';
        foreach (str_split($moved) as $ch) {
            $num .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
        }
        $rest = 0;
        foreach (str_split($num, 7) as $part) {
            $rest = (int) ($rest.$part) % 97;
        }

        return $rest === 1;
    }

    /** Form der deutschen Umsatzsteuer-Identifikationsnummer: DE und neun Ziffern. */
    public static function validVatId(string $id): bool
    {
        return preg_match('/^DE\d{9}$/', strtoupper(preg_replace('/\s+/', '', $id) ?? '')) === 1;
    }
}
