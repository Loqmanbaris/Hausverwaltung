<?php

namespace App\Support\Report;

use App\Models\Lease;
use App\Models\Loan;
use App\Models\NkSettlement;
use App\Models\Owner;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\RentIncrease;
use App\Models\RentPayment;
use App\Models\Unit;
use App\Models\WorkOrder;
use App\Support\Finance\Kennzahlen;
use App\Support\Finance\LoanPlan;
use App\Support\Tax\AnlageV;
use Illuminate\Support\Facades\Schema;

/**
 * Jahresbericht je Eigentümer (Paket 28): Ergebnis, Vermietung, Mieteingang, Nebenkosten, Darlehen, Instandhaltung.
 * Beträge in Euro (float), Anlage-V-Werte werden aus Cent umgerechnet.
 */
class Jahresbericht
{
    public static function stichtag(int $year, ?string $today = null): string
    {
        $today ??= date('Y-m-d');

        return min($year.'-12-31', $today);
    }

    /** @return array<string, mixed> */
    public static function build(Owner $owner, int $year, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $stichtag = self::stichtag($year, $today);
        $ownerId = (int) $owner->id;
        $av = AnlageV::build($ownerId, $year);
        $properties = Property::query()->where('owner_id', $ownerId)->orderBy('name')->get();

        // Ergebnis je Objekt
        $rows = [];
        foreach ($av['properties'] as $pid => $p) {
            $rows[] = ['name' => $p['property']->name, 'income' => $p['income_total'] / 100, 'costs' => $p['cost_total'] / 100, 'interest' => $p['costs']['zinsen'] / 100, 'afa' => $p['costs']['afa'] / 100, 'result' => $p['result'] / 100];
        }
        if (($av['unassigned']['cost_total'] ?? 0) > 0) {
            $u = $av['unassigned'];
            $rows[] = ['name' => 'Ohne Objekt', 'income' => 0.0, 'costs' => $u['cost_total'] / 100, 'interest' => ($u['costs']['zinsen'] ?? 0) / 100, 'afa' => 0.0, 'result' => -$u['cost_total'] / 100];
        }
        $summary = [
            'income' => $av['totals']['income'] / 100,
            'costs' => $av['totals']['costs'] / 100,
            'result' => $av['totals']['result'] / 100,
            'interest' => array_sum(array_map(fn ($r) => $r['interest'], $rows)),
            'afa' => array_sum(array_map(fn ($r) => $r['afa'], $rows)),
        ];

        // Vermietung zum Stichtag
        $letting = [];
        $tot = ['units' => 0, 'rented' => 0, 'area' => 0.0, 'area_rented' => 0.0, 'cold' => 0.0];
        foreach ($properties as $p) {
            $units = Unit::query()->where('property_id', $p->id)->orderBy('label')->get();
            if ($units->isEmpty()) {
                continue;
            }
            $leased = Lease::query()->whereIn('unit_id', $units->pluck('id'))->activeOn($stichtag)->pluck('unit_id')->unique()->all();
            $vacant = $units->filter(fn ($u) => ! in_array($u->id, $leased, true));
            $area = (float) $units->sum('area_sqm');
            $areaVacant = (float) $vacant->sum('area_sqm');
            $cold = Kennzahlen::annualColdRent($p, $stichtag);
            $letting[] = ['name' => $p->name, 'units' => $units->count(), 'rented' => count($leased), 'vacant' => $vacant->pluck('label')->all(), 'area' => $area, 'area_rented' => $area - $areaVacant, 'cold' => $cold];
            $tot['units'] += $units->count();
            $tot['rented'] += count($leased);
            $tot['area'] += $area;
            $tot['area_rented'] += $area - $areaVacant;
            $tot['cold'] += $cold;
        }

        // Mieteingang
        $from = $year.'-01-01';
        $to = $year.'-12-31';
        $soll = (float) RentCharge::query()->where('owner_id', $ownerId)->where('kind', 'miete')->whereBetween('period', [$from, $to])->sum('total');
        $received = (float) RentPayment::query()->where('owner_id', $ownerId)->whereBetween('paid_on', [$from, $to])->whereNotIn('source', ['import', 'nk', 'co2'])->sum('amount');
        $openRows = RentCharge::query()->where('owner_id', $ownerId)->where('due_date', '<=', $stichtag)->whereColumn('paid_amount', '<', 'total')
            ->with('lease.unit', 'lease.tenant')->get();
        $openByLease = [];
        foreach ($openRows as $c) {
            $key = $c->lease_id;
            $openByLease[$key] ??= ['label' => $c->lease?->title() ?? 'Vertrag', 'amount' => 0.0];
            $openByLease[$key]['amount'] += $c->openAmount();
        }
        $openByLease = array_values(array_filter($openByLease, fn ($r) => $r['amount'] > 0.004));
        usort($openByLease, fn ($a, $b) => $b['amount'] <=> $a['amount']);
        $rent = ['soll' => round($soll, 2), 'received' => round($received, 2), 'open' => round(array_sum(array_column($openByLease, 'amount')), 2), 'open_rows' => array_slice($openByLease, 0, 10), 'open_count' => count($openByLease)];

        // Nebenkostenabrechnungen
        $nk = NkSettlement::query()->where('owner_id', $ownerId)->whereBetween('period_to', [$from, $to])->with('property')->orderBy('period_to')->get()
            ->map(fn ($s) => ['property' => $s->property?->name, 'from' => $s->period_from, 'to' => $s->period_to, 'status' => $s->status === 'abgeschlossen' ? 'abgeschlossen' : 'Entwurf'])->all();

        // Darlehen
        $loans = [];
        foreach (Loan::query()->where('owner_id', $ownerId)->with('events')->orderBy('name')->get() as $l) {
            $t = LoanPlan::yearTotals($l, $year);
            $loans[] = ['name' => $l->name, 'lender' => $l->lender, 'balance' => LoanPlan::balanceOn($l, $stichtag), 'payment' => $t['payment'], 'interest' => $t['interest'], 'principal' => $t['principal'], 'fixed_until' => $l->fixed_until, 'status' => $l->status];
        }

        // Instandhaltung
        $orders = ['created' => 0, 'done' => 0, 'open' => 0];
        if (Schema::hasTable('work_orders')) {
            $base = WorkOrder::query()->where('owner_id', $ownerId);
            $orders['created'] = (clone $base)->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59'])->count();
            $orders['done'] = (clone $base)->whereIn('status', ['erledigt', 'abgerechnet'])->whereBetween('updated_at', [$from.' 00:00:00', $to.' 23:59:59'])->count();
            $orders['open'] = (clone $base)->whereNotIn('status', ['erledigt', 'abgerechnet', 'storniert'])->count();
        }

        // Mieterhöhungen
        $increases = [];
        if (Schema::hasTable('rent_increases')) {
            $increases = RentIncrease::query()->where('owner_id', $ownerId)->where('status', 'wirksam')->whereBetween('effective_from', [$from, $to])->with('lease.unit', 'lease.tenant')->orderBy('effective_from')->get()
                ->map(fn ($i) => ['label' => $i->lease?->title(), 'kind' => $i->kindLabel(), 'from' => $i->effective_from, 'old' => (float) $i->old_cold, 'new' => (float) $i->new_cold])->all();
        }

        return [
            'owner' => $owner,
            'year' => $year,
            'stichtag' => $stichtag,
            'is_final' => $stichtag === $year.'-12-31',
            'summary' => $summary,
            'rows' => $rows,
            'letting' => $letting,
            'letting_total' => $tot,
            'rent' => $rent,
            'nk' => $nk,
            'loans' => $loans,
            'orders' => $orders,
            'increases' => $increases,
        ];
    }

    public static function pdfName(Owner $owner, int $year): string
    {
        return \App\Support\Mail\Outbox::fileName('Jahresbericht', (string) $year, $owner->name);
    }
}
