<?php

namespace App\Support\Datev;

use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/** Zuordnung der Kostenarten zu Konten im Kontenrahmen (Tabelle datev_accounts). */
class DatevAccounts
{
    /** @return array<string, array{account: ?string, bu_key: ?string}> Schlüssel der Kostenart => Konto */
    public static function all(): array
    {
        $out = [];
        foreach (DB::table('datev_accounts')->get() as $row) {
            $out[(string) $row->cost_key] = ['account' => $row->account, 'bu_key' => $row->bu_key];
        }

        return $out;
    }

    /**
     * Speichert die Zuordnung. Nur geänderte Zeilen werden geschrieben und protokolliert.
     *
     * @param  array<string, array{account: ?string, bu_key: ?string}>  $rows
     */
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

                DB::table('datev_accounts')->upsert(
                    [['cost_key' => $key, 'account' => $account, 'bu_key' => $bu, 'created_at' => now(), 'updated_at' => now()]],
                    ['cost_key'],
                    ['account', 'bu_key', 'updated_at']
                );
            }

            if ($new !== []) {
                Audit::log('datev.accounts', 'DATEV-Konten der Kostenarten geändert ('.count($new).')', null, $old, $new);
            }
        });

        return count($new);
    }
}
