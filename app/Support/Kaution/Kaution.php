<?php

namespace App\Support\Kaution;

use App\Models\Deposit;
use App\Models\DepositEntry;
use App\Models\Lease;
use App\Models\RentCharge;
use App\Models\User;
use App\Support\Audit;
use App\Support\Rent\Erhoehung\Regeln;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Kautionsverwaltung (Paket 34). Rechtsgrundlage: § 551 BGB (höchstens drei Monatskaltmieten, Zahlung in bis zu drei gleichen
 * Monatsraten, getrennte verzinste Anlage, Zinsen stehen dem Mieter zu und erhöhen die Kaution).
 * Beträge in Cent.
 */
class Kaution
{
    /** Zahltermine und Raten: erste Rate bei Mietbeginn, die weiteren monatlich danach. Der Rest der Teilung liegt in der ersten Rate. */
    public static function schedule(int $agreedCents, string $start, int $installments): array
    {
        $n = max(1, min(3, $installments));
        $each = intdiv($agreedCents, $n);
        $rows = [];
        for ($i = 0; $i < $n; $i++) {
            $rows[] = ['due_on' => $i === 0 ? $start : Regeln::addMonths($start, $i), 'cents' => $each + ($i === 0 ? $agreedCents - $each * $n : 0)];
        }

        return $rows;
    }

    /** Zinsen (Näherung) auf einen Betrag für einen Zeitraum nach Tagen (365). */
    public static function interestCents(int $balanceCents, float $ratePercent, string $from, string $to): int
    {
        $days = max(0, (int) round((strtotime($to) - strtotime($from)) / 86400));

        return (int) round($balanceCents * $ratePercent / 100 * $days / 365);
    }

    /**
     * Stand der Kaution.
     *
     * @return array<string, mixed>
     */
    public static function state(Lease $lease, ?Deposit $d, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $agreed = Regeln::cents($lease->deposit_amount ?? 0);
        $entries = $d ? $d->entries()->get() : collect();
        $sum = fn (string $k) => (int) round(((float) $entries->where('kind', $k)->sum('amount')) * 100);
        $paid = $sum('einzahlung');
        $interest = $sum('zinsen');
        $held = $paid + $interest - $sum('einbehalt') - $sum('rueckzahlung');
        $sched = self::schedule($agreed, $lease->start_date->format('Y-m-d'), (int) ($d?->installments ?? 1));
        $due = 0;
        $next = null;
        foreach ($sched as $r) {
            if ($r['due_on'] <= $today) {
                $due += $r['cents'];
            } elseif ($next === null) {
                $next = $r;
            }
        }
        $overdue = max(0, $due - $paid);
        $rent = (int) Regeln::cents($lease->currentRent()?->rent_cold ?? 0);

        return [
            'agreed' => $agreed, 'paid' => $paid, 'interest' => $interest, 'withheld' => $sum('einbehalt'), 'returned' => $sum('rueckzahlung'),
            'held' => $held, 'schedule' => $sched, 'due' => $due, 'overdue' => $overdue, 'open' => max(0, $agreed - $paid), 'next' => $next,
            'too_high' => $rent > 0 && $agreed > $rent * 3,
            'settled' => $d?->status === 'abgerechnet',
        ];
    }

    /** Offene Forderungen des Vermieters aus dem Mieterkonto, als Vorschlag für die Abrechnung (Cent). */
    public static function openClaims(Lease $lease): int
    {
        $sum = RentCharge::query()->where('lease_id', $lease->id)->whereColumn('paid_amount', '<', 'total')->get()->sum(fn ($c) => $c->openAmount());

        return (int) round($sum * 100);
    }

    /**
     * Vorschau der Abrechnung: Kaution plus Zinsen minus Einbehalte.
     *
     * @param  array<int, array{reason: string, cents: int}>  $withholdings
     * @return array{held: int, withheld: int, payout: int, claim: int}
     */
    public static function settlement(int $heldCents, array $withholdings): array
    {
        $w = array_sum(array_column($withholdings, 'cents'));
        $left = $heldCents - $w;

        return ['held' => $heldCents, 'withheld' => $w, 'payout' => max(0, $left), 'claim' => max(0, -$left)];
    }

    // ------------------------------------------------------------------ Buchungen

    public static function forLease(Lease $lease): Deposit
    {
        return Deposit::query()->firstOrCreate(['lease_id' => $lease->id], ['owner_id' => $lease->owner_id]);
    }

    public static function addEntry(Deposit $d, string $on, string $kind, int $cents, ?string $note, User $user): DepositEntry
    {
        if (! array_key_exists($kind, Deposit::KINDS)) {
            throw new RuntimeException('Unbekannte Buchungsart.');
        }
        if ($cents <= 0) {
            throw new RuntimeException('Der Betrag muss größer als null sein.');
        }
        if ($d->status === 'abgerechnet') {
            throw new RuntimeException('Die Kaution ist schon abgerechnet. Zum Ändern zuerst die Abrechnung zurücknehmen.');
        }
        $e = $d->entries()->create(['entry_on' => $on, 'kind' => $kind, 'amount' => Regeln::euro($cents), 'note' => $note ? mb_substr($note, 0, 190) : null, 'created_by' => $user->id]);
        Audit::log('deposit.entry', $d, 'Kaution '.Deposit::KINDS[$kind].': '.Regeln::eur($cents).' ('.$d->lease?->title().')');

        return $e;
    }

    /**
     * Schließt die Kaution ab: Einbehalte und Auszahlung werden gebucht. Gibt es mehr einzubehalten als vorhanden ist, bleibt eine Nachforderung.
     *
     * @param  array<int, array{reason: string, cents: int}>  $withholdings
     */
    public static function settle(Deposit $d, string $on, array $withholdings, User $user): array
    {
        if ($d->status === 'abgerechnet') {
            throw new RuntimeException('Die Kaution ist schon abgerechnet.');
        }
        $lease = $d->lease()->first();
        $state = self::state($lease, $d, $on);
        $res = self::settlement($state['held'], $withholdings);

        DB::transaction(function () use ($d, $on, $withholdings, $res, $user) {
            $left = $res['held'];
            foreach ($withholdings as $w) {
                $take = min($w['cents'], max(0, $left));
                if ($take > 0) {
                    $d->entries()->create(['entry_on' => $on, 'kind' => 'einbehalt', 'amount' => Regeln::euro($take), 'note' => mb_substr($w['reason'], 0, 190), 'created_by' => $user->id]);
                    $left -= $take;
                }
            }
            if ($res['payout'] > 0) {
                $d->entries()->create(['entry_on' => $on, 'kind' => 'rueckzahlung', 'amount' => Regeln::euro($res['payout']), 'note' => 'Auszahlung an den Mieter', 'created_by' => $user->id]);
            }
            $d->update(['status' => 'abgerechnet', 'settled_on' => $on, 'claim' => Regeln::euro($res['claim'])]);
            Audit::log('deposit.settled', $d, 'Kaution abgerechnet: Auszahlung '.Regeln::eur($res['payout']).', Einbehalt '.Regeln::eur($res['withheld']).($res['claim'] > 0 ? ', Nachforderung '.Regeln::eur($res['claim']) : ''));
        });

        return $res;
    }

    public static function reopen(Deposit $d, User $user): void
    {
        if ($d->status !== 'abgerechnet') {
            throw new RuntimeException('Die Kaution ist nicht abgerechnet.');
        }
        DB::transaction(function () use ($d) {
            $d->entries()->whereIn('kind', ['einbehalt', 'rueckzahlung'])->whereDate('entry_on', $d->settled_on?->format('Y-m-d'))->delete();
            $d->update(['status' => 'offen', 'settled_on' => null, 'claim' => 0]);
        });
        Audit::log('deposit.reopened', $d, 'Kautionsabrechnung zurückgenommen: '.$d->lease?->title());
    }
}
