<?php

namespace App\Support\Receipts;

use DateTimeImmutable;

/** Rechnet aus, wann wiederkehrende Ausgaben fällig sind, und ordnet Belege den Terminen zu. */
class RecurrenceSchedule
{
    public const FREQUENCIES = [
        'monthly' => 'monatlich',
        'quarterly' => 'vierteljährlich',
        'half_yearly' => 'halbjährlich',
        'yearly' => 'jährlich',
    ];

    public const MONTHS = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni',
        7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember',
    ];

    /** Ein Beleg zählt zu einem Termin, wenn sein Datum so viele Tage vor oder nach dem Termin liegt. */
    public const DAYS_BEFORE = 20;

    public const DAYS_AFTER = 40;

    /** So lange nach dem Termin ist ein fehlender Beleg „fällig“, danach „fehlt“. */
    public const GRACE_DAYS = 20;

    /**
     * Fälligkeitstermine zwischen $from und $to (beide eingeschlossen), aufsteigend.
     * Der Tag wird auf den letzten Tag eines kurzen Monats gekürzt (31. im Februar = 28. oder 29.).
     *
     * @return DateTimeImmutable[]
     */
    public static function dueDates(
        string $frequency,
        int $month,
        int $day,
        ?string $start,
        ?string $end,
        DateTimeImmutable $from,
        DateTimeImmutable $to
    ): array {
        $step = match ($frequency) {
            'monthly' => 1,
            'quarterly' => 3,
            'half_yearly' => 6,
            'yearly' => 12,
            default => 0,
        };
        if ($step === 0 || $from > $to) {
            return [];
        }

        $month = max(1, min(12, $month));
        $day = max(1, min(31, $day));
        $startDate = $start !== null ? new DateTimeImmutable($start) : null;
        $endDate = $end !== null ? new DateTimeImmutable($end) : null;

        $cursor = $from->modify('first day of this month')->setTime(0, 0);
        $last = $to->modify('first day of this month')->setTime(0, 0);
        $dates = [];

        while ($cursor <= $last) {
            $m = (int) $cursor->format('n');
            if ((($m - $month) % $step + $step) % $step === 0) {
                $dim = (int) $cursor->format('t');
                $due = $cursor->setDate((int) $cursor->format('Y'), $m, min($day, $dim))->setTime(0, 0);

                if ($due >= $from->setTime(0, 0) && $due <= $to->setTime(0, 0)
                    && ($startDate === null || $due >= $startDate)
                    && ($endDate === null || $due <= $endDate)) {
                    $dates[] = $due;
                }
            }
            $cursor = $cursor->modify('first day of next month');
        }

        return $dates;
    }

    /**
     * Ordnet jedem Termin höchstens einen Beleg zu (der nächstliegende, jeder Beleg nur einmal).
     *
     * @param  DateTimeImmutable[]  $dueDates
     * @param  array<int, array{id: int, date: DateTimeImmutable}>  $receipts
     * @return array<int, ?int> Index des Termins => Beleg-Nr. oder null
     */
    public static function match(array $dueDates, array $receipts): array
    {
        $used = [];
        $result = [];

        foreach ($dueDates as $i => $due) {
            $best = null;
            $bestDistance = null;
            $from = $due->modify('-'.self::DAYS_BEFORE.' days');
            $to = $due->modify('+'.self::DAYS_AFTER.' days');

            foreach ($receipts as $receipt) {
                if (isset($used[$receipt['id']]) || $receipt['date'] < $from || $receipt['date'] > $to) {
                    continue;
                }
                $distance = abs((int) $receipt['date']->diff($due)->format('%a'));
                if ($best === null || $distance < $bestDistance) {
                    $best = $receipt['id'];
                    $bestDistance = $distance;
                }
            }

            if ($best !== null) {
                $used[$best] = true;
            }
            $result[$i] = $best;
        }

        return $result;
    }

    /** „jährlich im März zum 15.“ */
    public static function describe(string $frequency, int $month, int $day): string
    {
        $label = self::FREQUENCIES[$frequency] ?? $frequency;

        return match ($frequency) {
            'monthly' => $label.' zum '.$day.'.',
            'yearly' => $label.' am '.$day.'. '.(self::MONTHS[$month] ?? ''),
            default => $label.', ab '.(self::MONTHS[$month] ?? '').' zum '.$day.'.',
        };
    }
}
