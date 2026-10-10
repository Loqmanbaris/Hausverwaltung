<?php

namespace App\Support\Finance;

use App\Models\Lease;
use App\Models\Loan;
use App\Models\Property;
use App\Models\PropertyValue;
use App\Models\User;
use App\Support\Tax\AnlageV;
use Illuminate\Support\Collection;

/**
 * Kennzahlen je Objekt (Paket 23): Restschuld, Rate, Cashflow, Renditen, Beleihung.
 * Einnahmen und Ausgaben kommen aus derselben Rechnung wie die Anlage V (Zufluss/Abfluss im Jahr).
 */
class Kennzahlen
{
    /** @var array<string, array> */
    private static array $anlageV = [];

    private static function anlageV(int $ownerId, int $year): array
    {
        return self::$anlageV[$ownerId.'-'.$year] ??= AnlageV::build($ownerId, $year);
    }

    public static function forget(): void
    {
        self::$anlageV = [];
        LoanPlan::forget();
    }

    /** Jahresnettokaltmiete laut heute gültiger Verträge (Kaltmiete, Stellplatz, Garage, Möblierung). */
    public static function annualColdRent(Property $p, ?string $today = null): float
    {
        $today ??= date('Y-m-d');
        $sum = 0.0;
        $leases = Lease::query()->whereIn('unit_id', $p->units()->select('id'))->activeOn($today)->with('rents')->get();
        foreach ($leases as $l) {
            $r = $l->currentRent($today);
            if ($r) {
                $sum += (float) $r->rent_cold + (float) $r->parking + (float) $r->garage + (float) $r->furniture;
            }
        }

        return round($sum * 12, 2);
    }

    public static function property(Property $p, int $year, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $v = PropertyValue::for((int) $p->id);
        $loans = Loan::query()->where('property_id', $p->id)->with('events')->get();
        $active = $loans->where('status', 'aktiv');

        $debt = round($loans->sum(fn ($l) => LoanPlan::balanceOn($l, $today)), 2);
        $monthly = round($active->sum(fn ($l) => LoanPlan::monthly($l, $today)), 2);
        $principalTotal = round((float) $loans->sum('principal'), 2);
        $yearLoan = ['payment' => 0.0, 'interest' => 0.0, 'principal' => 0.0];
        foreach ($loans as $l) {
            foreach (LoanPlan::yearTotals($l, $year) as $k => $val) {
                $yearLoan[$k] += $val;
            }
        }
        $fixed = $active->filter(fn ($l) => $l->fixed_until !== null && $l->fixed_until->format('Y-m-d') >= $today)->sortBy(fn ($l) => $l->fixed_until->format('Y-m-d'))->first();

        $av = self::anlageV((int) $p->owner_id, $year)['properties'][$p->id] ?? null;
        $income = $av ? $av['income_total'] / 100 : 0.0;
        $costsNoInterest = $av ? ($av['cost_total'] - $av['costs']['zinsen']) / 100 : 0.0;
        $nonAllocatable = $av ? ($av['costs']['erhaltung'] + $av['costs']['verwaltung'] + $av['costs']['sonstige']) / 100 : 0.0;
        $cashflow = round($income - $costsNoInterest - $yearLoan['payment'], 2);

        $jnkm = self::annualColdRent($p, $today);
        $price = (float) ($v->purchase_price ?? 0);
        $invest = $price + (float) ($v->purchase_costs ?? 0);
        $equity = $v->equity !== null ? (float) $v->equity : ($invest > 0 ? max(0.0, $invest - $principalTotal) : null);
        $market = $v->market_value !== null ? (float) $v->market_value : null;

        $pct = fn (?float $a, ?float $b) => ($a !== null && $b !== null && $b > 0) ? round($a / $b * 100, 2) : null;

        return [
            'property' => $p,
            'values' => $v,
            'loans' => $loans,
            'debt' => $debt,
            'monthly_rate' => $monthly,
            'fixed_next' => $fixed,
            'year' => $year,
            'income' => round($income, 2),
            'costs' => round($costsNoInterest, 2),
            'loan_year' => array_map(fn ($x) => round($x, 2), $yearLoan),
            'cashflow' => $cashflow,
            'cashflow_month' => round($cashflow / 12, 2),
            'jnkm' => $jnkm,
            'gross_yield' => $pct($jnkm, $price),
            'net_yield' => $invest > 0 ? $pct($jnkm - $nonAllocatable, $invest) : null,
            'factor' => $jnkm > 0 && $price > 0 ? round($price / $jnkm, 1) : null,
            'equity' => $equity,
            'equity_yield' => $equity !== null && $equity > 0 ? $pct($cashflow + $yearLoan['principal'], $equity) : null,
            'market' => $market,
            'ltv' => $market ? $pct($debt, $market) : null,
            'net_worth' => $market !== null ? round($market - $debt, 2) : null,
        ];
    }

    /** Alle sichtbaren Objekte mit Darlehen oder Werten, plus Summen. */
    public static function overview(User $user, int $year, ?int $ownerId = null): array
    {
        $props = Property::visibleTo($user)->when($ownerId, fn ($q) => $q->where('properties.owner_id', $ownerId))->orderBy('name')->get();
        $rows = [];
        foreach ($props as $p) {
            $rows[] = self::property($p, $year);
        }
        $sum = fn (string $k) => round(array_sum(array_map(fn ($r) => (float) ($r[$k] ?? 0), $rows)), 2);
        $market = array_filter(array_map(fn ($r) => $r['market'], $rows), fn ($m) => $m !== null);

        $unassigned = Loan::visibleTo($user)->whereNull('property_id')->when($ownerId, fn ($q) => $q->where('loans.owner_id', $ownerId))->with('events')->get();
        $uDebt = round($unassigned->sum(fn ($l) => LoanPlan::balanceOn($l, date('Y-m-d'))), 2);

        return [
            'rows' => $rows,
            'unassigned' => $unassigned,
            'totals' => [
                'debt' => $sum('debt') + $uDebt,
                'monthly_rate' => $sum('monthly_rate') + round($unassigned->where('status', 'aktiv')->sum(fn ($l) => LoanPlan::monthly($l)), 2),
                'cashflow' => $sum('cashflow'),
                'jnkm' => $sum('jnkm'),
                'market' => $market ? round(array_sum($market), 2) : null,
                'net_worth' => $market ? round(array_sum($market) - ($sum('debt') + $uDebt), 2) : null,
            ],
        ];
    }

    /** Darlehen, deren Zinsbindung innerhalb von $months Monaten endet. */
    public static function fixedEnding(User $user, int $months = 12, ?int $ownerId = null, ?string $today = null): Collection
    {
        $today ??= date('Y-m-d');

        return Loan::visibleTo($user)->where('status', 'aktiv')->whereNotNull('fixed_until')
            ->when($ownerId, fn ($q) => $q->where('loans.owner_id', $ownerId))
            ->whereBetween('fixed_until', [$today, date('Y-m-d', strtotime($today.' +'.$months.' months'))])
            ->with(['property', 'events'])->orderBy('fixed_until')->get();
    }
}
