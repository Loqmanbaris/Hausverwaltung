<?php

namespace App\Support\Listings;

/** Feste Werte für Inserate (Paket 11). */
class Listings
{
    public const KINDS = [
        'wohnen' => 'Wohnung',
        'montage' => 'Montage-Wohnung',
        'gewerbe' => 'Gewerbefläche',
    ];

    /** Öffentliche Übersichtsseiten: Adresse => [Art, Überschrift] */
    public const PAGES = [
        'wohnen' => ['wohnungen', 'Wohnungen zur Miete'],
        'montage' => ['montage-wohnungen', 'Montage-Wohnungen für Monteure'],
        'gewerbe' => ['gewerbe', 'Gewerbeflächen'],
    ];

    public const STATUS = [
        'entwurf' => 'Entwurf',
        'online' => 'Online',
        'offline' => 'Offline',
    ];

    /** Merkmal => [Bezeichnung, Arten] */
    public const FEATURES = [
        'balkon' => ['Balkon oder Terrasse', ['wohnen', 'montage']],
        'garten' => ['Garten oder Gartenanteil', ['wohnen']],
        'ebk' => ['Einbauküche', ['wohnen', 'gewerbe']],
        'kueche' => ['Voll ausgestattete Küche', ['montage']],
        'moebliert' => ['Möbliert', ['wohnen']],
        'keller' => ['Keller oder Abstellraum', ['wohnen', 'gewerbe']],
        'aufzug' => ['Aufzug', ['wohnen', 'montage', 'gewerbe']],
        'stufenlos' => ['Stufenloser Zugang', ['wohnen', 'montage', 'gewerbe']],
        'stellplatz' => ['Stellplatz oder Garage', ['wohnen', 'gewerbe']],
        'transporter' => ['Parkplatz auch für Transporter', ['montage']],
        'waschmaschine' => ['Waschmaschine', ['wohnen', 'montage']],
        'wlan' => ['WLAN inklusive', ['montage', 'gewerbe']],
        'tv' => ['Fernseher', ['montage']],
        'einzelbetten' => ['Einzelbetten', ['montage']],
        'bettwaesche' => ['Bettwäsche und Handtücher', ['montage']],
        'endreinigung' => ['Endreinigung inklusive', ['montage']],
        'haustiere' => ['Haustiere nach Absprache', ['wohnen']],
        'schaufenster' => ['Schaufenster', ['gewerbe']],
        'lager' => ['Lagerfläche', ['gewerbe']],
        'wc' => ['Eigenes WC', ['gewerbe']],
    ];

    public const ENERGY_TYPES = [
        'verbrauch' => 'Verbrauchsausweis',
        'bedarf' => 'Bedarfsausweis',
        'keiner' => 'Kein Energieausweis nötig (z. B. Baudenkmal)',
    ];

    public const ENERGY_CLASSES = ['A+', 'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    /** Art des Inserats aus der Nutzungsart der Einheit. */
    public static function kindForUsage(?string $usage): string
    {
        return match ($usage) {
            'montage' => 'montage',
            'gewerbe_option', 'gewerbe_ohne_option' => 'gewerbe',
            default => 'wohnen',
        };
    }

    /** @return array<string, string> */
    public static function featuresFor(string $kind): array
    {
        $out = [];
        foreach (self::FEATURES as $key => [$label, $kinds]) {
            if (in_array($kind, $kinds, true)) {
                $out[$key] = $label;
            }
        }

        return $out;
    }

    public static function eur(mixed $v, int $dec = 2): string
    {
        return number_format((float) $v, $dec, ',', '.').' €';
    }

    public static function num(mixed $v, int $dec = 2): string
    {
        $s = number_format((float) $v, $dec, ',', '.');

        return $dec > 0 ? rtrim(rtrim($s, '0'), ',') : $s;
    }

    /** Pflichtangaben zum Energieausweis als ein Satz (§ 87 GEG). Leer, wenn nichts eingetragen. */
    public static function energyLine(\App\Models\Listing $l): string
    {
        if ($l->energy_type === null) {
            return '';
        }
        if ($l->energy_type === 'keiner') {
            return 'Energieausweis: nicht erforderlich.';
        }
        $parts = [self::ENERGY_TYPES[$l->energy_type]];
        if ($l->energy_value !== null) {
            $parts[] = ($l->energy_type === 'bedarf' ? 'Endenergiebedarf ' : 'Endenergieverbrauch ').self::num($l->energy_value, 1).' kWh/(m²·a)';
        }
        if ($l->energy_source) {
            $parts[] = 'wesentlicher Energieträger '.$l->energy_source;
        }
        if ($l->energy_year) {
            $parts[] = 'Baujahr laut Ausweis '.$l->energy_year;
        }
        if ($l->energy_class) {
            $parts[] = 'Energieeffizienzklasse '.$l->energy_class;
        }

        return 'Energieausweis: '.implode(', ', $parts).'.';
    }

    /** Was für ein vollständiges Inserat noch fehlt (Hinweise, kein Hindernis). */
    public static function missing(\App\Models\Listing $l): array
    {
        $m = [];
        if ($l->photos()->count() === 0) {
            $m[] = 'Fotos';
        }
        if (trim((string) $l->description) === '') {
            $m[] = 'Beschreibung';
        }
        if ($l->kind === 'montage') {
            if ($l->price_night === null && $l->price_week === null && $l->price_month === null) {
                $m[] = 'Preis';
            }
        } else {
            if ($l->rent_cold === null) {
                $m[] = 'Kaltmiete';
            }
            if ($l->energy_type === null) {
                $m[] = 'Energieausweis (Pflichtangabe in Anzeigen)';
            }
        }
        if ($l->area_sqm === null) {
            $m[] = 'Fläche';
        }

        return $m;
    }
}
