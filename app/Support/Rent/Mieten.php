<?php

namespace App\Support\Rent;

/** Feste Listen und Bezeichnungen rund um Mietverträge. */
class Mieten
{
    public const LEASE_TYPES = [
        'wohnen' => 'Wohnraum',
        'gewerbe' => 'Gewerbe',
        'montage' => 'Montage-Wohnung',
    ];

    /** Bestandteile der Miete: Spalte => Bezeichnung (in dieser Reihenfolge angezeigt). */
    public const COMPONENTS = [
        'rent_cold' => 'Kaltmiete',
        'prepay_operating' => 'Nebenkosten-Vorauszahlung',
        'prepay_heating' => 'Heizkosten-Vorauszahlung',
        'parking' => 'Stellplatz',
        'garage' => 'Garage',
        'furniture' => 'Möblierungszuschlag',
    ];

    public const SHORT = [
        'rent_cold' => 'Kalt',
        'prepay_operating' => 'NK',
        'prepay_heating' => 'Heizung',
        'parking' => 'Stellplatz',
        'garage' => 'Garage',
        'furniture' => 'Möbel',
    ];

    public const VAT_RATES = [
        '0' => 'keine Umsatzsteuer',
        '7' => '7 % (Beherbergung)',
        '19' => '19 % (Gewerbe mit Option)',
    ];

    public const ADJUSTMENT = [
        'fest' => 'Feste Miete (Erhöhung von Hand)',
        'staffel' => 'Staffelmiete',
        'index' => 'Indexmiete',
    ];

    public const REASONS = [
        'beginn' => 'Vertragsbeginn',
        'erhoehung' => 'Mieterhöhung',
        'staffel' => 'Staffel',
        'index' => 'Indexanpassung',
        'vorauszahlung' => 'Neue Vorauszahlung',
        'korrektur' => 'Korrektur',
    ];

    public const METHODS = [
        'ueberweisung' => 'Überweisung',
        'lastschrift' => 'Lastschrift',
        'bar' => 'Bar',
        'sonstiges' => 'Sonstiges',
    ];

    /** Vorschlag für Vertragsart und Umsatzsteuer aus der Nutzungsart der Einheit und dem Steuerstatus des Eigentümers. */
    public static function suggest(?string $usage, ?string $ownerTaxStatus): array
    {
        $regular = $ownerTaxStatus !== 'klein';

        return match ($usage) {
            'gewerbe_option' => ['type' => 'gewerbe', 'vat' => $regular ? '19' : '0'],
            'gewerbe_ohne_option' => ['type' => 'gewerbe', 'vat' => '0'],
            'montage' => ['type' => 'montage', 'vat' => $regular ? '7' : '0'],
            default => ['type' => 'wohnen', 'vat' => '0'],
        };
    }

    public static function vatLabel(mixed $rate): string
    {
        $r = (float) $rate;

        return $r <= 0 ? 'keine' : rtrim(rtrim(number_format($r, 2, ',', ''), '0'), ',').' %';
    }
}
