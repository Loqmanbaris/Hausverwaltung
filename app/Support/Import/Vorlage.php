<?php

namespace App\Support\Import;

use App\Models\Owner;
use App\Models\User;
use App\Support\Stammdaten;

/** Aufbau der Excel-Vorlage für den Import (Paket 15). */
class Vorlage
{
    /** Blatt => [Spalte => [Überschrift, Pflicht, Hinweis, Beispiel]] */
    public const SHEETS = [
        'Objekte' => [
            'kennung' => ['Kennung', true, 'frei wählbar und eindeutig, z. B. H1. Damit verweisen die anderen Blätter auf das Objekt.', 'H1'],
            'eigentuemer' => ['Eigentümer', true, 'Name genau wie im CRM (siehe unten)', 'Loqman Baris'],
            'name' => ['Objektname', true, 'z. B. Hauptstraße 5', 'Hauptstraße 5'],
            'strasse' => ['Straße und Hausnummer', false, '', 'Hauptstraße 5'],
            'plz' => ['PLZ', false, '', '29664'],
            'ort' => ['Ort', false, '', 'Walsrode'],
            'heizung' => ['Heizung', false, 'siehe Liste unten', 'Zentralheizung Gas'],
            'warmwasser' => ['Warmwasser', false, 'zentral oder dezentral', 'zentral'],
            'notizen' => ['Notizen', false, '', ''],
        ],
        'Einheiten' => [
            'objekt' => ['Objekt', true, 'Kennung aus dem Blatt „Objekte“ oder Name eines Objekts, das schon im CRM ist', 'H1'],
            'bezeichnung' => ['Bezeichnung', true, 'z. B. Wohnung 1 oder Laden EG', 'EG links'],
            'etage' => ['Etage', false, '', 'EG'],
            'nutzung' => ['Nutzung', true, 'Wohnen, Gewerbe mit USt, Gewerbe ohne USt oder Montage', 'Wohnen'],
            'flaeche' => ['Fläche m²', false, '', '72,5'],
            'zimmer' => ['Zimmer', false, '', '3'],
            'ausstattung' => ['Ausstattung', false, '', 'Einbauküche, Balkon'],
        ],
        'Mieter' => [
            'kennung' => ['Kennung', true, 'frei wählbar und eindeutig, z. B. M1. Damit verweisen die Mietverträge auf den Mieter.', 'M1'],
            'eigentuemer' => ['Eigentümer', true, 'derselbe Eigentümer wie beim Objekt', 'Loqman Baris'],
            'art' => ['Art', false, 'Person oder Firma (leer = Person)', 'Person'],
            'vorname' => ['Vorname', false, '', 'Hans'],
            'nachname' => ['Nachname', false, 'bei Personen Pflicht', 'Müller'],
            'firma' => ['Firma', false, 'bei Firmen Pflicht', ''],
            'strasse' => ['Straße und Hausnummer', false, 'Anschrift, falls anders als die Wohnung', ''],
            'plz' => ['PLZ', false, '', ''],
            'ort' => ['Ort', false, '', ''],
            'email' => ['E-Mail', false, '', 'hans.mueller@example.org'],
            'telefon' => ['Telefon', false, '', '0171 1234567'],
            'ustid' => ['USt-IdNr.', false, 'nur bei Firmen', ''],
            'notizen' => ['Notizen', false, '', ''],
        ],
        'Mietverträge' => [
            'objekt' => ['Objekt', true, 'Kennung oder Objektname', 'H1'],
            'einheit' => ['Einheit', true, 'Bezeichnung der Einheit', 'EG links'],
            'mieter' => ['Mieter', true, 'Kennung aus dem Blatt „Mieter“ oder Name wie im CRM (Nachname, Vorname)', 'M1'],
            'vertragsart' => ['Vertragsart', false, 'Wohnraum, Gewerbe oder Montage (leer = passend zur Einheit)', 'Wohnraum'],
            'beginn' => ['Beginn', true, 'TT.MM.JJJJ', '01.03.2020'],
            'ende' => ['Ende', false, 'nur bei befristeten oder beendeten Verträgen', ''],
            'kaltmiete' => ['Kaltmiete', true, 'heutige Miete; bei Gewerbe netto', '600,00'],
            'nebenkosten' => ['NK-Vorauszahlung', false, '', '150,00'],
            'heizkosten' => ['Heizkosten-Vorauszahlung', false, '', '90,00'],
            'stellplatz' => ['Stellplatz', false, '', ''],
            'garage' => ['Garage', false, '', ''],
            'moebel' => ['Möblierungszuschlag', false, '', ''],
            'ust' => ['USt %', false, '0, 7 oder 19 (leer = 0)', '0'],
            'faellig' => ['Fällig am Tag', false, '1 bis 28 (leer = 3)', '3'],
            'kaution' => ['Kaution', false, '', '1.800,00'],
            'mietart' => ['Mietart', false, 'fest, Staffel oder Index (leer = fest)', 'fest'],
            'index_monat' => ['Index-Basismonat', false, 'MM.JJJJ, nur bei Indexmiete', ''],
            'index_wert' => ['Index-Basiswert', false, 'nur bei Indexmiete', ''],
            'mietbeginn_crm' => ['Soll ab', false, 'ab wann das CRM Mieten berechnet (TT.MM.JJJJ). Leer = Beginn. Für alte Verträge z. B. 01.01. dieses Jahres', '01.01.2026'],
            'notiz' => ['Notiz', false, '', ''],
        ],
        'Zähler' => [
            'objekt' => ['Objekt', true, 'Kennung oder Objektname', 'H1'],
            'einheit' => ['Einheit', false, 'leer = Hauszähler', 'EG links'],
            'art' => ['Art', true, 'Kaltwasser, Warmwasser, Wärmemenge oder Heizkostenverteiler', 'Kaltwasser'],
            'bezeichnung' => ['Bezeichnung', false, 'z. B. Bad, Küche', 'Bad'],
            'nummer' => ['Zählernummer', false, '', '12345678'],
            'faktor' => ['Faktor', false, 'nur bei Heizkostenverteilern (leer = 1)', ''],
            'datum' => ['Ablesedatum', false, 'TT.MM.JJJJ', '01.01.2026'],
            'stand' => ['Zählerstand', false, '', '123,456'],
        ],
    ];

    /** Spaltenüberschrift für die Datei (Pflichtfelder mit *). */
    public static function header(string $sheet, string $key): string
    {
        [$label, $required] = self::SHEETS[$sheet][$key];

        return $label.($required ? ' *' : '');
    }

    /** Excel-Datei mit leeren Blättern und einer Anleitung erzeugen. */
    public static function build(string $path, User $user): void
    {
        $owners = Owner::visibleTo($user)->orderBy('name')->pluck('name')->all();
        $guide = [
            ['Import in das CRM – Anleitung'],
            [''],
            ['1. Die Blätter „Objekte“, „Einheiten“, „Mieter“, „Mietverträge“ und „Zähler“ ausfüllen. Nicht benötigte Blätter leer lassen.'],
            ['2. Spalten mit * sind Pflicht. Die erste Zeile (Überschriften) bitte nicht ändern.'],
            ['3. Datei speichern (als .xlsx) und im CRM unter „Import“ hochladen. Es wird erst geprüft, eingetragen wird erst nach Bestätigung.'],
            ['4. Was schon im CRM ist (gleicher Name), wird nicht doppelt angelegt.'],
            [''],
            ['Eigentümer im CRM (genau so schreiben):'],
        ];
        foreach ($owners as $o) {
            $guide[] = ['   '.$o];
        }
        $guide[] = [''];
        $guide[] = ['Heizung: '.implode(', ', Stammdaten::HEATING)];
        $guide[] = [''];
        $bold = [0 => true];
        foreach (self::SHEETS as $sheet => $cols) {
            $bold[count($guide)] = true;
            $guide[] = ['Blatt „'.$sheet.'“', 'Beispiel', 'Hinweis'];
            foreach ($cols as $key => [$label, $req, $hint, $example]) {
                $guide[] = [self::header($sheet, $key), $example, $hint];
            }
            $guide[] = [''];
        }

        $sheets = ['Anleitung' => ['rows' => $guide, 'widths' => [42, 22, 90], 'bold' => $bold]];
        foreach (self::SHEETS as $sheet => $cols) {
            $sheets[$sheet] = [
                'rows' => [array_map(fn ($k) => self::header($sheet, $k), array_keys($cols))],
                'widths' => array_map(fn ($c) => max(12, min(30, mb_strlen($c[0]) + 6)), array_values($cols)),
                'header' => true,
            ];
        }
        XlsxWriter::write($path, $sheets);
    }
}
