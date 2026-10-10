<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Lease;
use App\Models\RentCharge;
use App\Support\Money;
use App\Support\Rent\RentLedger;
use DateTimeImmutable;
use Illuminate\Http\Request;

/**
 * Mieteingangsprüfung: Soll und Ist je Monat, Zahlungen mit einem Klick erfassen, Offene-Posten-Liste.
 */
class RentIncomeController extends Controller
{
    use ScopesOwners;

    public function month(Request $request)
    {
        RentLedger::syncAllIfStale();

        $monthParam = (string) $request->query('monat', date('Y-m'));
        $month = DateTimeImmutable::createFromFormat('!Y-m', $monthParam) ?: new DateTimeImmutable('first day of this month');
        if ($month > new DateTimeImmutable('first day of this month')) {
            $month = new DateTimeImmutable('first day of this month');
        }
        $ownerId = (int) $request->query('eigentuemer');

        $charges = RentCharge::visibleTo($this->me())
            ->where('period', $month->format('Y-m-d'))
            ->where('rent_charges.kind', 'miete')
            ->when($ownerId > 0, fn ($q) => $q->where('rent_charges.owner_id', $ownerId))
            ->with(['lease.unit.property', 'lease.tenant', 'lease.owner'])
            ->get()
            ->sortBy(fn ($c) => [$c->lease?->owner?->name, $c->lease?->unit?->property?->name, $c->lease?->unit?->label])
            ->values();

        $totals = [
            'soll' => round((float) $charges->sum('total'), 2),
            'ist' => round((float) $charges->sum('paid_amount'), 2),
        ];
        $totals['offen'] = round($totals['soll'] - $totals['ist'], 2);

        return view('rent.month', [
            'charges' => $charges,
            'month' => $month,
            'prev' => $month->modify('-1 month')->format('Y-m'),
            'next' => $month < new DateTimeImmutable('first day of this month') ? $month->modify('+1 month')->format('Y-m') : null,
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'ownerId' => $ownerId,
            'totals' => $totals,
            'today' => date('Y-m-d'),
        ]);
    }

    /** Zahlung zu einem Soll-Posten aus der Monatsliste erfassen. */
    public function quickPay(Request $request)
    {
        $charge = RentCharge::query()->findOrFail((int) $request->input('charge_id'));
        $this->assertOwnerAccess((int) $charge->owner_id);
        $lease = Lease::query()->findOrFail($charge->lease_id);

        $payment = app(LeaseController::class)->createPayment($request, $lease);

        return redirect()
            ->to(route('rent.month', array_filter(['monat' => $charge->period->format('Y-m'), 'eigentuemer' => $request->input('eigentuemer')])).'#posten-'.$charge->id)
            ->with('status', 'Zahlung über '.Money::format($payment->amount).' für '.$lease->load(['unit.property', 'tenant'])->title().' wurde erfasst.');
    }

    public function open(Request $request)
    {
        RentLedger::syncAllIfStale();

        $ownerId = (int) $request->query('eigentuemer');
        $today = date('Y-m-d');

        $charges = RentCharge::visibleTo($this->me())
            ->open()
            ->where('due_date', '<', $today)
            ->when($ownerId > 0, fn ($q) => $q->where('rent_charges.owner_id', $ownerId))
            ->with(['lease.unit.property', 'lease.tenant', 'lease.owner'])
            ->orderBy('due_date')
            ->get();

        $groups = $charges->groupBy('lease_id')->map(function ($items) use ($today) {
            $first = $items->first();
            $oldest = $items->min(fn ($c) => $c->due_date->format('Y-m-d'));

            return [
                'lease' => $first->lease,
                'charges' => $items,
                'open' => round($items->sum(fn ($c) => $c->openAmount()), 2),
                'oldest' => $oldest,
                'days' => (int) (new DateTimeImmutable($oldest))->diff(new DateTimeImmutable($today))->days,
            ];
        })->sortByDesc('days')->values();

        $byOwner = $groups->groupBy(fn ($g) => $g['lease']->owner?->name ?? '–')->map(fn ($g) => round($g->sum('open'), 2))->sortKeys();

        return view('rent.open', [
            'groups' => $groups,
            'byOwner' => $byOwner,
            'total' => round($groups->sum('open'), 2),
            'owners' => $this->visibleOwners()->get(['owners.id', 'owners.name']),
            'ownerId' => $ownerId,
            'today' => $today,
        ]);
    }
}
