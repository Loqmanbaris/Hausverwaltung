<?php

namespace App\Support\Interest;

use App\Models\Installment;
use App\Models\InstallmentPlan;
use App\Models\Lease;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Rent\RentLedger;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Ratenvereinbarungen (Paket 14).
 *
 * Überwachung über den Kontostand: Der überfällige Rückstand darf höchstens so hoch sein wie
 * die Schuld bei Abschluss minus die bis heute fälligen Raten. Die laufende Miete ist zusätzlich zu zahlen.
 */
class Raten
{
    /**
     * Ratenplan berechnen.
     *
     * @return array<int, array{no: int, due: string, amount: string}>
     */
    public static function schedule(string $debt, ?int $count, ?string $rate, string $firstDue): array
    {
        $total = (int) round((float) $debt * 100);
        if ($total <= 0) {
            throw new RuntimeException('Der Betrag muss größer als 0 sein.');
        }
        if ($count !== null && $count > 0) {
            $per = intdiv($total, $count);
            $amounts = array_fill(0, $count, $per);
            $amounts[$count - 1] += $total - $per * $count;
        } elseif ($rate !== null && (float) $rate > 0) {
            $per = (int) round((float) $rate * 100);
            $amounts = [];
            $rest = $total;
            while ($rest > 0) {
                $amounts[] = min($per, $rest);
                $rest -= $per;
                if (count($amounts) > 120) {
                    throw new RuntimeException('Mehr als 120 Raten sind nicht möglich. Bitte eine höhere Rate wählen.');
                }
            }
        } else {
            throw new RuntimeException('Bitte die Anzahl der Raten oder die Höhe einer Rate angeben.');
        }
        if (count($amounts) > 120) {
            throw new RuntimeException('Mehr als 120 Raten sind nicht möglich.');
        }
        $first = new DateTimeImmutable($firstDue);
        $day = (int) $first->format('j');
        $out = [];
        foreach ($amounts as $i => $c) {
            $m = $first->modify('first day of +'.$i.' month');
            $d = min($day, (int) $m->format('t'));
            $out[] = ['no' => $i + 1, 'due' => $m->format('Y-m-').str_pad((string) $d, 2, '0', STR_PAD_LEFT), 'amount' => number_format($c / 100, 2, '.', '')];
        }

        return $out;
    }

    public static function create(Lease $lease, string $debt, array $schedule, ?string $note, User $user, ?string $today = null): InstallmentPlan
    {
        if (InstallmentPlan::query()->where('lease_id', $lease->id)->where('status', 'aktiv')->exists()) {
            throw new RuntimeException('Für diesen Mietvertrag läuft schon eine Ratenvereinbarung.');
        }

        return DB::transaction(function () use ($lease, $debt, $schedule, $note, $user, $today) {
            $plan = InstallmentPlan::create([
                'lease_id' => $lease->id, 'owner_id' => $lease->owner_id, 'agreed_on' => $today ?? date('Y-m-d'),
                'debt' => $debt, 'status' => 'aktiv', 'note' => $note ?: null, 'created_by' => $user->id,
            ]);
            foreach ($schedule as $s) {
                Installment::create(['installment_plan_id' => $plan->id, 'no' => $s['no'], 'due_date' => $s['due'], 'amount' => $s['amount']]);
            }
            Audit::log('installments.created', $plan, 'Ratenvereinbarung über '.Money::format($debt).' in '.count($schedule).' Raten: '.$lease->title());

            return $plan;
        });
    }

    /**
     * Stand der Vereinbarung.
     *
     * @return array{overdue: float, allowed: float, due_sum: float, covered: float, ok: bool, done: bool, items: array}
     */
    public static function state(InstallmentPlan $plan, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $overdue = (float) RentLedger::balance($plan->lease, $today)['overdue'];
        $debt = (float) $plan->debt;
        $covered = max(0.0, round($debt - $overdue, 2));     // laut Kontostand schon abgezahlt
        $dueSum = 0.0;
        $cum = 0.0;
        $items = [];
        foreach ($plan->installments as $i) {
            $cum = round($cum + (float) $i->amount, 2);
            $isDue = $i->due_date->format('Y-m-d') < $today;
            if ($isDue) {
                $dueSum = $cum;
            }
            $items[] = [
                'no' => $i->no, 'due' => $i->due_date, 'amount' => (float) $i->amount,
                'state' => $covered + 0.004 >= $cum ? 'bezahlt' : ($isDue ? 'offen' : 'kommt'),
            ];
        }
        $allowed = max(0.0, round($debt - $dueSum, 2));

        return [
            'overdue' => $overdue,
            'allowed' => $allowed,
            'due_sum' => $dueSum,
            'covered' => $covered,
            'ok' => $overdue <= $allowed + 0.004,
            'done' => $overdue <= 0.004 && $covered + 0.004 >= $debt,
            'items' => $items,
        ];
    }

    /** Aktive Vereinbarung eines Vertrags. */
    public static function activeFor(int $leaseId): ?InstallmentPlan
    {
        return InstallmentPlan::query()->where('lease_id', $leaseId)->where('status', 'aktiv')->with('installments', 'lease')->first();
    }

    /** Täglich: erfüllte Vereinbarungen automatisch abschließen. */
    public static function refreshAll(): int
    {
        $n = 0;
        foreach (InstallmentPlan::query()->where('status', 'aktiv')->with('installments', 'lease')->get() as $p) {
            if ($p->lease && self::state($p)['done']) {
                $p->update(['status' => 'erfuellt']);
                Audit::log('installments.fulfilled', $p, 'Ratenvereinbarung erfüllt: '.$p->lease->title());
                $n++;
            }
        }

        return $n;
    }

    public static function setStatus(InstallmentPlan $plan, string $status): void
    {
        $plan->update(['status' => $status]);
        Audit::log('installments.'.$status, $plan, 'Ratenvereinbarung '.InstallmentPlan::STATUS[$status].': '.$plan->lease?->title());
    }
}
