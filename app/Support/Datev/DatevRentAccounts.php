<?php

namespace App\Support\Datev;

use App\Support\Audit;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Erlöskonten für Mieteinnahmen. Ein Konto je Gruppe (Vertragsart und Steuersatz) und Bestandteil.
 * Fehlt das Konto für Nebenkosten oder Heizkosten, wird das Konto „Miete“ der Gruppe genommen.
 */
class DatevRentAccounts
{
    public const GROUPS = [
        'wohnen_0' => 'Wohnraum (ohne Umsatzsteuer)',
        'gewerbe_0' => 'Gewerbe ohne Umsatzsteuer',
        'gewerbe_19' => 'Gewerbe mit 19 % Umsatzsteuer',
        'montage_7' => 'Montage-Wohnung mit 7 % Umsatzsteuer',
        'montage_0' => 'Montage-Wohnung ohne Umsatzsteuer',
    ];

    public const PARTS = [
        'miete' => 'Miete (Kaltmiete, Stellplatz, Garage, Möbel)',
        'nk' => 'Nebenkosten-Vorauszahlung',
        'heiz' => 'Heizkosten-Vorauszahlung',
    ];

    /** Bestandteil im Mietvertrag => Teil im Export */
    public const COMPONENT_PART = [
        'rent_cold' => 'miete',
        'parking' => 'miete',
        'garage' => 'miete',
        'furniture' => 'miete',
        'prepay_operating' => 'nk',
        'prepay_heating' => 'heiz',
    ];

    public static function group(string $leaseType, mixed $vatRate): string
    {
        $rate = (int) round((float) $vatRate);

        return $leaseType.'_'.$rate;
    }

    public static function groupLabel(string $group): string
    {
        if (isset(self::GROUPS[$group])) {
            return self::GROUPS[$group];
        }
        [$type, $rate] = array_pad(explode('_', $group), 2, '0');

        return ucfirst($type).' mit '.$rate.' % Umsatzsteuer';
    }

    /** @return array<string, array{account: ?string, bu_key: ?string}> */
    public static function all(): array
    {
        $out = [];
        foreach (DB::table('datev_rent_accounts')->get() as $row) {
            $out[(string) $row->rent_key] = ['account' => $row->account, 'bu_key' => $row->bu_key];
        }

        return $out;
    }

    /**
     * Konto für Gruppe und Teil, mit Rückfall auf „Miete“ der Gruppe.
     *
     * @return array{account: string, bu_key: string, key: string}|null
     */
    public static function resolve(array $all, string $group, string $part): ?array
    {
        foreach ([$group.':'.$part, $group.':miete'] as $key) {
            $acc = trim((string) ($all[$key]['account'] ?? ''));
            if ($acc !== '') {
                return ['account' => $acc, 'bu_key' => trim((string) ($all[$key]['bu_key'] ?? '')), 'key' => $key];
            }
        }

        return null;
    }

    /** @param array<string, array{account: string, bu_key: string}> $rows */
    public static function save(array $rows): int
    {
        $current = self::all();
        $old = [];
        $new = [];
        DB::transaction(function () use ($rows, $current, &$old, &$new) {
            foreach ($rows as $key => $row) {
                $account = $row['account'] === '' ? null : $row['account'];
                $bu = $row['bu_key'] === '' ? null : $row['bu_key'];
                $before = $current[$key] ?? ['account' => null, 'bu_key' => null];
                if ($before['account'] === $account && $before['bu_key'] === $bu) {
                    continue;
                }
                $old[$key] = ($before['account'] ?? '–').($before['bu_key'] ? ' / BU '.$before['bu_key'] : '');
                $new[$key] = ($account ?? '–').($bu ? ' / BU '.$bu : '');
                DB::table('datev_rent_accounts')->upsert(
                    [['rent_key' => $key, 'account' => $account, 'bu_key' => $bu, 'created_at' => now(), 'updated_at' => now()]],
                    ['rent_key'],
                    ['account', 'bu_key', 'updated_at']
                );
            }
            if ($new !== []) {
                Audit::log('datev.rent_accounts', 'Erlöskonten für Mieten geändert ('.count($new).')', null, $old, $new);
            }
        });

        return count($new);
    }

    /** Gegenkonten: Bank für Überweisung und Lastschrift, Kasse für Barzahlung (leer = Bank). */
    public static function counterAccounts(): array
    {
        return [
            'bank' => (string) (Settings::get('datev_rent_bank_account') ?? ''),
            'cash' => (string) (Settings::get('datev_rent_cash_account') ?? ''),
        ];
    }
}
