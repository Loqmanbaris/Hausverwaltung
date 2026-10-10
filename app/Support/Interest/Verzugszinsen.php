<?php

namespace App\Support\Interest;

use App\Models\InterestBooking;
use App\Models\Lease;
use App\Models\RentCharge;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Rent\RentLedger;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Verzugszinsen nach § 288 BGB: Basiszinssatz + 5 Prozentpunkte (Verbraucher) bzw. + 9 Prozentpunkte (kein Verbraucher beteiligt).
 * Zinslauf ab dem Tag nach der Fälligkeit bis einschließlich zum Tag der Zahlung, taggenau auf 365 Tage.
 * Keine Zinsen auf Zinsen und Mahngebühren (§ 289 BGB).
 */
class Verzugszinsen
{
    public const MODES = [
        'verbraucher' => 'Verbraucher (Wohnraum): Basiszinssatz + 5 Prozentpunkte',
        'unternehmer' => 'Unternehmer (Gewerbe, Firma): Basiszinssatz + 9 Prozentpunkte',
    ];

    public const NO_INTEREST_KINDS = ['zinsen', 'mahngebuehr'];

    private static ?array $rates = null;

    public static function forgetRates(): void
    {
        self::$rates = null;
    }

    /** @return array<string, float> valid_from => Basiszinssatz */
    public static function baseRates(): array
    {
        return self::$rates ??= DB::table('base_interest_rates')->orderBy('valid_from')->pluck('rate', 'valid_from')->map(fn ($r) => (float) $r)->all();
    }

    public static function baseRateOn(string $date): ?float
    {
        $rate = null;
        foreach (self::baseRates() as $from => $r) {
            if ($from <= $date) {
                $rate = $r;
            }
        }

        return $rate;
    }

    /** Fehlt der Basiszinssatz für das laufende Halbjahr? */
    public static function missingCurrentRate(?string $today = null): bool
    {
        $today ??= date('Y-m-d');
        $half = substr($today, 0, 4).((int) substr($today, 5, 2) >= 7 ? '-07-01' : '-01-01');

        return ! array_key_exists($half, self::baseRates());
    }

    public static function modeFor(Lease $lease): string
    {
        $lease->loadMissing('tenant');

        return $lease->lease_type === 'gewerbe' || $lease->tenant?->type === 'firma' ? 'unternehmer' : 'verbraucher';
    }

    public static function surcharge(string $mode): float
    {
        return $mode === 'unternehmer' ? 9.0 : 5.0;
    }

    /** Beginn des nächsten Zinszeitraums: Tag bis zu dem zuletzt gebucht wurde (oder null). */
    public static function lastBookedUntil(Lease $lease): ?string
    {
        $d = InterestBooking::query()->where('lease_id', $lease->id)->where('status', 'gebucht')->max('period_until');

        return $d ? substr((string) $d, 0, 10) : null;
    }

    /**
     * Zinsen für Tage d mit $from < d <= $until.
     *
     * @return array{total: float, mode: string, lines: array<int, array>, missing_rate: bool}
     */
    public static function calculate(Lease $lease, ?string $from, string $until, ?string $mode = null): array
    {
        $mode ??= self::modeFor($lease);
        $add = self::surcharge($mode);
        $charges = RentCharge::query()->where('lease_id', $lease->id)->whereNotIn('kind', self::NO_INTEREST_KINDS)
            ->where('due_date', '<', $until)->orderBy('due_date')->get();
        $payments = DB::table('rent_allocations')->join('rent_payments', 'rent_payments.id', '=', 'rent_allocations.rent_payment_id')
            ->whereIn('rent_allocations.rent_charge_id', $charges->pluck('id'))
            ->get(['rent_allocations.rent_charge_id', 'rent_allocations.amount', 'rent_payments.paid_on', 'rent_payments.source'])
            ->groupBy('rent_charge_id');

        $lines = [];
        $total = 0.0;
        $missing = false;
        foreach ($charges as $c) {
            $start = (new DateTimeImmutable($c->due_date->format('Y-m-d')))->modify('+1 day');
            if ($from !== null && $start->format('Y-m-d') <= $from) {
                $start = (new DateTimeImmutable($from))->modify('+1 day');
            }
            $end = new DateTimeImmutable($until);
            if ($start > $end) {
                continue;
            }
            // Übernahme beim Import gilt als pünktlich gezahlt
            $pays = collect($payments->get($c->id, []))->map(fn ($p) => ['date' => $p->source === 'import' ? $c->due_date->format('Y-m-d') : substr((string) $p->paid_on, 0, 10), 'amount' => (float) $p->amount])->sortBy('date')->values();
            $cents = 0.0;
            $days = 0;
            $openDays = [];
            for ($d = $start; $d <= $end; $d = $d->modify('+1 day')) {
                $ds = $d->format('Y-m-d');
                // offen am Tag d: Soll minus Zahlungen vor diesem Tag (Zahltag zählt noch mit)
                $paid = $pays->filter(fn ($p) => $p['date'] < $ds)->sum('amount');
                $open = round((float) $c->total - $paid, 2);
                if ($open <= 0.004) {
                    if ($pays->filter(fn ($p) => $p['date'] >= $ds)->isEmpty()) {
                        break;
                    }

                    continue;
                }
                $base = self::baseRateOn($ds);
                if ($base === null) {
                    $missing = true;

                    continue;
                }
                $rate = $base + $add;
                $cents += $open * 100 * $rate / 100 / 365;
                $days++;
                $openDays[] = $open;
            }
            $amount = round($cents / 100, 2);
            if ($amount > 0) {
                $lines[] = [
                    'charge_id' => $c->id,
                    'label' => ($c->kind === 'miete' ? 'Miete ' : '').$c->periodLabel(),
                    'due' => $c->due_date->format('Y-m-d'),
                    'total' => round((float) $c->total, 2),
                    'open_now' => round((float) $c->total - (float) $c->paid_amount, 2),
                    'days' => $days,
                    'avg_open' => $days ? round(array_sum($openDays) / $days, 2) : 0,
                    'interest' => $amount,
                ];
                $total += $amount;
            }
        }

        return ['total' => round($total, 2), 'mode' => $mode, 'lines' => $lines, 'missing_rate' => $missing];
    }

    /** Zinsen als Forderung ins Mieterkonto buchen. @throws RuntimeException */
    public static function book(Lease $lease, string $until, string $mode, User $user): InterestBooking
    {
        $from = self::lastBookedUntil($lease);
        if ($from !== null && $until <= $from) {
            throw new RuntimeException('Bis zum '.date('d.m.Y', strtotime($from)).' sind schon Zinsen gebucht.');
        }
        if ($until > date('Y-m-d')) {
            throw new RuntimeException('Zinsen können nur bis heute gebucht werden.');
        }
        $calc = self::calculate($lease, $from, $until, $mode);
        if ($calc['total'] < 0.01) {
            throw new RuntimeException('Für diesen Zeitraum fallen keine Zinsen an.');
        }
        $periodFrom = $from ?? (collect($calc['lines'])->min('due') ?? $until);
        $label = 'Verzugszinsen '.date('d.m.Y', strtotime($periodFrom.' +1 day')).' bis '.date('d.m.Y', strtotime($until));

        $b = DB::transaction(function () use ($lease, $until, $mode, $user, $calc, $periodFrom, $label) {
            if (RentCharge::query()->where('lease_id', $lease->id)->where('kind', 'zinsen')->where('period', $until)->exists()) {
                throw new RuntimeException('Für diesen Tag wurden schon Zinsen gebucht.');
            }
            $charge = RentCharge::create([
                'lease_id' => $lease->id, 'owner_id' => $lease->owner_id, 'kind' => 'zinsen', 'label' => $label,
                'period' => $until, 'period_from' => $periodFrom, 'period_to' => $until,
                'due_date' => date('Y-m-d', strtotime($until.' +14 days')),
                'net_total' => $calc['total'], 'vat_rate' => 0, 'vat_amount' => 0, 'total' => $calc['total'], 'paid_amount' => 0,
            ]);
            $b = InterestBooking::create([
                'lease_id' => $lease->id, 'owner_id' => $lease->owner_id, 'period_from' => $periodFrom, 'period_until' => $until,
                'mode' => $mode, 'amount' => $calc['total'], 'details' => $calc['lines'], 'status' => 'gebucht',
                'rent_charge_id' => $charge->id, 'created_by' => $user->id,
            ]);
            Audit::log('interest.booked', $b, $label.' gebucht: '.Money::format($calc['total']).' – '.$lease->title());

            return $b;
        });
        RentLedger::sync($lease);

        return $b;
    }

    public static function cancel(InterestBooking $b): void
    {
        if ($b->status !== 'gebucht') {
            return;
        }
        $latest = InterestBooking::query()->where('lease_id', $b->lease_id)->where('status', 'gebucht')->max('period_until');
        if (substr((string) $latest, 0, 10) !== $b->period_until->format('Y-m-d')) {
            throw new RuntimeException('Bitte zuerst die neueren Zinsbuchungen stornieren.');
        }
        DB::transaction(function () use ($b) {
            $id = $b->rent_charge_id;
            $b->update(['status' => 'storniert', 'rent_charge_id' => null]);
            if ($id) {
                DB::table('rent_allocations')->where('rent_charge_id', $id)->delete();
                DB::table('rent_charges')->where('id', $id)->where('kind', 'zinsen')->delete();
            }
            Audit::log('interest.cancelled', $b, 'Zinsbuchung storniert: '.Money::format($b->amount));
        });
        if ($b->lease) {
            RentLedger::sync($b->lease);
        }
    }
}
