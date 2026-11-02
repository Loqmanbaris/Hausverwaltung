<?php

namespace App\Support\Bank;

use App\Models\BankTransaction;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Monatsauswahl für Kontoauszüge (Eingänge und Ausgaben).
 * Die Wahl steht in der Sitzung, damit sie nach dem Zuordnen erhalten bleibt und beide Reiter denselben Monat zeigen.
 */
class Monatsfilter
{
    public const SESSION = 'bank_monat';

    /** Gewählter Monat als „JJJJ-MM“ oder null für alle Monate. */
    public static function current(Request $request): ?string
    {
        if ($request->has('monat')) {
            $value = (string) $request->query('monat', '');
            $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value) === 1 ? $value : null;
            $request->session()->put(self::SESSION, $month);

            return $month;
        }
        $saved = $request->session()->get(self::SESSION);

        return is_string($saved) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $saved) === 1 ? $saved : null;
    }

    /** Abfrage auf den Monat einschränken (nichts tun bei „alle Monate“). */
    public static function apply(Builder $query, ?string $month): Builder
    {
        if ($month === null) {
            return $query;
        }
        $from = new DateTimeImmutable($month.'-01');

        return $query->whereBetween('booking_date', [$from->format('Y-m-d'), $from->modify('last day of this month')->format('Y-m-d')]);
    }

    /**
     * Auswahlliste: alle Monate vom ältesten bis zum neuesten Umsatz, neueste zuerst.
     *
     * @param  array<int>  $ownerIds
     * @return array<string, string> „JJJJ-MM“ => „Oktober 2026“
     */
    public static function options(array $ownerIds, ?string $selected = null): array
    {
        $range = BankTransaction::query()->whereIn('owner_id', $ownerIds)
            ->selectRaw('min(booking_date) as a, max(booking_date) as b')->first();
        $names = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        $out = [];
        if ($range?->a && $range?->b) {
            $cur = new DateTimeImmutable(substr((string) $range->b, 0, 7).'-01');
            $end = new DateTimeImmutable(substr((string) $range->a, 0, 7).'-01');
            for ($i = 0; $cur >= $end && $i < 240; $i++, $cur = $cur->modify('-1 month')) {
                $out[$cur->format('Y-m')] = $names[(int) $cur->format('n')].' '.$cur->format('Y');
            }
        }
        if ($selected !== null && ! isset($out[$selected])) {
            $d = new DateTimeImmutable($selected.'-01');
            $out = [$selected => $names[(int) $d->format('n')].' '.$d->format('Y')] + $out;
        }

        return $out;
    }

    /** Vorheriger und nächster Monat für die Pfeile (null, wenn es keinen gibt). */
    public static function neighbours(array $options, ?string $month): array
    {
        if ($month === null) {
            return [null, null];
        }
        $keys = array_keys($options);
        $i = array_search($month, $keys, true);
        if ($i === false) {
            return [null, null];
        }

        return [$keys[$i + 1] ?? null, $keys[$i - 1] ?? null];
    }
}
