<?php

namespace App\Support\Bank;

use App\Support\Money;
use App\Support\Stammdaten;
use DateTimeImmutable;

/**
 * Liest Umsätze aus der CSV-Datei des Online-Bankings. Getestet mit dem Sparkassen-Format „CSV-CAMT“,
 * erkennt die Spalten aber am Namen und kommt deshalb auch mit „CSV-MT940“ und ähnlichen Exporten anderer Banken zurecht.
 * Reine Logik ohne Datenbank.
 */
class BankCsvParser
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    /** Mögliche Spaltennamen (klein, ohne Umlaute und Sonderzeichen) je Feld. */
    private const COLUMNS = [
        'account' => ['auftragskonto', 'ibanauftragskonto', 'kontonummerauftragskonto', 'eigenekontonummer'],
        'booking_date' => ['buchungstag', 'buchungsdatum', 'buchung'],
        'value_date' => ['valutadatum', 'valuta', 'wertstellung', 'wertstellungsdatum'],
        'booking_text' => ['buchungstext', 'umsatzart', 'vorgang'],
        'purpose' => ['verwendungszweck', 'verwendungszweckzeile1'],
        'name' => ['beguenstigterzahlungspflichtiger', 'namezahlungsbeteiligter', 'zahlungsempfaenger', 'auftraggeberbeguenstigter', 'empfaengerauftraggeber', 'name'],
        'iban' => ['kontonummeriban', 'ibanzahlungsbeteiligter', 'iban', 'kontonummer', 'gegenkontoiban'],
        'bic' => ['bicswiftcode', 'biczahlungsbeteiligter', 'bic'],
        'amount' => ['betrag', 'betrageur', 'umsatz', 'betraginEUR'],
        'currency' => ['waehrung', 'wahrung'],
        'info' => ['info', 'status'],
        'end_to_end' => ['kundenreferenzendtoend', 'endtoendreferenz', 'kundenreferenz'],
        'mandate_ref' => ['mandatsreferenz'],
        'creditor_id' => ['glaeubigerid', 'glaeubigeridentifikation'],
    ];

    /**
     * @return array{format: string, account_iban: ?string, rows: array<int, array>, skipped: int, from: ?string, to: ?string}
     *
     * @throws BankImportException
     */
    public static function parse(string $raw): array
    {
        if (strlen($raw) === 0) {
            throw new BankImportException('Die Datei ist leer.');
        }
        if (strlen($raw) > self::MAX_BYTES) {
            throw new BankImportException('Die Datei ist zu groß (höchstens 5 MB). Bitte einen kürzeren Zeitraum exportieren.');
        }
        if (str_contains(substr($raw, 0, 2000), "\0")) {
            throw new BankImportException('Das ist keine CSV-Datei. Bitte im Online-Banking das Format „CSV-CAMT“ wählen.');
        }

        $text = self::toUtf8($raw);
        $firstLine = strtok($text, "\n");
        $delimiter = substr_count((string) $firstLine, ';') >= substr_count((string) $firstLine, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $text);
        rewind($handle);

        $header = null;
        $map = [];
        $rows = [];
        $skipped = 0;
        $lineNo = 0;

        while (($cells = fgetcsv($handle, 0, $delimiter, '"', '')) !== false) {
            $lineNo++;
            if ($cells === [null] || implode('', array_map('trim', array_map('strval', $cells))) === '') {
                continue;
            }

            if ($header === null) {
                $map = self::mapHeader($cells);
                if (isset($map['booking_date'], $map['amount']) && (isset($map['purpose']) || isset($map['name']))) {
                    $header = $cells;
                }
                if ($lineNo > 20 && $header === null) {
                    break;
                }

                continue;
            }

            $get = fn (string $field) => isset($map[$field]) ? trim((string) ($cells[$map[$field]] ?? '')) : '';

            $info = mb_strtolower($get('info'));
            if (str_contains($info, 'vorgemerkt') || str_contains($info, 'vormerk')) {
                $skipped++;

                continue;
            }

            $booking = self::date($get('booking_date'));
            $amount = Money::parse($get('amount'));
            if ($booking === null || $amount === null) {
                throw new BankImportException('Zeile '.$lineNo.' kann nicht gelesen werden (Datum „'.$get('booking_date').'“, Betrag „'.$get('amount').'“).');
            }

            $iban = Stammdaten::normalizeIban($get('iban'));
            $rows[] = [
                'account' => Stammdaten::normalizeIban($get('account')) ?: null,
                'booking_date' => $booking,
                'value_date' => self::date($get('value_date')),
                'amount' => $amount,
                'currency' => strtoupper($get('currency')) ?: 'EUR',
                'booking_text' => mb_substr(self::clean($get('booking_text')), 0, 100) ?: null,
                'purpose' => self::clean($get('purpose')) ?: null,
                'name' => mb_substr(self::clean($get('name')), 0, 190) ?: null,
                'iban' => preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{8,30}$/', $iban) ? $iban : null,
                'bic' => mb_substr(strtoupper($get('bic')), 0, 11) ?: null,
                'end_to_end' => mb_substr($get('end_to_end'), 0, 100) ?: null,
                'mandate_ref' => mb_substr($get('mandate_ref'), 0, 100) ?: null,
                'creditor_id' => mb_substr($get('creditor_id'), 0, 40) ?: null,
            ];
        }
        fclose($handle);

        if ($header === null) {
            throw new BankImportException('In der Datei wurden keine Spalten „Buchungstag“ und „Betrag“ gefunden. Bitte im Online-Banking beim Export das Format „CSV-CAMT“ wählen.');
        }
        if ($rows === []) {
            throw new BankImportException('Die Datei enthält keine gebuchten Umsätze.');
        }

        $accounts = array_values(array_unique(array_filter(array_column($rows, 'account'))));
        if (count($accounts) > 1) {
            throw new BankImportException('Die Datei enthält Umsätze mehrerer Konten. Bitte je Konto eine eigene Datei exportieren.');
        }

        $rows = self::withDedupeKeys($rows);

        $dates = array_column($rows, 'booking_date');

        return [
            'format' => isset($map['end_to_end']) || isset($map['mandate_ref']) ? 'CSV-CAMT' : 'CSV',
            'account_iban' => $accounts[0] ?? null,
            'rows' => $rows,
            'skipped' => $skipped,
            'from' => min($dates),
            'to' => max($dates),
        ];
    }

    /**
     * Schlüssel gegen doppeltes Einlesen: gleiche Umsätze in einer Lieferung werden durchnummeriert.
     *
     * @param  array<int, array>  $rows
     * @return array<int, array>
     */
    public static function withDedupeKeys(array $rows): array
    {
        $seen = [];
        foreach ($rows as &$r) {
            $base = hash('sha256', implode('|', [
                $r['booking_date'], $r['value_date'], $r['amount'], $r['iban'], mb_strtolower((string) $r['name']),
                mb_strtolower((string) preg_replace('/\s+/u', ' ', (string) $r['purpose'])), $r['end_to_end'], $r['mandate_ref'],
            ]));
            $seen[$base] = ($seen[$base] ?? 0) + 1;
            $r['dedupe_key'] = hash('sha256', $base.'#'.$seen[$base]);
        }
        unset($r);

        return $rows;
    }

    /** UTF-8 bleibt, alles andere wird als Windows-1252 (ANSI) gelesen. */
    public static function toUtf8(string $raw): string
    {
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }

        return mb_check_encoding($raw, 'UTF-8') ? $raw : mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    /** 08.10.26, 08.10.2026 oder 2026-10-08 */
    public static function date(string $v): ?string
    {
        $v = trim($v);
        foreach (['!d.m.Y', '!d.m.y', '!Y-m-d'] as $f) {
            $d = DateTimeImmutable::createFromFormat($f, $v);
            if ($d !== false && $d->format(ltrim($f, '!')) === $v) {
                return $d->format('Y-m-d');
            }
        }

        return null;
    }

    private static function mapHeader(array $cells): array
    {
        $norm = array_map(fn ($c) => self::key((string) $c), $cells);
        $map = [];
        foreach (self::COLUMNS as $field => $names) {
            foreach ($names as $name) {
                $i = array_search(self::key($name), $norm, true);
                if ($i !== false) {
                    $map[$field] = $i;

                    break;
                }
            }
        }

        return $map;
    }

    private static function key(string $s): string
    {
        $s = mb_strtolower(strtr($s, ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss']));

        return (string) preg_replace('/[^a-z0-9]/', '', $s);
    }

    private static function clean(string $s): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', (string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $s)));
    }
}
