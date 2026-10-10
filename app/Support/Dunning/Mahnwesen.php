<?php

namespace App\Support\Dunning;

use App\Models\DunningNotice;
use App\Models\Lease;
use App\Models\RentCharge;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Rent\RentLedger;
use App\Support\Settings;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Mahnwesen (Paket 10): Vorschläge aus den offenen Posten, Mahnstufen, Mahngebühr ins Mieterkonto, Storno.
 *
 * Stufen: 1 Zahlungserinnerung, 2 Mahnung, 3 letzte Mahnung.
 * Eine neue Stufe wird erst vorgeschlagen, wenn die Zahlungsfrist der vorigen abgelaufen ist
 * und mindestens ein damals gemahnter Posten noch offen ist. Sonst beginnt es wieder bei Stufe 1.
 */
class Mahnwesen
{
    /** Einstellungen: Schlüssel => [Bezeichnung, Standardwert, Art] */
    public const SETTINGS = [
        'dunning_grace_days' => ['Erinnerung frühestens … Tage nach Fälligkeit', '7', 'days'],
        'dunning_pay_days' => ['Zahlungsfrist im Schreiben (Tage)', '10', 'days'],
        'dunning_min_amount' => ['Mindestbetrag für einen Vorschlag', '10,00', 'money'],
        'dunning_fee_1' => ['Gebühr Zahlungserinnerung', '0,00', 'money'],
        'dunning_fee_2' => ['Gebühr Mahnung', '0,00', 'money'],
        'dunning_fee_3' => ['Gebühr letzte Mahnung', '0,00', 'money'],
    ];

    public static function setting(string $key): string
    {
        return (string) Settings::get($key, self::SETTINGS[$key][1] ?? '');
    }

    public static function days(string $key): int
    {
        return max(0, min(90, (int) self::setting($key)));
    }

    /** Betrag einer Einstellung als Dezimalzahl mit Punkt. */
    public static function amount(string $key): string
    {
        return Money::parse(self::setting($key)) ?? '0.00';
    }

    public static function fee(int $level): string
    {
        return self::amount('dunning_fee_'.$level);
    }

    /**
     * Stand je Mietvertrag mit überfälligen Posten.
     *
     * @return Collection<int, array{lease: Lease, items: Collection, open: float, oldest: string, days: int, last: ?DunningNotice, state: string, level: int, hint: ?string}>
     *   state: vorschlag | frist (Frist der letzten Mahnung läuft) | stufe3 (letzte Mahnung abgelaufen) | wartet (noch in der Karenzzeit) | gering (unter Mindestbetrag)
     */
    public static function overview(User $user, ?int $ownerId = null, ?string $today = null): Collection
    {
        $today ??= date('Y-m-d');
        $grace = self::days('dunning_grace_days');
        $limit = (new DateTimeImmutable($today))->modify('-'.$grace.' days')->format('Y-m-d');
        $min = (float) self::amount('dunning_min_amount');

        $charges = RentCharge::visibleTo($user)->open()
            ->where('due_date', '<', $today)
            ->when($ownerId, fn ($q) => $q->where('rent_charges.owner_id', $ownerId))
            ->with(['lease.unit.property', 'lease.tenant', 'lease.owner'])
            ->orderBy('due_date')->orderBy('id')
            ->get();

        $leaseIds = $charges->pluck('lease_id')->unique()->values();
        $last = DunningNotice::query()->whereIn('lease_id', $leaseIds)->where('status', 'erstellt')
            ->orderByDesc('issued_on')->orderByDesc('id')->get()->unique('lease_id')->keyBy('lease_id');

        $plans = \Illuminate\Support\Facades\Schema::hasTable('installment_plans')
            ? \App\Models\InstallmentPlan::query()->whereIn('lease_id', $leaseIds)->where('status', 'aktiv')->with('installments', 'lease')->get()->keyBy('lease_id')
            : collect();

        return $charges->groupBy('lease_id')->map(function (Collection $items, $leaseId) use ($today, $limit, $min, $last, $plans) {
            $lease = $items->first()->lease;
            $open = round($items->sum(fn ($c) => $c->openAmount()), 2);
            $oldest = $items->min(fn ($c) => $c->due_date->format('Y-m-d'));
            $ripe = $items->filter(fn ($c) => ! in_array($c->kind, ['mahngebuehr', 'zinsen'], true) && $c->due_date->format('Y-m-d') <= $limit);
            $ripeOpen = round($ripe->sum(fn ($c) => $c->openAmount()), 2);

            $notice = $last->get($leaseId);
            $stillOpen = $notice !== null && collect($notice->items ?? [])->pluck('id')->intersect($items->pluck('id'))->isNotEmpty();
            $relevant = $stillOpen ? $notice : null;

            if ($relevant !== null) {
                if ($relevant->pay_by->format('Y-m-d') >= $today) {
                    $state = 'frist';
                    $level = $relevant->level;
                } elseif ($relevant->level >= 3) {
                    $state = 'stufe3';
                    $level = 3;
                } else {
                    $state = 'vorschlag';
                    $level = $relevant->level + 1;
                }
            } else {
                $level = 1;
                $state = $ripe->isEmpty() ? 'wartet' : ($ripeOpen < $min ? 'gering' : 'vorschlag');
            }

            // Ratenvereinbarung: solange sie eingehalten wird, nicht mahnen
            $plan = $plans->get($leaseId);
            $planState = null;
            $hint = self::terminationHint($lease, $items);
            if ($plan !== null) {
                $planState = \App\Support\Interest\Raten::state($plan, $today);
                if ($planState['ok']) {
                    $state = 'raten';
                } else {
                    $hint = trim('Ratenvereinbarung vom '.$plan->agreed_on->format('d.m.Y').' wird nicht eingehalten. '.($hint ?? ''));
                }
            }

            return [
                'lease' => $lease,
                'items' => $items,
                'open' => $open,
                'plan' => $plan,
                'oldest' => $oldest,
                'days' => (int) (new DateTimeImmutable($oldest))->diff(new DateTimeImmutable($today))->days,
                'last' => $notice,
                'state' => $state,
                'level' => $level,
                'hint' => $hint,
            ];
        })->sortByDesc('days')->values();
    }

    /**
     * Hinweis, wenn der Mietrückstand die Grenze für eine fristlose Kündigung erreicht (§ 543 Abs. 2 Nr. 3 BGB):
     * zwei aufeinanderfolgende Monate mit Rückstand von mehr als einer Monatsmiete, oder insgesamt zwei Monatsmieten.
     * Nur ein Hinweis, keine rechtliche Prüfung.
     */
    public static function terminationHint(Lease $lease, Collection $items): ?string
    {
        $rentItems = $items->filter(fn ($c) => ($c->kind ?? 'miete') === 'miete')->sortBy(fn ($c) => $c->period->format('Y-m-d'))->values();
        if ($rentItems->isEmpty()) {
            return null;
        }
        $monthly = (float) (RentCharge::query()->where('lease_id', $lease->id)->where('kind', 'miete')
            ->orderByDesc('period')->get()->first(fn ($c) => ! $c->isPartial())?->total ?? 0);
        if ($monthly <= 0) {
            return null;
        }
        $sum = round($rentItems->sum(fn ($c) => $c->openAmount()), 2);
        if ($sum >= 2 * $monthly - 0.004) {
            return 'Rückstand erreicht zwei Monatsmieten. Eine fristlose Kündigung kann möglich sein (bitte rechtlich prüfen lassen).';
        }
        for ($i = 1; $i < $rentItems->count(); $i++) {
            $a = $rentItems[$i - 1];
            $b = $rentItems[$i];
            $consecutive = $a->period->copy()->addMonthNoOverflow()->format('Y-m') === $b->period->format('Y-m');
            if ($consecutive && $a->openAmount() + $b->openAmount() > $monthly + 0.004) {
                return 'In zwei aufeinanderfolgenden Monaten ist mehr als eine Monatsmiete offen. Eine fristlose Kündigung kann möglich sein (bitte rechtlich prüfen lassen).';
            }
        }

        return null;
    }

    /**
     * Erstellt eine Zahlungserinnerung oder Mahnung. Die Gebühr (falls eingestellt) wird als offener Posten ins Mieterkonto gebucht.
     *
     * @throws RuntimeException
     */
    public static function create(Lease $lease, int $level, User $user, ?string $note = null, ?string $today = null): DunningNotice
    {
        $today ??= date('Y-m-d');
        if (! isset(DunningNotice::LEVELS[$level])) {
            throw new RuntimeException('Unbekannte Mahnstufe.');
        }
        $items = RentCharge::query()->where('lease_id', $lease->id)->open()->where('due_date', '<', $today)
            ->orderBy('due_date')->orderBy('id')->get();
        if ($items->isEmpty()) {
            throw new RuntimeException('Für '.$lease->title().' ist nichts überfällig.');
        }
        if (DunningNotice::query()->where('lease_id', $lease->id)->where('issued_on', $today)->where('status', 'erstellt')->exists()) {
            throw new RuntimeException('Für '.$lease->title().' wurde heute schon ein Schreiben erstellt.');
        }

        $fee = self::fee($level);
        $payBy = (new DateTimeImmutable($today))->modify('+'.self::days('dunning_pay_days').' days')->format('Y-m-d');
        $open = round($items->sum(fn ($c) => $c->openAmount()), 2);

        $notice = DB::transaction(function () use ($lease, $level, $user, $note, $today, $items, $fee, $payBy, $open) {
            $notice = DunningNotice::create([
                'lease_id' => $lease->id,
                'owner_id' => $lease->owner_id,
                'level' => $level,
                'issued_on' => $today,
                'pay_by' => $payBy,
                'open_amount' => number_format($open, 2, '.', ''),
                'fee' => $fee,
                'items' => $items->map(fn ($c) => [
                    'id' => (int) $c->id,
                    'label' => ($c->kind === 'miete' ? 'Miete ' : '').$c->periodLabel(),
                    'due' => $c->due_date->format('Y-m-d'),
                    'total' => number_format((float) $c->total, 2, '.', ''),
                    'open' => number_format($c->openAmount(), 2, '.', ''),
                ])->values()->all(),
                'note' => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 2000) : null,
                'status' => 'erstellt',
                'created_by' => $user->id,
            ]);

            if ((float) $fee > 0) {
                $charge = RentCharge::create([
                    'lease_id' => $lease->id,
                    'owner_id' => $lease->owner_id,
                    'kind' => 'mahngebuehr',
                    'label' => 'Mahngebühr ('.DunningNotice::LEVELS[$level].' vom '.date('d.m.Y', strtotime($today)).')',
                    'period' => $today,
                    'period_from' => $today,
                    'period_to' => $today,
                    'due_date' => $payBy,
                    'net_total' => $fee,
                    'vat_rate' => 0,
                    'vat_amount' => 0,
                    'total' => $fee,
                    'paid_amount' => 0,
                ]);
                $notice->update(['fee_charge_id' => $charge->id]);
            }

            Audit::log('dunning.created', $notice, DunningNotice::LEVELS[$level].' erstellt: '.$lease->title().', offen '.Money::format($open)
                .((float) $fee > 0 ? ', Gebühr '.Money::format($fee) : ''));

            return $notice;
        });

        if ($notice->fee_charge_id) {
            RentLedger::sync($lease);
        }

        return $notice;
    }

    /** Storniert ein Schreiben. Eine gebuchte Gebühr wird aus dem Mieterkonto entfernt. */
    public static function cancel(DunningNotice $notice): void
    {
        if ($notice->isCancelled()) {
            return;
        }
        $lease = $notice->lease;
        DB::transaction(function () use ($notice, $lease) {
            $chargeId = $notice->fee_charge_id;
            $notice->update(['status' => 'storniert', 'fee_charge_id' => null]);
            if ($chargeId) {
                DB::table('rent_allocations')->where('rent_charge_id', $chargeId)->delete();
                DB::table('rent_charges')->where('id', $chargeId)->where('kind', 'mahngebuehr')->delete();
            }
            Audit::log('dunning.cancelled', $notice, $notice->levelLabel().' vom '.$notice->issued_on->format('d.m.Y').' storniert'.($lease ? ': '.$lease->title() : ''));
        });
        if ($lease) {
            RentLedger::sync($lease);
        }
    }

    /** Vorheriges (nicht storniertes) Schreiben zum selben Vertrag, für den Bezug im Text. */
    public static function previous(DunningNotice $notice): ?DunningNotice
    {
        return DunningNotice::query()->where('lease_id', $notice->lease_id)->where('status', 'erstellt')
            ->where('id', '<', $notice->id)->where('level', '<', $notice->level)
            ->orderByDesc('id')->first();
    }

    /** Anzahl Verträge, für die ein Schreiben vorgeschlagen wird (Startseite). */
    public static function proposalCountFor(User $user): int
    {
        try {
            return self::overview($user)->where('state', 'vorschlag')->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
