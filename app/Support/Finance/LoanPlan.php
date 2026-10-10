<?php

namespace App\Support\Finance;

use App\Models\Loan;
use DateTimeImmutable;

/**
 * Tilgungsplan eines Darlehens (Paket 23).
 *
 * Zinsen je Zahlung = Restschuld × Zinssatz × Monate/12 (übliche Rechnung der Banken bei nachschüssiger Zahlung).
 * Ereignisse (Sondertilgung, neuer Zinssatz/neue Rate, Restschuld laut Bank) werden zum jeweiligen Datum berücksichtigt.
 * Das Ergebnis ist ein Plan; maßgeblich sind die Kontoauszüge und Jahresbescheinigungen der Bank.
 */
class LoanPlan
{
    private const MAX_ROWS = 1200;

    /** @var array<int, array> */
    private static array $cache = [];

    public static function forget(): void
    {
        self::$cache = [];
    }

    /** Rate je Zahlung (Annuität aus anfänglicher Tilgung, falls keine Rate angegeben). */
    public static function installment(Loan $loan): float
    {
        if ($loan->installment !== null && (float) $loan->installment > 0) {
            return (float) $loan->installment;
        }
        if ($loan->kind === 'annuitaet' && $loan->initial_repayment !== null) {
            return round((float) $loan->principal * ((float) $loan->interest_rate + (float) $loan->initial_repayment) / 100 * $loan->interval_months / 12, 2);
        }

        return 0.0;
    }

    /**
     * @return array<int, array{date: string, type: string, payment: float, interest: float, principal: float, balance: float, rate: float}>
     */
    public static function schedule(Loan $loan): array
    {
        $events = $loan->relationLoaded('events') ? $loan->events : ($loan->exists ? $loan->events()->get() : collect());
        $key = md5(json_encode($loan->only(['principal', 'interest_rate', 'installment', 'initial_repayment', 'interval_months', 'kind', 'first_payment_on', 'free_until', 'end_date', 'paid_out_on']))
            .'|'.$events->map(fn ($e) => $e->id.':'.$e->type.':'.$e->date?->format('Y-m-d').':'.$e->amount.':'.$e->new_rate.':'.$e->new_installment)->implode(','));
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }
        $changes = $events->whereIn('type', ['sondertilgung', 'zinsaenderung', 'saldo'])->sortBy(fn ($e) => $e->date->format('Y-m-d').'-'.$e->id)->values();

        $balance = (float) $loan->principal;
        $rate = (float) $loan->interest_rate;
        $inst = self::installment($loan);
        $iv = max(1, (int) $loan->interval_months);
        $first = DateTimeImmutable::createFromInterface($loan->first_payment_on);
        $free = $loan->free_until?->format('Y-m-d');
        $end = $loan->end_date?->format('Y-m-d');
        $rows = [];
        $ci = 0;
        $limitYears = 60;

        for ($k = 0; $k < self::MAX_ROWS && $balance > 0.005; $k++) {
            $date = self::addMonths($first, $k * $iv);
            if ((int) substr($date, 0, 4) > (int) $first->format('Y') + $limitYears) {
                break;
            }
            // Ereignisse bis einschließlich zu diesem Termin
            while ($ci < $changes->count() && $changes[$ci]->date->format('Y-m-d') <= $date) {
                $e = $changes[$ci++];
                $d = $e->date->format('Y-m-d');
                if ($e->type === 'sondertilgung') {
                    $amt = min($balance, (float) $e->amount);
                    $balance = round($balance - $amt, 2);
                    $rows[] = ['date' => $d, 'type' => 'sondertilgung', 'payment' => $amt, 'interest' => 0.0, 'principal' => $amt, 'balance' => $balance, 'rate' => $rate];
                } elseif ($e->type === 'saldo') {
                    $balance = round(max(0, (float) $e->amount), 2);
                    $rows[] = ['date' => $d, 'type' => 'saldo', 'payment' => 0.0, 'interest' => 0.0, 'principal' => 0.0, 'balance' => $balance, 'rate' => $rate];
                } elseif ($e->type === 'zinsaenderung') {
                    if ($e->new_rate !== null) {
                        $rate = (float) $e->new_rate;
                    }
                    if ($e->new_installment !== null && (float) $e->new_installment > 0) {
                        $inst = (float) $e->new_installment;
                    }
                    $rows[] = ['date' => $d, 'type' => 'zinsaenderung', 'payment' => 0.0, 'interest' => 0.0, 'principal' => 0.0, 'balance' => $balance, 'rate' => $rate];
                }
            }
            if ($balance <= 0.005) {
                break;
            }

            $interest = round($balance * $rate / 100 * $iv / 12, 2);
            $isFree = $free !== null && $date <= $free;
            $principal = 0.0;
            $kind = $loan->kind;
            if ($kind === 'privat') {
                $kind = $inst > 0 ? 'annuitaet' : 'endfaellig';
            }
            if (! $isFree) {
                $principal = match ($kind) {
                    'annuitaet' => max(0.0, $inst - $interest),
                    'tilgung' => $inst,
                    default => 0.0,
                };
            }
            // Endfällig: Rückzahlung zum Enddatum (erster Termin an oder nach dem Enddatum)
            if ($end !== null && $date >= $end) {
                $principal = $balance;
            }
            $principal = round(min($balance, $principal), 2);
            $balance = round($balance - $principal, 2);
            $rows[] = ['date' => $date, 'type' => 'rate', 'payment' => round($interest + $principal, 2), 'interest' => $interest, 'principal' => $principal, 'balance' => $balance, 'rate' => $rate];

            // Ohne Tilgung und ohne Enddatum nicht endlos rechnen
            if ($principal <= 0 && ! $isFree && $end === null && $k > 12 * 12 / $iv) {
                break;
            }
        }

        self::$cache[$key] = $rows;

        return $rows;
    }

    /** Monate addieren ohne Überlauf (31.01. + 1 Monat = 28./29.02.). */
    public static function addMonths(DateTimeImmutable $d, int $months): string
    {
        $y = (int) $d->format('Y');
        $m = (int) $d->format('n') + $months;
        $y += intdiv($m - 1, 12);
        $m = ($m - 1) % 12 + 1;
        $last = (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $y, $m)))->format('t');

        return sprintf('%04d-%02d-%02d', $y, $m, min((int) $d->format('j'), $last));
    }

    /** Restschuld an einem Tag (nach allen Zahlungen bis einschließlich). */
    public static function balanceOn(Loan $loan, string $date): float
    {
        $balance = (float) $loan->principal;
        if ($loan->paid_out_on && $loan->paid_out_on->format('Y-m-d') > $date) {
            return 0.0;
        }
        foreach (self::schedule($loan) as $r) {
            if ($r['date'] > $date) {
                break;
            }
            $balance = $r['balance'];
        }

        return $balance;
    }

    /** Zinsen eines Jahres: laut Bank (falls eingetragen), sonst laut Plan. */
    public static function interestForYear(Loan $loan, int $year): array
    {
        $events = $loan->relationLoaded('events') ? $loan->events : $loan->events()->get();
        $bank = $events->where('type', 'jahreszinsen')->first(fn ($e) => (int) $e->date->format('Y') === $year);
        if ($bank) {
            return ['amount' => round((float) $bank->amount, 2), 'source' => 'bank'];
        }
        $sum = 0.0;
        foreach (self::schedule($loan) as $r) {
            if ((int) substr($r['date'], 0, 4) === $year) {
                $sum += $r['interest'];
            }
        }

        return ['amount' => round($sum, 2), 'source' => 'plan'];
    }

    /** Summen eines Jahres laut Plan: payment, interest, principal (inkl. Sondertilgungen). */
    public static function yearTotals(Loan $loan, int $year): array
    {
        $t = ['payment' => 0.0, 'interest' => 0.0, 'principal' => 0.0];
        foreach (self::schedule($loan) as $r) {
            if ((int) substr($r['date'], 0, 4) === $year) {
                $t['payment'] += $r['payment'];
                $t['interest'] += $r['interest'];
                $t['principal'] += $r['principal'];
            }
        }

        return array_map(fn ($v) => round($v, 2), $t);
    }

    /** Je Jahr: Zinsen, Tilgung, Restschuld am Jahresende. */
    public static function byYear(Loan $loan): array
    {
        $out = [];
        $balance = (float) $loan->principal;
        foreach (self::schedule($loan) as $r) {
            $y = (int) substr($r['date'], 0, 4);
            $out[$y] ??= ['year' => $y, 'payment' => 0.0, 'interest' => 0.0, 'principal' => 0.0, 'balance' => $balance];
            $out[$y]['payment'] += $r['payment'];
            $out[$y]['interest'] += $r['interest'];
            $out[$y]['principal'] += $r['principal'];
            $out[$y]['balance'] = $r['balance'];
        }

        return array_values(array_map(fn ($a) => array_map(fn ($v) => is_float($v) ? round($v, 2) : $v, $a), $out));
    }

    /** Nächste Zahlung ab heute (oder null). */
    public static function next(Loan $loan, ?string $today = null): ?array
    {
        $today ??= date('Y-m-d');
        foreach (self::schedule($loan) as $r) {
            if ($r['type'] === 'rate' && $r['date'] >= $today) {
                return $r;
            }
        }

        return null;
    }

    /** Datum der letzten Zahlung (Volltilgung) oder null, wenn nicht absehbar. */
    public static function payoffDate(Loan $loan): ?string
    {
        $rows = self::schedule($loan);
        $last = end($rows);

        return $last && $last['balance'] <= 0.005 ? $last['date'] : null;
    }

    /** Rate pro Monat (für Übersichten). */
    public static function monthly(Loan $loan, ?string $today = null): float
    {
        $n = self::next($loan, $today);

        return $n ? round($n['payment'] / max(1, (int) $loan->interval_months), 2) : 0.0;
    }

    /** Zahlung laut Plan nahe einem Datum (für den Kontoabgleich). */
    public static function expectedAround(Loan $loan, string $date, int $days = 20): ?array
    {
        $best = null;
        foreach (self::schedule($loan) as $r) {
            if ($r['type'] !== 'rate') {
                continue;
            }
            $diff = abs((strtotime($r['date']) - strtotime($date)) / 86400);
            if ($diff <= $days && ($best === null || $diff < $best['diff'])) {
                $best = $r + ['diff' => $diff];
            }
        }

        return $best;
    }
}
