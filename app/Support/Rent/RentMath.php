<?php

namespace App\Support\Rent;

use DateTimeImmutable;

/**
 * Reine Rechenlogik ohne Datenbank: Soll eines Monats, anteilige Monate, Fälligkeit, Umsatzsteuer,
 * Zuordnung von Zahlungen und Indexanpassung. Wird auch vom Selbsttest geprüft.
 */
class RentMath
{
    /**
     * Soll für einen Monat.
     *
     * @param  array{start: string, end: ?string, due_day: int, term_days: ?int, vat_rate: string|float}  $lease
     * @param  array<int, array{valid_from: string}+array<string, mixed>>  $rents  Mietstand-Zeilen (Bestandteile als Spalten)
     * @param  string  $month  erster Tag des Monats (Y-m-d)
     * @return array|null null, wenn der Vertrag in diesem Monat nicht läuft oder noch kein Mietstand gilt
     */
    public static function charge(array $lease, array $rents, string $month): ?array
    {
        $monthStart = new DateTimeImmutable($month);
        $monthEnd = $monthStart->modify('last day of this month');

        $from = max($monthStart, new DateTimeImmutable($lease['start']));
        $to = $lease['end'] ? min($monthEnd, new DateTimeImmutable($lease['end'])) : $monthEnd;
        if ($from > $to) {
            return null;
        }

        $rent = self::rentAt($rents, $from->format('Y-m-d'));
        if ($rent === null) {
            return null;
        }

        $daysInMonth = (int) $monthStart->format('t');
        $days = (int) $from->diff($to)->days + 1;
        $full = $days === $daysInMonth;

        $out = [
            'period' => $monthStart->format('Y-m-d'),
            'period_from' => $from->format('Y-m-d'),
            'period_to' => $to->format('Y-m-d'),
            'due_date' => self::dueDate($from, $lease['due_day'], $lease['term_days'])->format('Y-m-d'),
            'days' => $days,
            'days_in_month' => $daysInMonth,
        ];

        $net = 0;
        foreach (array_keys(Mieten::COMPONENTS) as $col) {
            $cents = self::cents($rent[$col] ?? 0);
            if (! $full) {
                $cents = (int) round($cents * $days / $daysInMonth);
            }
            $out[$col] = self::money($cents);
            $net += $cents;
        }

        $rate = (float) $lease['vat_rate'];
        $vat = (int) round($net * $rate / 100);

        $out['net_total'] = self::money($net);
        $out['vat_rate'] = number_format($rate, 2, '.', '');
        $out['vat_amount'] = self::money($vat);
        $out['total'] = self::money($net + $vat);

        return $out;
    }

    /** Der Mietstand, der an einem Tag gilt (letzte Zeile mit valid_from <= Tag). */
    public static function rentAt(array $rents, string $date): ?array
    {
        $found = null;
        foreach ($rents as $r) {
            if ($r['valid_from'] <= $date && ($found === null || $r['valid_from'] > $found['valid_from'])) {
                $found = $r;
            }
        }

        return $found;
    }

    /** Fälligkeit: X Tage nach Beginn des Zeitraums, sonst der X. des Monats (nie vor dem Beginn). */
    public static function dueDate(DateTimeImmutable $from, int $dueDay, ?int $termDays): DateTimeImmutable
    {
        if ($termDays !== null) {
            return $from->modify('+'.$termDays.' days');
        }

        $day = max(1, min($dueDay, (int) $from->format('t')));
        $due = $from->setDate((int) $from->format('Y'), (int) $from->format('n'), $day);

        return $due < $from ? $from : $due;
    }

    /**
     * Monate (jeweils der Erste), für die ein Soll entstehen kann: vom Vertragsbeginn bis $until, höchstens bis Vertragsende.
     *
     * @return string[]
     */
    public static function months(string $start, ?string $end, string $until): array
    {
        $m = (new DateTimeImmutable($start))->modify('first day of this month');
        $last = new DateTimeImmutable($until);
        if ($end !== null && new DateTimeImmutable($end) < $last) {
            $last = new DateTimeImmutable($end);
        }

        $out = [];
        while ($m <= $last && count($out) < 1200) {
            $out[] = $m->format('Y-m-d');
            $m = $m->modify('first day of next month');
        }

        return $out;
    }

    /**
     * Ordnet Zahlungen den Soll-Posten zu: Erst werden Rückbuchungen (negative Beträge) mit den davor liegenden
     * Zahlungen verrechnet (die jüngste zuerst), dann wird jede Zahlung dem ältesten offenen Posten zugeordnet.
     * Was übrig bleibt, ist Guthaben und wird automatisch mit späteren Posten verrechnet.
     * Hat eine Zahlung ein Ziel („target“: Posten-IDs, z. B. Miete Oktober), geht sie zuerst auf diese Posten, der Rest wie oben.
     *
     * @param  array<int, array{id: int, total: string}>  $charges  sortiert nach Fälligkeit
     * @param  array<int, array{id: int, amount: string, target?: array<int>}>  $payments  sortiert nach Zahldatum
     * @return array{allocations: array<int, array{payment: int, charge: int, amount: string}>, paid: array<int, string>, used: array<int, string>, credit: string}
     */
    public static function allocate(array $charges, array $payments): array
    {
        // 1. Rückbuchungen verrechnen
        $avail = [];
        $order = [];
        foreach ($payments as $p) {
            $c = self::cents($p['amount']);
            if ($c >= 0) {
                $avail[$p['id']] = $c;
                $order[] = $p['id'];

                continue;
            }
            $need = -$c;
            for ($i = count($order) - 1; $i >= 0 && $need > 0; $i--) {
                $take = min($need, $avail[$order[$i]]);
                $avail[$order[$i]] -= $take;
                $need -= $take;
            }
        }

        $allocations = [];
        $paid = [];
        $used = array_fill_keys(array_column($payments, 'id'), 0);
        $left = [];
        foreach ($charges as $ch) {
            $left[$ch['id']] = self::cents($ch['total']);
            $paid[$ch['id']] = 0;
        }

        // 2. Zahlungen mit Ziel (gewählter Monat oder Einnahmeart) zuerst auf ihre Posten
        foreach ($payments as $p) {
            $pid = $p['id'];
            if (empty($p['target']) || ($avail[$pid] ?? 0) <= 0) {
                continue;
            }
            foreach ($p['target'] as $cid) {
                if (! isset($left[$cid]) || $left[$cid] <= 0 || $avail[$pid] <= 0) {
                    continue;
                }
                $take = min($left[$cid], $avail[$pid]);
                $avail[$pid] -= $take;
                $left[$cid] -= $take;
                $paid[$cid] += $take;
                $used[$pid] += $take;
                $allocations[] = ['payment' => $pid, 'charge' => $cid, 'amount' => self::money($take)];
            }
        }

        // 3. Älteste offene Posten zuerst
        $pi = 0;
        foreach ($charges as $ch) {
            $open = $left[$ch['id']];
            while ($open > 0 && $pi < count($order)) {
                $pid = $order[$pi];
                if ($avail[$pid] <= 0) {
                    $pi++;

                    continue;
                }
                $take = min($open, $avail[$pid]);
                $avail[$pid] -= $take;
                $open -= $take;
                $paid[$ch['id']] += $take;
                $used[$pid] += $take;
                $allocations[] = ['payment' => $pid, 'charge' => $ch['id'], 'amount' => self::money($take)];
            }
        }

        return [
            'allocations' => $allocations,
            'paid' => array_map([self::class, 'money'], $paid),
            'used' => array_map([self::class, 'money'], $used),
            'credit' => self::money(array_sum($avail)),
        ];
    }

    /**
     * Vorschlag für eine Indexanpassung der Kaltmiete (§ 557b BGB): neue Miete im Verhältnis der Indexwerte.
     *
     * @return array{new_cold: string, change_percent: string, earliest: string}
     */
    public static function indexProposal(string $oldCold, string $baseIndex, string $newIndex, string $lastChange, DateTimeImmutable $today): array
    {
        $base = (float) $baseIndex;
        if ($base <= 0) {
            throw new \InvalidArgumentException('Der alte Indexwert fehlt.');
        }
        $factor = (float) $newIndex / $base;
        $newCold = (int) round(self::cents($oldCold) * $factor);

        // Frühestens zu Beginn des übernächsten Monats nach der Erklärung, und mindestens ein Jahr nach der letzten Änderung.
        $byNotice = $today->modify('first day of next month')->modify('first day of next month');
        $byYear = (new DateTimeImmutable($lastChange))->modify('+1 year');
        if ((int) $byYear->format('j') !== 1) {
            $byYear = $byYear->modify('first day of next month');
        }

        return [
            'new_cold' => self::money($newCold),
            'change_percent' => number_format(($factor - 1) * 100, 2, '.', ''),
            'earliest' => max($byNotice, $byYear)->format('Y-m-d'),
        ];
    }

    public static function cents(mixed $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
