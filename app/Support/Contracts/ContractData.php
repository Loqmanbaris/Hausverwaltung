<?php

namespace App\Support\Contracts;

use App\Models\Lease;
use App\Support\Rent\Mieten;

/**
 * Werte für die Platzhalter eines Mietvertrags: aus dem CRM (Eigentümer, Mieter, Einheit, Vertrag, Miete)
 * und aus den Zusatzangaben, die beim Erstellen des Vertrags eingegeben werden.
 */
class ContractData
{
    /** Platzhalter => Beschreibung (für die Vorlagen-Seite). */
    public const PLACEHOLDERS = [
        'vermieter_name' => 'Name des Vermieters (Eigentümer)',
        'vermieter_anschrift' => 'Anschrift des Vermieters',
        'vermieter_kontoinhaber' => 'Kontoinhaber',
        'vermieter_iban' => 'IBAN',
        'vermieter_bank' => 'Bank',
        'mieter_name' => 'Name des Mieters bzw. der Firma',
        'mieter_anschrift' => 'Anschrift des Mieters',
        'weitere_mieter' => 'Weitere Mieter (Zusatzangabe)',
        'einheit' => 'Bezeichnung der Einheit',
        'etage' => 'Etage',
        'objekt_anschrift' => 'Straße, PLZ und Ort des Hauses',
        'flaeche' => 'Fläche in m²',
        'zimmer' => 'Anzahl Zimmer',
        'raeume' => 'Räume (Zusatzangabe)',
        'nebenraeume' => 'Keller, Dachboden … (Zusatzangabe)',
        'ausstattung' => 'Mitvermietete Einrichtung (Zusatzangabe)',
        'stellplatz' => 'Stellplatz oder Garage (Zusatzangabe)',
        'schluessel' => 'Schlüssel (Zusatzangabe)',
        'beginn' => 'Beginn des Mietverhältnisses',
        'ende' => 'Ende (nur bei Befristung)',
        'befristet' => 'ja, wenn ein Ende eingetragen ist',
        'befristungsgrund' => 'Grund der Befristung bei Wohnraum (Zusatzangabe)',
        'kuendigungsverzicht' => 'Jahre Kündigungsverzicht (Zusatzangabe)',
        'kuendigungsfrist' => 'Kündigungsfrist Montage (Zusatzangabe)',
        'miete_aufstellung' => 'Aufstellung der Miete mit allen Bestandteilen',
        'kaltmiete' => 'Grundmiete',
        'gesamtmiete' => 'Monatlicher Gesamtbetrag',
        'faellig_text' => 'Fälligkeit, z. B. „dritten Werktag“',
        'ust' => 'ja, wenn Umsatzsteuer berechnet wird',
        'ust_satz' => 'Umsatzsteuersatz',
        'staffel' => 'ja bei Staffelmiete',
        'staffel_liste' => 'Künftige Staffeln',
        'index' => 'ja bei Indexmiete',
        'index_basis_monat' => 'Basismonat des Index',
        'index_basis_wert' => 'Basiswert des Index',
        'kaution' => 'Kaution',
        'buergschaft' => 'ja, wenn Bürgschaft erlaubt (Gewerbe)',
        'renoviert' => 'ja, wenn renoviert übergeben (Wohnraum)',
        'energieausweis' => 'ja, wenn Energieausweis vorgelegt',
        'nutzungszweck' => 'Nutzungszweck (Gewerbe)',
        'verlaengerungsoption' => 'Verlängerungsoption (Gewerbe)',
        'max_personen' => 'Höchstzahl Bewohner (Montage)',
        'personen_namen' => 'Namen der Bewohner (Montage)',
        'preis_text' => 'Preis in Worten, z. B. „25,00 € pro Person und Nacht“ (Montage)',
        'endreinigung' => 'Endreinigung (Montage)',
        'zahlungsziel' => 'Zahlungsziel in Tagen',
        'sondervereinbarungen' => 'Sondervereinbarungen (Zusatzangabe)',
        'ort' => 'Ort der Unterschrift',
    ];

    /**
     * Zusatzangaben je Vertragsart: Feld => [Bezeichnung, Typ, Hinweis, Arten].
     * Typen: text, textarea, check, number, money
     */
    public const EXTRAS = [
        'weitere_mieter' => ['Weitere Mieter', 'textarea', 'Name und Geburtsdatum aller weiteren Personen, die den Vertrag mit unterschreiben', ['wohnen']],
        'raeume' => ['Räume', 'text', 'z. B. 2 Zimmer, Küche, Bad, Flur, Balkon', ['wohnen', 'gewerbe']],
        'nebenraeume' => ['Nebenräume', 'text', 'z. B. Kellerraum Nr. 3, Dachbodenabteil', ['wohnen', 'gewerbe']],
        'ausstattung' => ['Mitvermietete Einrichtung', 'text', 'z. B. Einbauküche mit Herd, Kühlschrank und Spülmaschine', ['wohnen', 'montage']],
        'stellplatz' => ['Stellplatz oder Garage', 'text', 'z. B. Stellplatz Nr. 4 im Hof', ['wohnen', 'gewerbe', 'montage']],
        'schluessel' => ['Schlüssel', 'text', 'Anzahl und Art', ['wohnen', 'gewerbe', 'montage']],
        'befristungsgrund' => ['Grund der Befristung', 'textarea', 'Nur bei befristetem Wohnraum, z. B. Eigenbedarf für … ab … (§ 575 BGB). Ohne Grund gilt der Vertrag als unbefristet.', ['wohnen']],
        'kuendigungsverzicht' => ['Kündigungsverzicht in Jahren', 'number', '0 bis 4, leer = keiner', ['wohnen']],
        'renoviert' => ['Wohnung wird renoviert übergeben', 'check', 'Nur dann übernimmt der Mieter die Schönheitsreparaturen', ['wohnen']],
        'nutzungszweck' => ['Nutzungszweck', 'text', 'z. B. Friseursalon, Büro, Einzelhandel mit Lebensmitteln', ['gewerbe']],
        'verlaengerungsoption' => ['Verlängerungsoption', 'textarea', 'z. B. Der Mieter kann das Mietverhältnis durch Erklärung bis 6 Monate vor Ablauf einmal um 5 Jahre verlängern.', ['gewerbe']],
        'buergschaft' => ['Kaution auch als Bankbürgschaft', 'check', '', ['gewerbe']],
        'max_personen' => ['Höchstzahl Bewohner', 'number', '', ['montage']],
        'personen_namen' => ['Namen der Bewohner', 'textarea', 'Falls schon bekannt', ['montage']],
        'preis_text' => ['Preis', 'text', 'z. B. 25,00 € pro Person und Nacht, mindestens 7 Nächte', ['montage']],
        'endreinigung' => ['Endreinigung (€)', 'money', '', ['montage']],
        'kuendigungsfrist' => ['Kündigungsfrist bei unbestimmter Dauer', 'text', 'z. B. einer Woche', ['montage']],
        'energieausweis' => ['Energieausweis wurde vorgelegt', 'check', '', ['wohnen', 'gewerbe']],
        'sondervereinbarungen' => ['Sondervereinbarungen', 'textarea', 'Werden im letzten Paragrafen eingefügt', ['wohnen', 'gewerbe', 'montage']],
        'ort' => ['Ort der Unterschrift', 'text', '', ['wohnen', 'gewerbe', 'montage']],
    ];

    public static function kindFor(Lease $lease): string
    {
        return in_array($lease->lease_type, ['wohnen', 'gewerbe', 'montage'], true) ? $lease->lease_type : 'wohnen';
    }

    /** Zusatzfelder für eine Vertragsart. */
    public static function extrasFor(string $kind): array
    {
        return array_filter(self::EXTRAS, fn ($e) => in_array($kind, $e[3], true));
    }

    /** Vorbelegung der Zusatzangaben aus dem CRM. */
    public static function defaultExtras(Lease $lease): array
    {
        $lease->loadMissing(['unit', 'owner', 'rents']);
        $kind = self::kindFor($lease);
        $current = $lease->currentRent($lease->start_date->format('Y-m-d'));
        $out = [
            'schluessel' => $kind === 'montage' ? '1 Wohnungsschlüssel je Bewohner, 1 Haustürschlüssel' : '2 Haustür-/Wohnungsschlüssel, 1 Briefkastenschlüssel',
            'ort' => (string) ($lease->owner?->city ?? ''),
            'ausstattung' => $kind === 'montage' ? 'vollständig möbliert mit Betten, Schränken, Küche mit Geschirr, Waschmaschine, Fernseher und WLAN' : '',
            'kuendigungsfrist' => 'einer Woche',
            'max_personen' => (string) (\App\Models\LeasePerson::query()->where('lease_id', $lease->id)->orderByDesc('valid_from')->value('persons') ?? ''),
        ];
        if ($kind === 'montage' && $current) {
            $sum = (float) $current->rent_cold + (float) $current->prepay_operating + (float) $current->prepay_heating + (float) $current->furniture + (float) $current->parking + (float) $current->garage;
            if ($sum > 0) {
                $out['preis_text'] = self::eur($sum).' pro Monat (Pauschale für die ganze Wohnung)';
            }
        }

        return $out;
    }

    /**
     * Alle Platzhalter-Werte. Leere Zeichenkette = nicht vorhanden (für [[wenn …]]).
     *
     * @return array<string, string>
     */
    public static function values(Lease $lease, array $extras): array
    {
        $lease->loadMissing(['unit.property', 'tenant.contacts', 'owner', 'rents']);
        $owner = $lease->owner;
        $tenant = $lease->tenant;
        $unit = $lease->unit;
        $p = $unit?->property;
        $kind = self::kindFor($lease);
        $account = $owner?->bankAccounts()->orderByDesc('is_primary')->first();
        $start = $lease->start_date->format('Y-m-d');
        $rent = $lease->currentRent($start);
        $vat = (float) $lease->vat_rate;

        $line = fn (?string ...$parts) => trim(implode(', ', array_filter(array_map('trim', array_map('strval', $parts)), fn ($x) => $x !== '')));
        $contact = $tenant?->type === 'firma' ? $tenant->contacts->firstWhere('receives_invoices', true) : null;

        // Miete
        $rows = [];
        $net = 0.0;
        foreach (Mieten::COMPONENTS as $col => $label) {
            $v = (float) ($rent?->{$col} ?? 0);
            if ($v > 0) {
                $label = match ($col) {
                    'rent_cold' => $kind === 'gewerbe' ? 'Grundmiete (netto)' : 'Grundmiete (netto kalt)',
                    'prepay_operating' => 'Vorauszahlung Betriebskosten',
                    'prepay_heating' => 'Vorauszahlung Heiz- und Warmwasserkosten',
                    default => $label,
                };
                $rows[] = '- '.$label.': '.self::eur($v);
                $net += $v;
            }
        }
        $vatAmount = round($net * $vat / 100, 2);
        if ($vat > 0) {
            $rows[] = '- Umsatzsteuer '.self::num($vat).' %: '.self::eur($vatAmount);
        }
        $total = round($net + $vatAmount, 2);
        $rows[] = '- Gesamtbetrag monatlich: '.self::eur($total);

        // Staffel
        $staffel = [];
        if ($lease->adjustment_type === 'staffel') {
            foreach ($lease->rents->filter(fn ($r) => $r->valid_from->format('Y-m-d') > $start)->sortBy('valid_from') as $r) {
                $staffel[] = '- ab '.$r->valid_from->format('d.m.Y').': Grundmiete '.self::eur($r->rent_cold);
            }
        }

        $due = (int) $lease->due_day;
        $v = [
            'vermieter_name' => (string) ($owner?->name ?? ''),
            'vermieter_anschrift' => $line($owner?->street, trim(($owner?->zip ?? '').' '.($owner?->city ?? ''))),
            'vermieter_kontoinhaber' => (string) ($account?->account_holder ?? $owner?->name ?? ''),
            'vermieter_iban' => $account ? $account->formattedIban() : '',
            'vermieter_bank' => (string) ($account?->bank_name ?? ''),
            'mieter_name' => $tenant?->type === 'firma' ? (string) $tenant->company_name : trim(($tenant?->first_name ?? '').' '.($tenant?->last_name ?? '')),
            'mieter_vertreter' => $contact ? (string) $contact->name : '',
            'mieter_anschrift' => $line($tenant?->street, trim(($tenant?->zip ?? '').' '.($tenant?->city ?? ''))),
            'einheit' => (string) ($unit?->label ?? ''),
            'etage' => (string) ($unit?->floor ?? ''),
            'objekt_anschrift' => $line($p?->street, trim(($p?->zip ?? '').' '.($p?->city ?? ''))),
            'flaeche' => $unit?->area_sqm ? self::num($unit->area_sqm) : '',
            'zimmer' => $unit?->rooms ? self::num($unit->rooms, 1) : '',
            'beginn' => $lease->start_date->format('d.m.Y'),
            'ende' => $lease->end_date ? $lease->end_date->format('d.m.Y') : '',
            'befristet' => $lease->end_date ? 'ja' : '',
            'miete_aufstellung' => implode("\n", $rows),
            'kaltmiete' => self::eur($rent?->rent_cold ?? 0),
            'gesamtmiete' => self::eur($total),
            'faellig_text' => $due <= 3 ? 'dritten Werktag' : $due.'. Tag',
            'ust' => $vat > 0 ? 'ja' : '',
            'ust_satz' => self::num($vat),
            'staffel' => $staffel !== [] ? 'ja' : '',
            'staffel_liste' => implode("\n", $staffel),
            'index' => $lease->adjustment_type === 'index' ? 'ja' : '',
            'index_basis_monat' => $lease->index_base_month ? $lease->index_base_month->format('m/Y') : '',
            'index_basis_wert' => $lease->index_base_value !== null ? self::num($lease->index_base_value, 1) : '',
            'kaution' => (float) $lease->deposit_amount > 0 ? self::eur($lease->deposit_amount) : '',
            'zahlungsziel' => (string) ($lease->payment_term_days ?: 7),
        ];

        foreach (self::extrasFor($kind) as $key => [$label, $type]) {
            $val = $extras[$key] ?? '';
            if ($type === 'check') {
                $v[$key] = ! empty($val) ? 'ja' : '';
            } elseif ($type === 'money') {
                $v[$key] = trim((string) $val) !== '' && (float) $val > 0 ? self::eur($val) : '';
            } elseif ($key === 'kuendigungsverzicht') {
                $v[$key] = (int) $val > 0 ? (string) min(4, (int) $val) : '';
            } else {
                $v[$key] = trim((string) $val);
            }
        }
        // Befristung bei Wohnraum nur mit Grund (§ 575 BGB)
        if ($kind === 'wohnen' && $v['befristet'] === 'ja' && ($v['befristungsgrund'] ?? '') === '') {
            $v['befristet'] = '';
        }

        return $v;
    }

    /** Hinweise vor dem Erstellen bzw. Unterschreiben. */
    public static function warnings(Lease $lease, array $values): array
    {
        $kind = self::kindFor($lease);
        $w = [];
        foreach (['vermieter_anschrift' => 'Anschrift des Eigentümers', 'vermieter_iban' => 'Bankverbindung des Eigentümers', 'mieter_anschrift' => 'Anschrift des Mieters', 'objekt_anschrift' => 'Anschrift des Objekts', 'flaeche' => 'Fläche der Einheit'] as $k => $label) {
            if (($values[$k] ?? '') === '') {
                $w[] = $label.' fehlt in den Stammdaten. Im Vertrag steht dann eine Leerstelle zum Ausfüllen von Hand.';
            }
        }
        if ($kind === 'wohnen' && $lease->end_date && ($values['befristungsgrund'] ?? '') === '') {
            $w[] = 'Der Mietvertrag hat ein Ende, aber keinen Befristungsgrund. Wohnraum darf nur mit Grund befristet werden (§ 575 BGB). Ohne Grund wird der Vertrag als unbefristet erstellt.';
        }
        if ($kind === 'wohnen' && (float) $lease->deposit_amount > 3 * (float) ($lease->currentRent($lease->start_date->format('Y-m-d'))?->rent_cold ?? 0) + 0.004) {
            $w[] = 'Die Kaution ist höher als drei Kaltmieten (§ 551 BGB).';
        }
        if ($kind === 'gewerbe' && ($values['nutzungszweck'] ?? '') === '') {
            $w[] = 'Bitte den Nutzungszweck angeben.';
        }
        if ($kind === 'gewerbe' && $lease->adjustment_type === 'index') {
            $w[] = 'Eine Indexklausel ist bei Gewerbe nur wirksam, wenn der Vermieter mindestens 10 Jahre gebunden ist (Preisklauselgesetz). Bitte prüfen lassen.';
        }
        if ($kind === 'montage' && ($values['preis_text'] ?? '') === '') {
            $w[] = 'Bitte den Preis eintragen, z. B. „25,00 € pro Person und Nacht“.';
        }

        return $w;
    }

    /** Muss der Vertrag auf Papier unterschrieben werden (gesetzliche Schriftform)? */
    public static function needsPaper(Lease $lease, array $values): bool
    {
        // Wohnraum über ein Jahr fest (Befristung oder Kündigungsverzicht): Schriftform § 550 BGB
        return self::kindFor($lease) === 'wohnen' && ($values['befristet'] === 'ja' || (int) ($values['kuendigungsverzicht'] ?? 0) >= 1);
    }

    public static function eur(mixed $v): string
    {
        return number_format((float) $v, 2, ',', '.').' €';
    }

    public static function num(mixed $v, int $dec = 2): string
    {
        $s = number_format((float) $v, $dec, ',', '.');

        return str_contains($s, ',') ? rtrim(rtrim($s, '0'), ',') : $s;
    }
}
