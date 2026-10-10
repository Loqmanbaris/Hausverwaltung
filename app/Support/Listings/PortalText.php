<?php

namespace App\Support\Listings;

use App\Models\Listing;

/**
 * Texte zum Kopieren für ImmoScout24 und Kleinanzeigen.
 * Längen nach den Eingabefeldern der Portale (ImmoScout24: Titel 100, Freitexte je 3.999 Zeichen; Kleinanzeigen: Titel 65, Text 4.000).
 */
class PortalText
{
    public static function cut(string $text, int $max): string
    {
        $text = trim(preg_replace("/[ \t]+/", ' ', str_replace("\r", '', $text)));

        return mb_strlen($text) <= $max ? $text : rtrim(mb_substr($text, 0, $max - 1)).'…';
    }

    /** Eckdaten in der Reihenfolge der Portale. @return array<string, string> */
    public static function facts(Listing $l): array
    {
        $f = [];
        $f['Art'] = $l->kindLabel();
        $p = $l->unit?->property;
        $f['Ort'] = trim(($p?->zip ?? '').' '.($p?->city ?? ''));
        if ($l->show_address && $p?->street) {
            $f['Straße'] = $p->street;
        }
        if ($l->area_sqm !== null) {
            $f[$l->kind === 'gewerbe' ? 'Fläche' : 'Wohnfläche'] = Listings::num($l->area_sqm).' m²';
        }
        if ($l->rooms !== null) {
            $f['Zimmer'] = Listings::num($l->rooms, 1);
        }
        if ($l->floor) {
            $f['Etage'] = $l->floor;
        }
        if ($l->year_built) {
            $f['Baujahr'] = (string) $l->year_built;
        }
        if ($l->kind === 'montage') {
            if ($l->beds) {
                $f['Schlafplätze'] = (string) $l->beds;
            }
            $per = $l->price_per_person ? ' pro Person' : '';
            if ($l->price_night !== null) {
                $f['Preis pro Nacht'] = Listings::eur($l->price_night).$per;
            }
            if ($l->price_week !== null) {
                $f['Preis pro Woche'] = Listings::eur($l->price_week).$per;
            }
            if ($l->price_month !== null) {
                $f['Preis pro Monat'] = Listings::eur($l->price_month).$per;
            }
            if ($l->min_nights) {
                $f['Mindestaufenthalt'] = $l->min_nights.' Nacht'.($l->min_nights === 1 ? '' : 'e');
            }
        } else {
            $vat = $l->plus_vat ? ' zzgl. USt.' : '';
            if ($l->rent_cold !== null) {
                $f['Kaltmiete'] = Listings::eur($l->rent_cold).$vat;
            }
            if ($l->service_charge !== null) {
                $f['Nebenkosten'] = Listings::eur($l->service_charge).$vat;
            }
            if ($l->heating_charge !== null) {
                $f['Heizkosten'] = Listings::eur($l->heating_charge).$vat;
            }
            if ($l->warmRent() !== null && ($l->service_charge !== null || $l->heating_charge !== null)) {
                $f['Gesamtmiete'] = Listings::eur($l->warmRent()).$vat;
            }
            if ($l->deposit !== null) {
                $f['Kaution'] = Listings::eur($l->deposit);
            }
        }
        $f['Verfügbar ab'] = $l->available_from && $l->available_from->format('Y-m-d') > date('Y-m-d') ? $l->available_from->format('d.m.Y') : 'sofort';
        if ($l->energy_type) {
            $f['Energieausweis'] = rtrim(preg_replace('/^Energieausweis: /', '', Listings::energyLine($l)), '.');
        }

        return $f;
    }

    /** @return array{title: string, description: string, equipment: string, location: string, other: string} */
    public static function immoscout(Listing $l): array
    {
        $equip = trim((string) $l->equipment_text);
        $features = implode(', ', $l->featureLabels());
        if ($features !== '') {
            $equip = trim($equip."\n\n".'Ausstattung im Überblick: '.$features.'.');
        }
        $other = trim((string) $l->other_text);
        $energy = Listings::energyLine($l);
        if ($energy !== '') {
            $other = trim($other."\n\n".$energy);
        }

        return [
            'title' => self::cut($l->title, 100),
            'description' => self::cut((string) $l->description, 3999),
            'equipment' => self::cut($equip, 3999),
            'location' => self::cut((string) $l->location_text, 3999),
            'other' => self::cut($other, 3999),
        ];
    }

    /** @return array{title: string, text: string, price: string} */
    public static function kleinanzeigen(Listing $l): array
    {
        $lines = [];
        foreach (self::facts($l) as $label => $value) {
            if ($label !== 'Energieausweis') {
                $lines[] = $label.': '.$value;
            }
        }
        $blocks = [implode("\n", $lines)];
        foreach ([$l->description, $l->equipment_text, $l->location_text, $l->other_text] as $t) {
            if (trim((string) $t) !== '') {
                $blocks[] = trim((string) $t);
            }
        }
        if ($l->featureLabels() !== []) {
            $blocks[] = "Ausstattung:\n- ".implode("\n- ", $l->featureLabels());
        }
        $energy = Listings::energyLine($l);
        if ($energy !== '') {
            $blocks[] = $energy;
        }
        $blocks[] = 'Weitere Fotos und Anfrage: '.route('public.listing', $l->slug);

        $price = $l->kind === 'montage'
            ? (string) ($l->price_night ?? $l->price_week ?? $l->price_month ?? '')
            : (string) ($l->warmRent() ?? '');

        return [
            'title' => self::cut($l->title, 65),
            'text' => self::cut(implode("\n\n", $blocks), 4000),
            'price' => $price === '' ? '' : Listings::num($price, 2),
        ];
    }
}
