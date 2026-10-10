<?php

namespace App\Support\Dashboard;

use App\Models\HandoverProtocol;
use App\Models\InstallmentPlan;
use App\Models\Inquiry;
use App\Models\Lease;
use App\Models\Listing;
use App\Models\NkSettlement;
use App\Models\Property;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Unit;
use App\Models\UnitBooking;
use App\Models\User;
use App\Support\Bank\BankService;
use App\Support\Bank\ExpenseMatcher;
use App\Support\Dunning\Mahnwesen;
use App\Support\Interest\Raten;
use App\Support\Interest\Verzugszinsen;
use App\Support\Receipts\RecurringOverview;
use App\Support\Rent\RentDashboard;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Zahlen für die Übersicht (Paket 18): Geld im Monat, Aufgaben, Leerstand und Verträge,
 * Termine und Fristen, Mieteingang der letzten 12 Monate.
 * Jeder Block ist für sich abgesichert: Fehlt eine Tabelle, bleibt nur dieser Block leer.
 */
class Cockpit
{
    public const DAYS_AHEAD = 60;

    /** @param array<int,string>|null $only nur diese Bereiche berechnen (null = alle) */
    public static function build(User $user, ?int $ownerId = null, ?string $today = null, ?array $only = null): array
    {
        $today ??= date('Y-m-d');
        $need = fn (string $k) => $only === null || in_array($k, $only, true);
        $rd = ($need('money') || $need('letting') || $need('dates')) ? RentDashboard::forUser($user, $ownerId) : null;

        return [
            'today' => new DateTimeImmutable($today),
            'money' => $need('money') ? self::safe(fn () => self::money($user, $ownerId, $today, $rd), null) : null,
            'todo' => $need('todo') ? self::safe(fn () => self::todo($user, $ownerId, $today), []) : [],
            'letting' => $need('letting') ? self::safe(fn () => self::letting($user, $ownerId, $today, $rd), null) : null,
            'dates' => $need('dates') ? self::safe(fn () => self::dates($user, $ownerId, $today, $rd), []) : [],
            'chart' => $need('chart') ? self::safe(fn () => self::chart($user, $ownerId, $today), []) : [],
            'expenses' => $need('expenses') ? self::safe(fn () => self::expenses($user, $ownerId, $today), null) : null,
            'finance' => $need('finance') ? self::safe(fn () => self::finance($user, $ownerId, $today), null) : null,
            'technik' => $need('technik') ? self::safe(fn () => self::technik($user, $ownerId, $today), null) : null,
            'days' => self::DAYS_AHEAD,
        ];
    }

    /** Schäden und Wartung (Paket 24). */
    private static function technik(User $user, ?int $ownerId, string $today): array
    {
        $orders = \App\Models\WorkOrder::visibleTo($user)->open()->when($ownerId, fn ($q) => $q->where('work_orders.owner_id', $ownerId))
            ->with(['property', 'unit', 'vendor'])->orderByRaw("case priority when 'notfall' then 0 when 'dringend' then 1 else 2 end")->orderBy('id')->limit(6)->get();
        $openCount = \App\Models\WorkOrder::visibleTo($user)->open()->when($ownerId, fn ($q) => $q->where('work_orders.owner_id', $ownerId))->count();
        $due = \App\Support\Technik\Wartung::due($user, $ownerId, $today, 30);

        return ['orders' => $orders, 'open' => $openCount, 'due' => $due->take(5), 'due_count' => $due->count()];
    }

    /** Finanzierung (Paket 23): Restschuld, Raten, nächste Zinsbindung. */
    private static function finance(User $user, ?int $ownerId, string $today): ?array
    {
        $loans = \App\Models\Loan::visibleTo($user)->where('status', 'aktiv')->when($ownerId, fn ($q) => $q->where('loans.owner_id', $ownerId))->with(['events', 'property'])->get();
        if ($loans->isEmpty()) {
            return ['count' => 0];
        }
        $debt = $loans->sum(fn ($l) => \App\Support\Finance\LoanPlan::balanceOn($l, $today));
        $principal = (float) $loans->sum('principal');
        $year = \App\Support\Finance\LoanPlan::class;
        $y = (int) substr($today, 0, 4);
        $interest = $loans->sum(fn ($l) => $year::yearTotals($l, $y)['interest']);
        $repay = $loans->sum(fn ($l) => $year::yearTotals($l, $y)['principal']);
        $fixed = $loans->filter(fn ($l) => $l->fixed_until && $l->fixed_until->format('Y-m-d') >= $today)->sortBy(fn ($l) => $l->fixed_until->format('Y-m-d'))->first();

        return [
            'count' => $loans->count(),
            'debt' => round($debt, 2),
            'principal' => round($principal, 2),
            'paid_pct' => $principal > 0 ? (int) round(100 - $debt / $principal * 100) : 0,
            'monthly' => round($loans->sum(fn ($l) => $year::monthly($l, $today)), 2),
            'interest_year' => round($interest, 2),
            'repay_year' => round($repay, 2),
            'fixed' => $fixed,
            'fixed_balance' => $fixed ? $year::balanceOn($fixed, $fixed->fixed_until->format('Y-m-d')) : null,
            'year' => $y,
        ];
    }

    /** Ausgaben (Belege) im laufenden Jahr: Summe, je Monat, je Kostenart. */
    private static function expenses(User $user, ?int $ownerId, string $today): array
    {
        $year = substr($today, 0, 4);
        $base = fn () => \App\Models\Receipt::visibleTo($user)
            ->whereBetween('receipt_date', [$year.'-01-01', $year.'-12-31'])
            ->when($ownerId, fn ($q) => $q->where('receipts.owner_id', $ownerId));
        $byMonth = $base()->selectRaw('month(receipt_date) as m, sum(amount_gross) as s')->groupBy(DB::raw('month(receipt_date)'))->pluck('s', 'm');
        $byCat = $base()->selectRaw('cost_category as c, sum(amount_gross) as s, count(*) as n')->groupBy('cost_category')->orderByDesc('s')->get();
        $months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $cats = [];
        foreach ($byCat->take(5) as $r) {
            $cats[] = ['label' => \App\Support\Kostenarten::label($r->c), 'sum' => round((float) $r->s, 2), 'count' => (int) $r->n];
        }
        $rest = $byCat->slice(5);
        if ($rest->isNotEmpty()) {
            $cats[] = ['label' => 'Übrige Kostenarten', 'sum' => round((float) $rest->sum('s'), 2), 'count' => (int) $rest->sum('n')];
        }

        return [
            'year' => (int) $year,
            'total' => round((float) $byCat->sum('s'), 2),
            'count' => (int) $byCat->sum('n'),
            'months' => array_map(fn ($i) => ['label' => $months[$i - 1], 'sum' => round((float) ($byMonth[$i] ?? 0), 2), 'current' => $i === (int) substr($today, 5, 2), 'future' => $i > (int) substr($today, 5, 2)], range(1, 12)),
            'categories' => $cats,
        ];
    }

    private static function safe(callable $f, mixed $fallback): mixed
    {
        try {
            return $f();
        } catch (Throwable $e) {
            report($e);

            return $fallback;
        }
    }

    /** Monatsmieten (Art „miete“) eines Monats: Soll und davon bezahlt. */
    private static function monthSums(User $user, ?int $ownerId, string $from, string $to): array
    {
        $row = RentCharge::visibleTo($user)
            ->where('kind', 'miete')
            ->whereBetween('due_date', [$from, $to])
            ->when($ownerId, fn ($q) => $q->where('rent_charges.owner_id', $ownerId))
            ->selectRaw('coalesce(sum(total),0) as soll, coalesce(sum(least(paid_amount,total)),0) as ist')
            ->first();

        return [round((float) $row->soll, 2), round((float) $row->ist, 2)];
    }

    private static function money(User $user, ?int $ownerId, string $today, ?array $rd): ?array
    {
        if ($rd === null) {
            return null;
        }
        $d = new DateTimeImmutable($today);
        $from = $d->modify('first day of this month')->format('Y-m-d');
        $to = $d->modify('last day of this month')->format('Y-m-d');
        [$soll, $ist] = self::monthSums($user, $ownerId, $from, $to);

        $received = (float) RentPayment::query()
            ->when(! $user->isAdmin(), fn ($q) => $q->whereIn('rent_payments.owner_id', \App\Models\Owner::visibleTo($user)->select('owners.id')))
            ->when($ownerId, fn ($q) => $q->where('rent_payments.owner_id', $ownerId))
            ->whereBetween('paid_on', [$from, $to])
            ->whereNotIn('source', ['import', 'nk', 'co2'])
            ->sum('amount');

        return [
            'month' => $d,
            'soll' => $soll,
            'ist' => $ist,
            'open' => round(max(0, $soll - $ist), 2),
            'quote' => $soll > 0 ? (int) floor($ist / $soll * 100) : null,
            'received' => round($received, 2),
            'overdue' => $rd['overdue_sum'],
            'overdue_leases' => $rd['overdue_leases'],
        ];
    }

    /** Liste der Aufgaben: [Text, Anzahl, Link, Ton (warn|info)]. Nur Einträge mit Anzahl > 0. */
    private static function todo(User $user, ?int $ownerId, string $today): array
    {
        $items = [];
        $add = function (string $label, int $count, string $url, string $tone = 'info') use (&$items) {
            if ($count > 0) {
                $items[] = ['label' => $label, 'count' => $count, 'url' => $url, 'tone' => $tone];
            }
        };

        $dunning = self::safe(fn () => Mahnwesen::overview($user, $ownerId, $today), collect());
        $add('Mahnungen zu schreiben', $dunning->where('state', 'vorschlag')->count(), route('dunning.index'), 'warn');
        $add('Kündigungsgrund prüfen (Rückstand zwei Monatsmieten)', $dunning->filter(fn ($r) => $r['hint'] && str_contains($r['hint'], 'zwei Monatsmieten'))->count(), route('dunning.index'), 'warn');

        $plans = self::safe(fn () => InstallmentPlan::visibleTo($user)->where('status', 'aktiv')
            ->when($ownerId, fn ($q) => $q->where('installment_plans.owner_id', $ownerId))
            ->with('installments', 'lease')->get(), collect());
        $broken = $plans->filter(fn ($p) => $p->lease && ! Raten::state($p, $today)['ok']);
        $add('Ratenvereinbarungen nicht eingehalten', $broken->count(), $broken->count() === 1 ? route('installments.show', $broken->first()) : route('dunning.index'), 'warn');

        $add('Kontoumsätze zuordnen', self::safe(fn () => BankService::openCountFor($user), 0), route('bank.transactions'));
        $add('Ausgaben mit Belegen abgleichen', self::safe(fn () => ExpenseMatcher::openCountFor($user), 0), route('bank.expenses'));
        $add('Wiederkehrende Ausgaben fällig', self::safe(fn () => RecurringOverview::openCountFor($user), 0), route('recurring.index'));
        $add('Neue Anfragen von der Webseite', Inquiry::newCountFor($user), route('inquiries.index'));

        $drafts = self::safe(fn () => HandoverProtocol::visibleTo($user)->where('status', 'entwurf')
            ->when($ownerId, fn ($q) => $q->where('handover_protocols.owner_id', $ownerId))
            ->where('handover_on', '<=', $today)->orderBy('handover_on')->get(), collect());
        $add('Übergabeprotokolle nicht abgeschlossen', $drafts->count(), $drafts->count() === 1 ? route('handover.show', $drafts->first()) : route('leases.index'));

        $nk = self::nkMissing($user, $ownerId, $today);
        $add('Nebenkostenabrechnung '.((int) substr($today, 0, 4) - 1).' fehlt', count($nk), route('nk.index'), substr($today, 5, 2) >= '10' ? 'warn' : 'info');

        if (self::safe(fn () => Verzugszinsen::missingCurrentRate($today), false)) {
            $add('Aktuellen Basiszinssatz eintragen', 1, route('dunning.index').'#basiszins', 'warn');
        }

        // Schäden und Wartung (Paket 24)
        $newOrders = self::safe(fn () => \App\Models\WorkOrder::visibleTo($user)->where('status', 'gemeldet')->when($ownerId, fn ($q) => $q->where('work_orders.owner_id', $ownerId))->get(['id', 'priority']), collect());
        $add('Schäden gemeldet, noch kein Handwerker', $newOrders->count(), route('workorders.index', ['stand' => 'gemeldet']), $newOrders->whereIn('priority', ['dringend', 'notfall'])->isNotEmpty() ? 'warn' : 'info');
        $add('Aufträge erledigt, Rechnung fehlt', self::safe(fn () => \App\Models\WorkOrder::visibleTo($user)->where('status', 'erledigt')->when($ownerId, fn ($q) => $q->where('work_orders.owner_id', $ownerId))->count(), 0), route('workorders.index', ['stand' => 'erledigt']));
        $add('Wartungen überfällig', self::safe(fn () => \App\Models\MaintenanceTask::visibleTo($user)->where('active', true)->where('next_due', '<', $today)->when($ownerId, fn ($q) => $q->where('maintenance_tasks.owner_id', $ownerId))->count(), 0), route('maintenance.index'), 'warn');

        // Zinsbindung endet in den nächsten 12 Monaten (Paket 23)
        $ending = self::safe(fn () => \App\Support\Finance\Kennzahlen::fixedEnding($user, 12, $ownerId, $today), collect());
        $add('Anschlussfinanzierung planen (Zinsbindung endet)', $ending->count(), $ending->count() === 1 ? route('loans.show', $ending->first()) : route('finance.index'), $ending->contains(fn ($l) => $l->fixed_until->format('Y-m-d') <= date('Y-m-d', strtotime($today.' +6 months'))) ? 'warn' : 'info');

        // Zähler, CO2 und Verbrauchsinformation (Paket 26)
        $meters = self::safe(fn () => self::meters($user, $ownerId), collect());
        $notRemote = $meters->filter(fn ($m) => $m->isHeat() && ! $m->remote);
        $add('Zähler Heizung/Warmwasser nicht fernablesbar (Frist 31.12.2026)', $notRemote->count(), route('consumption.index'), $today >= '2026-07-01' ? 'warn' : 'info');
        $add('Zähler mit abgelaufener Eichfrist', $meters->filter(fn ($m) => $m->calibrationExpired($today))->count(), route('consumption.index'), 'warn');
        $add('Monatliche Verbrauchsinformation senden', self::safe(fn () => \App\Support\Nk\Verbrauchsinfo::open($user, $ownerId, $today)->count(), 0), route('consumption.index'));
        $add('CO2-Erstattung an Mieter offen', self::safe(fn () => \App\Models\Co2Claim::visibleTo($user)->where('status', 'offen')->when($ownerId, fn ($q) => $q->where('co2_claims.owner_id', $ownerId))->count(), 0), route('co2.index'));

        // Dokumente mit abgelaufener Frist (Paket 22)
        $expired = self::safe(fn () => \App\Models\Document::visibleTo($user)->where('archived', false)->whereNotNull('expires_on')
            ->where('expires_on', '<', $today)->when($ownerId, fn ($q) => $q->where('documents.owner_id', $ownerId))->count(), 0);
        $add('Dokumente abgelaufen (z. B. Versicherung, Wartung)', $expired, route('documents.index', ['frist' => 'abgelaufen']), 'warn');

        return $items;
    }

    /** Aktive Zähler der sichtbaren Objekte. */
    private static function meters(User $user, ?int $ownerId): \Illuminate\Support\Collection
    {
        return \App\Models\Meter::query()->where('active', true)
            ->whereIn('property_id', Property::visibleTo($user)->when($ownerId, fn ($q) => $q->where('properties.owner_id', $ownerId))->select('properties.id'))
            ->get();
    }

    /**
     * Objekte, für die im Vorjahr Vorauszahlungen gezahlt wurden, aber noch keine abgeschlossene
     * Abrechnung für dieses Jahr vorliegt. Frist: 12 Monate nach Ende des Abrechnungszeitraums.
     *
     * @return array<int, array{property: Property, draft: ?NkSettlement}>
     */
    private static function nkMissing(User $user, ?int $ownerId, string $today): array
    {
        return self::safe(function () use ($user, $ownerId, $today) {
            $year = (int) substr($today, 0, 4) - 1;
            $propertyIds = RentCharge::visibleTo($user)
                ->join('leases as l', 'l.id', '=', 'rent_charges.lease_id')
                ->join('units as u', 'u.id', '=', 'l.unit_id')
                ->where('rent_charges.kind', 'miete')
                ->whereBetween('rent_charges.due_date', [$year.'-01-01', $year.'-12-31'])
                ->whereRaw('(rent_charges.prepay_operating + rent_charges.prepay_heating) > 0')
                ->when($ownerId, fn ($q) => $q->where('rent_charges.owner_id', $ownerId))
                ->distinct()->pluck('u.property_id')->all();
            if ($propertyIds === []) {
                return [];
            }
            $settlements = NkSettlement::visibleTo($user)->whereIn('property_id', $propertyIds)
                ->whereYear('period_to', $year)->get()->groupBy('property_id');
            $out = [];
            foreach (Property::query()->whereIn('id', $propertyIds)->orderBy('name')->get() as $p) {
                $list = $settlements->get($p->id, collect());
                if ($list->contains(fn ($s) => $s->isFinal())) {
                    continue;
                }
                $out[] = ['property' => $p, 'draft' => $list->first()];
            }

            return $out;
        }, []);
    }

    private static function letting(User $user, ?int $ownerId, string $today, ?array $rd): ?array
    {
        $units = Unit::visibleTo($user)
            ->join('properties as p', 'p.id', '=', 'units.property_id')
            ->whereNull('p.deleted_at')
            ->when($ownerId, fn ($q) => $q->where('p.owner_id', $ownerId))
            ->select('units.*')->with('property')->get();
        if ($units->isEmpty()) {
            return null;
        }
        $busy = Lease::query()->activeOn($today)->whereIn('unit_id', $units->pluck('id'))->pluck('unit_id')->flip();
        $vacant = $units->reject(fn ($u) => isset($busy[$u->id]));

        // Montage-Einheiten: belegt, wenn heute eine Buchung läuft
        $booked = self::safe(fn () => UnitBooking::query()->whereIn('unit_id', $vacant->pluck('id'))
            ->whereIn('status', ['reserviert', 'belegt'])
            ->where('date_from', '<=', $today)->where('date_to', '>', $today)->pluck('unit_id')->flip(), collect());
        $vacant = $vacant->reject(fn ($u) => isset($booked[$u->id]));

        $lastEnd = Lease::query()->whereIn('unit_id', $vacant->pluck('id'))->whereNotNull('end_date')
            ->where('end_date', '<', $today)->selectRaw('unit_id, max(end_date) as e')->groupBy('unit_id')->pluck('e', 'unit_id');
        $nextStart = Lease::query()->whereIn('unit_id', $vacant->pluck('id'))->where('start_date', '>', $today)
            ->selectRaw('unit_id, min(start_date) as s')->groupBy('unit_id')->pluck('s', 'unit_id');
        $online = self::safe(fn () => Listing::query()->online()->whereIn('unit_id', $vacant->pluck('id'))->pluck('id', 'unit_id'), collect());

        $list = $vacant->map(fn ($u) => [
            'unit' => $u,
            'since' => isset($lastEnd[$u->id]) ? (new DateTimeImmutable($lastEnd[$u->id]))->modify('+1 day') : null,
            'next' => isset($nextStart[$u->id]) ? new DateTimeImmutable($nextStart[$u->id]) : null,
            'listing' => $online[$u->id] ?? null,
            'area' => (float) $u->area_sqm,
        ])->sortBy(fn ($v) => $v['since']?->format('Y-m-d') ?? '0000')->values();

        $total = $units->count();
        $area = (float) $units->sum('area_sqm');
        $vacantArea = (float) $list->sum('area');

        return [
            'units' => $total,
            'let' => $total - $list->count(),
            'quote' => (int) round(($total - $list->count()) / $total * 100),
            'area_quote' => $area > 0 ? (int) round(($area - $vacantArea) / $area * 100) : null,
            'vacant' => $list,
            'active' => $rd['active'] ?? null,
            'ending' => $rd['ending'] ?? collect(),
            'index_due' => $rd['index_due'] ?? [],
        ];
    }

    /** Termine der nächsten Tage, nach Datum sortiert: [date, label, detail, url, tone]. */
    private static function dates(User $user, ?int $ownerId, string $today, ?array $rd): array
    {
        $ahead = (new DateTimeImmutable($today))->modify('+'.self::DAYS_AHEAD.' days')->format('Y-m-d');
        $out = [];
        $push = function ($date, string $label, string $detail, string $url, string $tone = 'info') use (&$out) {
            $d = $date instanceof \DateTimeInterface ? DateTimeImmutable::createFromInterface($date) : new DateTimeImmutable((string) $date);
            $out[] = ['date' => $d, 'label' => $label, 'detail' => $detail, 'url' => $url, 'tone' => $tone];
        };

        if ($rd !== null) {
            foreach ($rd['ending'] as $l) {
                $push($l->end_date, 'Vertrag endet', $l->unit?->label.' – '.$l->tenant?->display_name, route('leases.show', $l), 'warn');
            }
            foreach ($rd['changes'] as $r) {
                $push($r->valid_from, $r->reasonLabel(), $r->lease?->unit?->label.' – '.$r->lease?->tenant?->display_name, route('leases.show', $r->lease_id));
            }
            foreach ($rd['index_due'] as $i) {
                $due = $i['since']->copy()->addYear();
                $push($due->format('Y-m-d') < $today ? $today : $due, 'Indexanpassung möglich', $i['lease']->unit?->label.' – '.$i['lease']->tenant?->display_name, route('leases.show', $i['lease']));
            }
        }

        // Neue Verträge (Einzug)
        foreach (self::safe(fn () => Lease::visibleTo($user)->when($ownerId, fn ($q) => $q->where('leases.owner_id', $ownerId))
            ->whereBetween('start_date', [$today, $ahead])->with(['unit', 'tenant'])->get(), collect()) as $l) {
            $push($l->start_date, 'Vertragsbeginn', $l->unit?->label.' – '.$l->tenant?->display_name, route('leases.show', $l));
        }

        // Übergaben, die als Entwurf für ein Datum angelegt sind
        foreach (self::safe(fn () => HandoverProtocol::visibleTo($user)->where('status', 'entwurf')
            ->when($ownerId, fn ($q) => $q->where('handover_protocols.owner_id', $ownerId))
            ->whereBetween('handover_on', [$today, $ahead])->with('lease.unit', 'lease.tenant')->get(), collect()) as $h) {
            $push($h->handover_on, 'Übergabe '.(HandoverProtocol::KINDS[$h->kind] ?? ''), $h->lease?->unit?->label.' – '.$h->lease?->tenant?->display_name, route('handover.show', $h));
        }

        // Montage: Anreise und Abreise
        $unitIds = Unit::visibleTo($user)->join('properties as p', 'p.id', '=', 'units.property_id')
            ->when($ownerId, fn ($q) => $q->where('p.owner_id', $ownerId))->select('units.id');
        foreach (self::safe(fn () => UnitBooking::query()->whereIn('unit_id', $unitIds)->whereIn('status', ['reserviert', 'belegt'])
            ->where(fn ($q) => $q->whereBetween('date_from', [$today, $ahead])->orWhereBetween('date_to', [$today, $ahead]))
            ->with('unit')->get(), collect()) as $b) {
            $who = trim(($b->label ?: 'Buchung').($b->status === 'reserviert' ? ' (reserviert)' : ''));
            if ($b->date_from->format('Y-m-d') >= $today && $b->date_from->format('Y-m-d') <= $ahead) {
                $push($b->date_from, 'Anreise', $b->unit?->label.' – '.$who, route('bookings.index', $b->unit_id));
            }
            if ($b->date_to->format('Y-m-d') >= $today && $b->date_to->format('Y-m-d') <= $ahead) {
                $push($b->date_to, 'Abreise', $b->unit?->label.' – '.$who, route('bookings.index', $b->unit_id));
            }
        }

        // Zahlungsfristen aus Mahnungen
        foreach (self::safe(fn () => Mahnwesen::overview($user, $ownerId, $today), collect()) as $r) {
            if ($r['state'] === 'frist' && $r['last']) {
                $push($r['last']->pay_by, 'Frist '.$r['last']->levelLabel().' läuft ab', $r['lease']->unit?->label.' – '.$r['lease']->tenant?->display_name, route('dunning.index'), 'warn');
            }
        }

        // Fällige Raten
        foreach (self::safe(fn () => InstallmentPlan::visibleTo($user)->where('status', 'aktiv')
            ->when($ownerId, fn ($q) => $q->where('installment_plans.owner_id', $ownerId))
            ->with('installments', 'lease.unit', 'lease.tenant')->get(), collect()) as $p) {
            foreach ($p->installments as $i) {
                $d = $i->due_date->format('Y-m-d');
                if ($d >= $today && $d <= $ahead) {
                    $push($i->due_date, 'Rate '.$i->no.' fällig ('.\App\Support\Money::format($i->amount).')', $p->lease?->unit?->label.' – '.$p->lease?->tenant?->display_name, route('installments.show', $p));
                }
            }
        }

        // Dokumente mit Ablaufdatum (Paket 22)
        foreach (self::safe(fn () => \App\Support\Ablage\Ablage::expiring($user, $ownerId, $today, self::DAYS_AHEAD), collect()) as $doc) {
            if ($doc->expires_on->format('Y-m-d') < $today) {
                continue;
            }
            $t = \App\Support\Ablage\Ablage::target($doc->attach_type, (int) $doc->attach_id);
            $push($doc->expires_on, $doc->categoryLabel().' läuft ab', $doc->title.($t ? ' – '.$t[2] : ''), route('documents.edit', $doc), 'warn');
        }

        // Wartungen und Handwerkertermine (Paket 24)
        foreach (self::safe(fn () => \App\Support\Technik\Wartung::due($user, $ownerId, $today, self::DAYS_AHEAD), collect()) as $t) {
            if ($t->next_due->format('Y-m-d') >= $today) {
                $push($t->next_due, 'Wartung: '.$t->title, $t->place(), route('maintenance.index', ['objekt' => $t->property_id]));
            }
        }
        foreach (self::safe(fn () => \App\Models\WorkOrder::visibleTo($user)->open()->whereNotNull('appointment_at')
            ->when($ownerId, fn ($q) => $q->where('work_orders.owner_id', $ownerId))
            ->whereBetween('appointment_at', [$today.' 00:00:00', $ahead.' 23:59:59'])->with(['vendor', 'property', 'unit'])->get(), collect()) as $o) {
            $push($o->appointment_at, 'Handwerker '.$o->appointment_at->format('H:i').' Uhr', ($o->vendor?->name ?? '').' – '.$o->title.', '.$o->place(), route('workorders.show', $o));
        }

        // Ende der Zinsbindung (Paket 23)
        foreach (self::safe(fn () => \App\Support\Finance\Kennzahlen::fixedEnding($user, 2, $ownerId, $today), collect()) as $l) {
            if ($l->fixed_until->format('Y-m-d') <= $ahead) {
                $push($l->fixed_until, 'Zinsbindung endet', $l->name.($l->property ? ' – '.$l->property->name : ''), route('loans.show', $l), 'warn');
            }
        }

        // Eichfristen und Frist für fernablesbare Zähler (Paket 26)
        $meters = self::safe(fn () => self::meters($user, $ownerId), collect());
        $y0 = substr($today, 0, 4);
        $expiring = $meters->filter(fn ($m) => $m->calibrated_until !== null && (string) $m->calibrated_until === $y0);
        if ($expiring->isNotEmpty() && $y0.'-12-31' <= $ahead) {
            $push($y0.'-12-31', 'Eichfrist endet', $expiring->count().' Zähler – Tausch beauftragen', route('consumption.index'), 'warn');
        }
        if ($meters->contains(fn ($m) => $m->isHeat() && ! $m->remote) && $today <= '2026-12-31' && '2026-12-31' <= $ahead) {
            $push('2026-12-31', 'Frist fernablesbare Zähler', 'Heizung und Warmwasser auf Funk umrüsten', route('consumption.index'), 'warn');
        }

        // Basiszinssatz ändert sich zum 1.1. und 1.7.
        $y = (int) substr($today, 0, 4);
        foreach ([$y.'-01-01', $y.'-07-01', ($y + 1).'-01-01'] as $d) {
            if ($d >= $today && $d <= $ahead) {
                $push($d, 'Neuer Basiszinssatz', 'Wert der Bundesbank im Mahnwesen eintragen', route('dunning.index').'#basiszins');
            }
        }

        // Frist Nebenkostenabrechnung (31.12. für das Vorjahr), ab Oktober anzeigen
        $nk = self::nkMissing($user, $ownerId, $today);
        if ($nk !== [] && substr($today, 5, 2) >= '10') {
            $names = implode(', ', array_map(fn ($n) => $n['property']->name, array_slice($nk, 0, 3))).(count($nk) > 3 ? ' …' : '');
            $push($y.'-12-31', 'Frist Nebenkostenabrechnung '.($y - 1), $names, route('nk.index'), 'warn');
        }

        usort($out, fn ($a, $b) => $a['date'] <=> $b['date']);

        return $out;
    }

    /** Monatsmieten der letzten 12 Monate: Soll und davon bezahlt. */
    private static function chart(User $user, ?int $ownerId, string $today): array
    {
        $start = (new DateTimeImmutable($today))->modify('first day of this month')->modify('-11 months');
        $rows = RentCharge::visibleTo($user)
            ->where('kind', 'miete')
            ->where('due_date', '>=', $start->format('Y-m-d'))
            ->where('due_date', '<=', (new DateTimeImmutable($today))->modify('last day of this month')->format('Y-m-d'))
            ->when($ownerId, fn ($q) => $q->where('rent_charges.owner_id', $ownerId))
            ->selectRaw("date_format(due_date, '%Y-%m') as m, sum(total) as soll, sum(least(paid_amount,total)) as ist")
            ->groupBy(DB::raw("date_format(due_date, '%Y-%m')"))
            ->get()->keyBy('m');

        $months = ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $out = [];
        for ($i = 0; $i < 12; $i++) {
            $m = $start->modify('+'.$i.' months');
            $r = $rows->get($m->format('Y-m'));
            $out[] = [
                'key' => $m->format('Y-m'),
                'label' => $months[(int) $m->format('n')],
                'year' => $m->format('Y'),
                'soll' => round((float) ($r->soll ?? 0), 2),
                'ist' => round((float) ($r->ist ?? 0), 2),
            ];
        }

        return array_sum(array_column($out, 'soll')) > 0 ? $out : [];
    }
}
