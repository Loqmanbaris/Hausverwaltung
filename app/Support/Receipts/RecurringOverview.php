<?php

namespace App\Support\Receipts;

use App\Models\Receipt;
use App\Models\RecurringExpense;
use App\Models\User;
use DateTimeImmutable;

/**
 * Zeigt, welche wiederkehrenden Ausgaben bald fällig sind und ob es schon einen passenden Beleg gibt.
 *
 * Ein Beleg passt, wenn Eigentümer, Kostenart und Objekt (bzw. Einheit) stimmen und sein Datum
 * nahe genug am Fälligkeitstermin liegt (siehe RecurrenceSchedule).
 * Stand: erfasst (Beleg gefunden), bald (Termin liegt noch vor uns), faellig (Termin ist vorbei,
 * es ist aber noch nicht lange her), fehlt (Termin ist länger vorbei, kein Beleg).
 */
class RecurringOverview
{
    public const DAYS_BACK = 60;

    public const DAYS_AHEAD = 30;

    /**
     * @return array<int, array{expense: RecurringExpense, due: DateTimeImmutable, receipt_id: ?int, state: string}>
     */
    public static function forUser(User $user, ?DateTimeImmutable $today = null): array
    {
        $today = ($today ?? new DateTimeImmutable('today'))->setTime(0, 0);
        $from = $today->modify('-'.self::DAYS_BACK.' days');
        $to = $today->modify('+'.self::DAYS_AHEAD.' days');

        $expenses = RecurringExpense::query()
            ->visibleTo($user)
            ->where('active', true)
            ->with(['owner', 'property', 'unit'])
            ->get();

        $rows = [];
        foreach ($expenses as $expense) {
            $dates = $expense->dueDates($from, $to);
            if ($dates === []) {
                continue;
            }

            $matched = RecurrenceSchedule::match($dates, self::candidates($expense, $user, $dates));

            foreach ($dates as $i => $due) {
                $receiptId = $matched[$i] ?? null;
                $rows[] = [
                    'expense' => $expense,
                    'due' => $due,
                    'receipt_id' => $receiptId,
                    'state' => self::state($due, $receiptId, $today),
                ];
            }
        }

        usort($rows, fn ($a, $b) => [$a['due'], $a['expense']->id] <=> [$b['due'], $b['expense']->id]);

        return $rows;
    }

    /** Anzahl der Termine, zu denen ein Beleg noch fehlt (fällig oder überfällig). */
    public static function openCountFor(User $user, ?DateTimeImmutable $today = null): int
    {
        return count(array_filter(
            self::forUser($user, $today),
            fn ($row) => in_array($row['state'], ['faellig', 'fehlt'], true)
        ));
    }

    public static function state(DateTimeImmutable $due, ?int $receiptId, DateTimeImmutable $today): string
    {
        if ($receiptId !== null) {
            return 'erfasst';
        }
        if ($due > $today) {
            return 'bald';
        }

        return $due >= $today->modify('-'.RecurrenceSchedule::GRACE_DAYS.' days') ? 'faellig' : 'fehlt';
    }

    /**
     * Belege, die für diese Ausgabe in Frage kommen.
     *
     * @param  DateTimeImmutable[]  $dates
     * @return array<int, array{id: int, date: DateTimeImmutable}>
     */
    private static function candidates(RecurringExpense $expense, User $user, array $dates): array
    {
        $first = $dates[0]->modify('-'.RecurrenceSchedule::DAYS_BEFORE.' days');
        $last = end($dates)->modify('+'.RecurrenceSchedule::DAYS_AFTER.' days');

        $query = Receipt::query()
            ->visibleTo($user)
            ->where('receipts.owner_id', $expense->owner_id)
            ->where('receipts.cost_category', $expense->cost_category)
            ->whereBetween('receipts.receipt_date', [$first->format('Y-m-d'), $last->format('Y-m-d')]);

        if ($expense->owner_level) {
            $query->where('receipts.owner_level', true);
        } else {
            $query->where('receipts.property_id', $expense->property_id);
            if ($expense->unit_id) {
                $query->where('receipts.unit_id', $expense->unit_id);
            }
        }

        return $query->orderBy('receipts.receipt_date')->get(['receipts.id', 'receipts.receipt_date'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'date' => new DateTimeImmutable($r->receipt_date->format('Y-m-d'))])
            ->all();
    }
}
