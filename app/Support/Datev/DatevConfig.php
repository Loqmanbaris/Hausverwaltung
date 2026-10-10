<?php

namespace App\Support\Datev;

use App\Support\Settings;
use DateTimeImmutable;

/** Einstellungen für den DATEV-Export (stehen in der Tabelle settings, Schlüssel datev_*). */
class DatevConfig
{
    /** Vorgaben, solange nichts gespeichert ist. */
    public const DEFAULTS = [
        'datev_consultant' => '10012',
        'datev_client' => '10011',
        'datev_skr' => '04',
        'datev_fy_month' => '1',
        'datev_account_length' => '4',
        'datev_counter_account' => '',
    ];

    /**
     * @return array{consultant: string, client: string, skr: string, fy_month: int, account_length: int, counter_account: string}
     */
    public static function load(): array
    {
        $get = fn (string $key) => (string) (Settings::get($key) ?? self::DEFAULTS[$key]);

        return [
            'consultant' => $get('datev_consultant'),
            'client' => $get('datev_client'),
            'skr' => $get('datev_skr'),
            'fy_month' => (int) $get('datev_fy_month'),
            'account_length' => (int) $get('datev_account_length'),
            'counter_account' => $get('datev_counter_account'),
        ];
    }

    /**
     * Prüft die Einstellungen. Gibt verständliche Meldungen zurück, leer wenn alles stimmt.
     *
     * @param  array{consultant: string, client: string, skr: string, fy_month: int, account_length: int, counter_account: string}  $c
     * @return string[]
     */
    public static function problems(array $c): array
    {
        $problems = [];
        if (preg_match('/^[1-9]\d{3,6}$/', $c['consultant']) !== 1) {
            $problems[] = 'Die Beraternummer fehlt oder ist ungültig (4 bis 7 Ziffern).';
        }
        if (preg_match('/^[1-9]\d{0,4}$/', $c['client']) !== 1) {
            $problems[] = 'Die Mandantennummer fehlt oder ist ungültig (1 bis 5 Ziffern).';
        }
        if (! in_array($c['skr'], ['03', '04'], true)) {
            $problems[] = 'Der Kontenrahmen muss SKR03 oder SKR04 sein.';
        }
        if ($c['fy_month'] < 1 || $c['fy_month'] > 12) {
            $problems[] = 'Der Beginn des Wirtschaftsjahres ist ungültig.';
        }
        if ($c['account_length'] < 4 || $c['account_length'] > 8) {
            $problems[] = 'Die Sachkontenlänge muss zwischen 4 und 8 liegen.';
        }
        if ($c['counter_account'] === '') {
            $problems[] = 'Das Gegenkonto fehlt (zum Beispiel Bank oder Verbindlichkeiten). Bitte mit dem Steuerberater abstimmen.';
        } elseif (! self::validCounterAccount($c['counter_account'], $c['account_length'])) {
            $problems[] = 'Das Gegenkonto ist ungültig: Es braucht '.$c['account_length'].' Ziffern (Sachkonto) oder '.($c['account_length'] + 1).' Ziffern (Personenkonto).';
        }

        return $problems;
    }

    /** Sachkonto: genau so viele Ziffern wie die Sachkontenlänge. */
    public static function validAccount(string $account, int $length): bool
    {
        return preg_match('/^\d{'.$length.'}$/', $account) === 1 && (int) $account > 0;
    }

    /** Gegenkonto: Sachkonto oder Personenkonto (eine Stelle länger). */
    public static function validCounterAccount(string $account, int $length): bool
    {
        return (self::validAccount($account, $length) || preg_match('/^\d{'.($length + 1).'}$/', $account) === 1) && (int) $account > 0;
    }

    /** Beginn des Wirtschaftsjahres, in dem $date liegt. */
    public static function fiscalYearStart(DateTimeImmutable $date, int $startMonth): DateTimeImmutable
    {
        $year = (int) $date->format('Y');
        if ((int) $date->format('n') < $startMonth) {
            $year--;
        }

        return new DateTimeImmutable(sprintf('%04d-%02d-01', $year, $startMonth));
    }

    /** Letzter Tag des Wirtschaftsjahres, das am $start beginnt. */
    public static function fiscalYearEnd(DateTimeImmutable $start): DateTimeImmutable
    {
        return $start->modify('+1 year')->modify('-1 day');
    }
}
