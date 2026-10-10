<?php

namespace App\Support\Datev;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Bausteine für das DATEV-Format (CSV, Format „Buchungsstapel“, Version 700, Formatversion 13).
 * Grundlage ist die Feldbeschreibung von DATEV (Dok.-Nr. 1003221):
 * Zeichensatz ANSI (Windows-1252), Trennzeichen Semikolon, Dezimalkomma ohne Tausenderpunkt,
 * Textfelder in Anführungszeichen (Anführungszeichen im Text verdoppelt), Zeilenende CR/LF,
 * Belegdatum als TTMM, 125 Felder je Buchungszeile, kein Trennzeichen am Zeilenende.
 */
class DatevFormat
{
    public const FIELD_COUNT = 125;

    /** Felder der Buchungszeile, die wir füllen (Nummer in der DATEV-Feldbeschreibung). */
    public const F_AMOUNT = 1;

    public const F_DEBIT_CREDIT = 2;

    public const F_CURRENCY = 3;

    public const F_ACCOUNT = 7;

    public const F_COUNTER_ACCOUNT = 8;

    public const F_TAX_KEY = 9;

    public const F_DOC_DATE = 10;

    public const F_DOC_FIELD_1 = 11;

    public const F_TEXT = 14;

    public const F_LOCK = 114;

    /** Überschriften der Felder 1 bis 125 (Zeile 2 der Datei). */
    public static function columnNames(): array
    {
        $names = [
            'Umsatz (ohne Soll/Haben-Kz)', 'Soll/Haben-Kennzeichen', 'WKZ Umsatz', 'Kurs', 'Basis-Umsatz',
            'WKZ Basis-Umsatz', 'Konto', 'Gegenkonto (ohne BU-Schlüssel)', 'BU-Schlüssel', 'Belegdatum',
            'Belegfeld 1', 'Belegfeld 2', 'Skonto', 'Buchungstext', 'Postensperre', 'Diverse Adressnummer',
            'Geschäftspartnerbank', 'Sachverhalt', 'Zinssperre', 'Beleglink',
        ];
        for ($i = 1; $i <= 8; $i++) {
            $names[] = "Beleginfo - Art $i";
            $names[] = "Beleginfo - Inhalt $i";
        }
        array_push(
            $names,
            'KOST1 - Kostenstelle', 'KOST2 - Kostenstelle', 'Kost-Menge', 'EU-Land u. USt-IdNr. (Bestimmung)',
            'EU-Steuersatz (Bestimmung)', 'Abw. Versteuerungsart', 'Sachverhalt L+L', 'Funktionsergänzung L+L',
            'BU 49 Hauptfunktionstyp', 'BU 49 Hauptfunktionsnummer', 'BU 49 Funktionsergänzung'
        );
        for ($i = 1; $i <= 20; $i++) {
            $names[] = "Zusatzinformation - Art $i";
            $names[] = "Zusatzinformation- Inhalt $i";
        }
        array_push(
            $names,
            'Stück', 'Gewicht', 'Zahlweise', 'Forderungsart', 'Veranlagungsjahr', 'Zugeordnete Fälligkeit',
            'Skontotyp', 'Auftragsnummer', 'Buchungstyp', 'USt-Schlüssel (Anzahlungen)', 'EU-Mitgliedstaat (Anzahlungen)',
            'Sachverhalt L+L (Anzahlungen)', 'EU-Steuersatz (Anzahlungen)', 'Erlöskonto (Anzahlungen)', 'Herkunft-Kz',
            'Leerfeld', 'KOST-Datum', 'SEPA-Mandatsreferenz', 'Skontosperre', 'Gesellschaftername', 'Beteiligtennummer',
            'Identifikationsnummer', 'Zeichnernummer', 'Postensperre bis', 'Bezeichnung SoBil-Sachverhalt',
            'Kennzeichen SoBil-Buchung', 'Festschreibung', 'Leistungsdatum', 'Datum Zuord. Steuerperiode', 'Fälligkeit',
            'Generalumkehr (GU)', 'Steuersatz', 'Land', 'Abrechnungsreferenz', 'BVV-Position',
            'EU-Land u. USt-IdNr. (Ursprung)', 'EU-Steuersatz (Ursprung)', 'Abw. Skontokonto'
        );

        return $names;
    }

    /** Textfeld: in Anführungszeichen, Anführungszeichen verdoppelt, Steuerzeichen entfernt, auf $max Zeichen gekürzt. Leer bleibt leer. */
    public static function text(?string $value, int $max): string
    {
        $v = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value));
        $v = trim((string) preg_replace('/\s+/u', ' ', $v));
        if ($v === '') {
            return '';
        }
        $v = rtrim(mb_substr($v, 0, $max));

        return '"'.str_replace('"', '""', $v).'"';
    }

    /** Textfeld, das immer erscheint, auch wenn es leer ist (Kopfzeile). */
    public static function textAlways(?string $value, int $max): string
    {
        $t = self::text($value, $max);

        return $t === '' ? '""' : $t;
    }

    /** „119.00“ wird zu „119,00“. Der Umsatz muss positiv sein, höchstens 10 Stellen vor dem Komma. */
    public static function amount(string $decimal): string
    {
        if (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $decimal) !== 1) {
            throw new InvalidArgumentException('Ungültiger Betrag für den Export: '.$decimal);
        }

        return str_replace('.', ',', number_format((float) $decimal, 2, '.', ''));
    }

    /** 2026-10-08 wird zu 0810 (TTMM). */
    public static function ttmm(string $isoDate): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $isoDate);
        if ($d === false || $d->format('Y-m-d') !== $isoDate) {
            throw new InvalidArgumentException('Ungültiges Datum für den Export: '.$isoDate);
        }

        return $d->format('dm');
    }

    /**
     * Belegfeld 1 (Rechnungsnummer): erlaubt sind Ziffern, Buchstaben und $ & % * + - /, höchstens 36 Zeichen.
     * Umlaute werden umgeschrieben, alles andere wird zu einem Bindestrich.
     */
    public static function documentNumber(?string $value, int $max = 36): string
    {
        $v = strtr((string) $value, [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue', 'ß' => 'ss',
        ]);
        $v = (string) preg_replace('#[^A-Za-z0-9$&%*+/\-]+#', '-', $v);
        $v = trim((string) preg_replace('/-{2,}/', '-', $v), '-');

        return rtrim(substr($v, 0, $max), '-');
    }

    /**
     * Kopfzeile (Zeile 1) mit den 31 Feldern des DATEV-Formats.
     *
     * @param  array{consultant: int|string, client: int|string, fy_start: string, account_length: int, from: string, to: string,
     *     description: string, skr: string, created: DateTimeImmutable, exported_by?: string, origin?: string}  $h
     */
    public static function headerLine(array $h): string
    {
        $ymd = fn (string $iso) => str_replace('-', '', $iso);

        $fields = [
            '"EXTF"',                                                    // 1 Kennzeichen
            '700',                                                       // 2 Versionsnummer
            '21',                                                        // 3 Datenkategorie (Buchungsstapel)
            '"Buchungsstapel"',                                          // 4 Formatname
            '13',                                                        // 5 Formatversion
            $h['created']->format('YmdHis').'000',                       // 6 Erzeugt am
            '',                                                          // 7 Importiert
            self::textAlways($h['origin'] ?? 'RE', 2),                   // 8 Herkunft
            self::textAlways($h['exported_by'] ?? 'CRM Immobilien', 25), // 9 Exportiert von
            '""',                                                        // 10 Importiert von
            (string) (int) $h['consultant'],                             // 11 Berater
            (string) (int) $h['client'],                                 // 12 Mandant
            $ymd($h['fy_start']),                                        // 13 Beginn des Wirtschaftsjahres
            (string) (int) $h['account_length'],                         // 14 Sachkontenlänge
            $ymd($h['from']),                                            // 15 Datum von
            $ymd($h['to']),                                              // 16 Datum bis
            self::textAlways($h['description'], 30),                     // 17 Bezeichnung
            '""',                                                        // 18 Diktatkürzel
            '1',                                                         // 19 Buchungstyp (Finanzbuchführung)
            '0',                                                         // 20 Rechnungslegungszweck
            '0',                                                         // 21 Festschreibung (keine)
            '"EUR"',                                                     // 22 Währungskennzeichen
            '',                                                          // 23 reserviert
            '""',                                                        // 24 Derivatskennzeichen
            '',                                                          // 25 reserviert
            '',                                                          // 26 reserviert
            self::textAlways($h['skr'], 2),                              // 27 Sachkontenrahmen
            '',                                                          // 28 ID der Branchenlösung
            '',                                                          // 29 reserviert
            '""',                                                        // 30 reserviert
            '""',                                                        // 31 Anwendungsinformation
        ];

        return implode(';', $fields);
    }

    /** Zeile 2: Überschriften. */
    public static function columnHeaderLine(): string
    {
        return implode(';', self::columnNames());
    }

    /**
     * Buchungszeile aus den gefüllten Feldern (Nummer => fertig formatierter Wert). Alle anderen Felder bleiben leer.
     *
     * @param  array<int, string>  $fields
     */
    public static function row(array $fields): string
    {
        $cells = array_fill(1, self::FIELD_COUNT, '');
        foreach ($fields as $no => $value) {
            if ($no < 1 || $no > self::FIELD_COUNT) {
                throw new InvalidArgumentException('Ungültige Feldnummer: '.$no);
            }
            $cells[$no] = $value;
        }

        return implode(';', $cells);
    }

    /** Fügt die Zeilen mit CR/LF zusammen und wandelt nach Windows-1252 (ANSI). Nicht darstellbare Zeichen werden zu „?“. */
    public static function encode(array $lines): string
    {
        $text = implode("\r\n", $lines)."\r\n";
        $previous = mb_substitute_character();
        mb_substitute_character(0x3F);
        $out = mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        mb_substitute_character($previous);

        return $out;
    }
}
