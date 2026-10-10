<?php

namespace App\Support\Rent;

use App\Models\Lease;
use App\Models\LeaseRent;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\Unit;
use App\Models\User;
use Throwable;

/** Hinweise für die Übersicht: Rückstände, Leerstand, auslaufende Verträge, anstehende Mietänderungen. */
class RentDashboard
{
    public const DAYS_AHEAD = 60;

    /** @return array|null null, wenn die Tabellen fehlen (Paket noch nicht eingespielt) */
    public static function forUser(User $user, ?int $ownerId = null): ?array
    {
        try {
            RentLedger::syncAllIfStale();
        } catch (Throwable) {
            return null;
        }

        $today = date('Y-m-d');
        $ahead = date('Y-m-d', strtotime('+'.self::DAYS_AHEAD.' days'));
        $owner = fn ($q, string $col) => $ownerId ? $q->where($col, $ownerId) : $q;

        $overdue = $owner(RentCharge::visibleTo($user)->open()->where('due_date', '<', $today), 'rent_charges.owner_id');
        $overdueSum = (float) (clone $overdue)->selectRaw('coalesce(sum(total - paid_amount), 0) as s')->value('s');
        $overdueLeases = (int) (clone $overdue)->distinct()->count('lease_id');

        $leases = $owner(Lease::visibleTo($user), 'leases.owner_id');
        $active = (int) (clone $leases)->activeOn($today)->count();

        $busy = Lease::query()->activeOn($today)->pluck('unit_id')->all();
        $vacant = (int) $owner(Unit::visibleTo($user), 'p.owner_id')
            ->join('properties as p', 'p.id', '=', 'units.property_id')
            ->whereNull('p.deleted_at')
            ->whereNotIn('units.id', $busy)
            ->count();

        $ending = (clone $leases)->with(['unit.property', 'tenant'])
            ->whereBetween('leases.end_date', [$today, $ahead])->orderBy('end_date')->get();

        $changes = LeaseRent::query()
            ->whereIn('lease_id', (clone $leases)->select('leases.id'))
            ->where('reason', '!=', 'beginn')
            ->whereBetween('valid_from', [date('Y-m-d', strtotime('+1 day')), $ahead])
            ->with(['lease.unit', 'lease.tenant'])
            ->orderBy('valid_from')
            ->get();

        // Indexmieten, deren letzte Änderung der Kaltmiete mindestens ein Jahr her ist
        $indexDue = [];
        foreach ((clone $leases)->activeOn($today)->where('adjustment_type', 'index')->with(['rents', 'unit', 'tenant'])->get() as $l) {
            $last = $l->rents->filter(fn ($r) => in_array($r->reason, ['beginn', 'index', 'erhoehung', 'staffel', 'korrektur'], true))->last();
            if ($last && $last->valid_from->copy()->addYear()->format('Y-m-d') <= $ahead) {
                $indexDue[] = ['lease' => $l, 'since' => $last->valid_from];
            }
        }

        return [
            'overdue_sum' => round($overdueSum, 2),
            'overdue_leases' => $overdueLeases,
            'active' => $active,
            'vacant' => $vacant,
            'ending' => $ending,
            'changes' => $changes,
            'index_due' => $indexDue,
            'days' => self::DAYS_AHEAD,
        ];
    }

    /** Für die Kachel: Anzahl Verträge mit überfälliger Miete. */
    public static function overdueCount(User $user): int
    {
        try {
            return (int) RentCharge::visibleTo($user)->open()->where('due_date', '<', date('Y-m-d'))->distinct()->count('lease_id');
        } catch (Throwable) {
            return 0;
        }
    }
}
