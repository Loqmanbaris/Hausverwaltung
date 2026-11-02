<?php

namespace App\Support\Rent;

use App\Models\Lease;
use App\Models\RentCharge;
use App\Models\RentPayment;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Soll-Stellung und Zuordnung der Zahlungen (mit Datenbank).
 * Das Soll entsteht jeweils am Monatsersten für den laufenden Monat (Miete im Voraus).
 * Ändert sich etwas am Vertrag oder an den Zahlungen, wird der Vertrag komplett neu berechnet.
 */
class RentLedger
{
    /**
     * Bringt Soll und Zuordnung eines Vertrags auf den neuesten Stand.
     *
     * @return array{created: int, changed: int, removed: int, credit: string}
     */
    public static function sync(Lease $lease, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');

        return DB::transaction(function () use ($lease, $today) {
            // Sperre auf den Vertrag, damit der tägliche Lauf und ein Benutzer nicht gleichzeitig rechnen
            $locked = Lease::query()->whereKey($lease->id)->lockForUpdate()->first();
            if ($locked === null) {
                return ['created' => 0, 'changed' => 0, 'removed' => 0, 'credit' => '0.00'];
            }

            $leaseData = [
                'start' => $locked->start_date->format('Y-m-d'),
                'end' => $locked->end_date?->format('Y-m-d'),
                'due_day' => (int) $locked->due_day,
                'term_days' => $locked->payment_term_days,
                'vat_rate' => (string) $locked->vat_rate,
            ];

            $rents = [];
            foreach ($locked->rents()->get() as $r) {
                $row = ['valid_from' => $r->valid_from->format('Y-m-d')];
                foreach (array_keys(Mieten::COMPONENTS) as $col) {
                    $row[$col] = (string) $r->{$col};
                }
                $rents[] = $row;
            }

            $wanted = [];
            foreach (RentMath::months($leaseData['start'], $leaseData['end'], $today->format('Y-m-d')) as $month) {
                $c = RentMath::charge($leaseData, $rents, $month);
                if ($c !== null) {
                    $wanted[$month] = $c;
                }
            }

            $existing = RentCharge::query()->where('lease_id', $locked->id)->where('kind', 'miete')->get()->keyBy(fn ($c) => $c->period->format('Y-m-d'));

            $created = 0;
            $changed = 0;
            $removed = 0;
            $fields = array_merge(['period_from', 'period_to', 'due_date', 'net_total', 'vat_rate', 'vat_amount', 'total'], array_keys(Mieten::COMPONENTS));

            foreach ($wanted as $month => $c) {
                $values = ['owner_id' => $locked->owner_id] + array_intersect_key($c, array_flip($fields));
                $row = $existing->get($month);
                if ($row === null) {
                    RentCharge::create(['lease_id' => $locked->id, 'kind' => 'miete', 'period' => $month, 'paid_amount' => 0] + $values);
                    $created++;

                    continue;
                }

                $differs = (int) $row->owner_id !== (int) $locked->owner_id;
                foreach ($fields as $f) {
                    $current = $row->{$f} instanceof \DateTimeInterface ? $row->{$f}->format('Y-m-d') : number_format((float) $row->{$f}, 2, '.', '');
                    $new = in_array($f, ['period_from', 'period_to', 'due_date'], true) ? $c[$f] : number_format((float) $c[$f], 2, '.', '');
                    if ($current !== $new) {
                        $differs = true;
                    }
                }
                if ($differs) {
                    $row->update($values);
                    $changed++;
                }
            }

            foreach ($existing as $month => $row) {
                if (! isset($wanted[$month])) {
                    $row->delete();
                    $removed++;
                }
            }

            $credit = self::allocate($locked);

            return ['created' => $created, 'changed' => $changed, 'removed' => $removed, 'credit' => $credit];
        });
    }

    /**
     * Posten, auf die eine Zahlung zuerst geht: Miete eines gewählten Monats, Nebenkosten-Nachzahlungen oder Mahngebühren/Zinsen.
     *
     * @return array<int>
     */
    public static function targets(RentPayment $p, \Illuminate\Support\Collection $charges): array
    {
        $kind = (string) ($p->income_kind ?? 'miete');
        $match = match (true) {
            $kind === 'miete' && $p->for_period !== null => fn ($c) => ($c->kind ?? 'miete') === 'miete' && $c->period?->format('Y-m') === $p->for_period->format('Y-m'),
            $kind === 'nk' => fn ($c) => $c->kind === 'nk',
            $kind === 'gebuehr' => fn ($c) => in_array($c->kind, ['mahngebuehr', 'zinsen'], true),
            default => null,
        };

        return $match === null ? [] : $charges->filter($match)->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /** Zahlungen den Soll-Posten neu zuordnen. Gibt das Guthaben zurück. Muss innerhalb einer Transaktion laufen. */
    public static function allocate(Lease $lease): string
    {
        $charges = RentCharge::query()->where('lease_id', $lease->id)->orderBy('due_date')->orderBy('period')->get();
        $payments = RentPayment::query()->where('lease_id', $lease->id)->orderBy('paid_on')->orderBy('id')->get();

        $result = RentMath::allocate(
            $charges->map(fn ($c) => ['id' => (int) $c->id, 'total' => (string) $c->total])->all(),
            $payments->map(fn ($p) => ['id' => (int) $p->id, 'amount' => (string) $p->amount, 'target' => self::targets($p, $charges)])->all()
        );

        DB::table('rent_allocations')->whereIn('rent_charge_id', $charges->pluck('id'))->delete();
        DB::table('rent_allocations')->whereIn('rent_payment_id', RentPayment::withTrashed()->where('lease_id', $lease->id)->pluck('id'))->delete();

        $now = now();
        $rows = array_map(fn ($a) => [
            'rent_payment_id' => $a['payment'],
            'rent_charge_id' => $a['charge'],
            'amount' => $a['amount'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $result['allocations']);
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('rent_allocations')->insert($chunk);
        }

        foreach ($charges as $c) {
            $paid = $result['paid'][$c->id] ?? '0.00';
            if (number_format((float) $c->paid_amount, 2, '.', '') !== $paid) {
                DB::table('rent_charges')->where('id', $c->id)->update(['paid_amount' => $paid, 'updated_at' => $now]);
            }
        }
        foreach ($payments as $p) {
            $used = $result['used'][$p->id] ?? '0.00';
            if (number_format((float) $p->allocated_amount, 2, '.', '') !== $used) {
                DB::table('rent_payments')->where('id', $p->id)->update(['allocated_amount' => $used, 'updated_at' => $now]);
            }
        }

        return $result['credit'];
    }

    /**
     * Alle Verträge auf den neuesten Stand bringen (täglicher Lauf).
     *
     * @return array{leases: int, created: int, changed: int}
     */
    public static function syncAll(?DateTimeImmutable $today = null): array
    {
        $sum = ['leases' => 0, 'created' => 0, 'changed' => 0];
        foreach (Lease::query()->orderBy('id')->get() as $lease) {
            $r = self::sync($lease, $today);
            $sum['leases']++;
            $sum['created'] += $r['created'];
            $sum['changed'] += $r['changed'] + $r['removed'];
        }

        try {
            \Illuminate\Support\Facades\Cache::put('rent_sync_day', ($today ?? new DateTimeImmutable('today'))->format('Y-m-d'), 86400 * 2);
        } catch (\Throwable) {
        }

        return $sum;
    }

    /** Einmal am Tag alles neu rechnen, falls der tägliche Lauf (Cronjob) noch nicht gelaufen ist. */
    public static function syncAllIfStale(): void
    {
        $today = date('Y-m-d');
        try {
            if (\Illuminate\Support\Facades\Cache::get('rent_sync_day') === $today) {
                return;
            }
        } catch (\Throwable) {
            // Ohne Zwischenspeicher wird einfach jedes Mal gerechnet
        }

        self::syncAll();

        try {
            \Illuminate\Support\Facades\Cache::put('rent_sync_day', $today, 86400 * 2);
        } catch (\Throwable) {
        }
    }

    /**
     * Kontostand eines Vertrags: Soll gesamt, gezahlt gesamt, Rückstand (positiv) oder Guthaben (negativ),
     * davon überfällig (nur Posten mit Fälligkeit vor heute).
     *
     * @return array{charged: float, paid: float, balance: float, overdue: float}
     */
    public static function balance(Lease $lease, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $charged = (float) RentCharge::query()->where('lease_id', $lease->id)->sum('total');
        $paid = (float) RentPayment::query()->where('lease_id', $lease->id)->sum('amount');
        $overdue = (float) RentCharge::query()->where('lease_id', $lease->id)->where('due_date', '<', $today)
            ->selectRaw('coalesce(sum(total - paid_amount), 0) as o')->value('o');

        return [
            'charged' => round($charged, 2),
            'paid' => round($paid, 2),
            'balance' => round($charged - $paid, 2),
            'overdue' => round($overdue, 2),
        ];
    }

    /**
     * Konto in zeitlicher Reihenfolge: Soll-Posten und Zahlungen mit laufendem Saldo.
     *
     * @return array<int, array{date: string, kind: string, text: string, debit: ?float, credit: ?float, balance: float, model: mixed}>
     */
    public static function statement(Lease $lease): array
    {
        $lines = [];
        foreach (RentCharge::query()->where('lease_id', $lease->id)->get() as $c) {
            $isRent = ($c->kind ?? 'miete') === 'miete';
            $lines[] = ['date' => $isRent ? $c->period_from->format('Y-m-d') : $c->created_at->format('Y-m-d'), 'sort' => 0, 'kind' => 'soll', 'text' => ($isRent ? 'Miete ' : '').$c->periodLabel(), 'debit' => (float) $c->total, 'credit' => null, 'model' => $c];
        }
        foreach (RentPayment::query()->where('lease_id', $lease->id)->get() as $p) {
            $lines[] = ['date' => $p->paid_on->format('Y-m-d'), 'sort' => 1, 'kind' => 'zahlung', 'text' => in_array($p->source, ['nk', 'co2'], true) ? $p->methodLabel() : ((float) $p->amount < 0 ? 'Rückbuchung / Erstattung' : 'Zahlung').(($p->income_kind ?? 'miete') !== 'miete' || $p->for_period ? ' '.$p->incomeLabel() : '').' ('.$p->methodLabel().')', 'debit' => null, 'credit' => (float) $p->amount, 'model' => $p];
        }
        usort($lines, fn ($a, $b) => [$a['date'], $a['sort'], $a['model']->id] <=> [$b['date'], $b['sort'], $b['model']->id]);

        $balance = 0.0;
        foreach ($lines as &$l) {
            $balance += ($l['debit'] ?? 0) - ($l['credit'] ?? 0);
            $l['balance'] = round($balance, 2);
        }
        unset($l);

        return $lines;
    }
}
