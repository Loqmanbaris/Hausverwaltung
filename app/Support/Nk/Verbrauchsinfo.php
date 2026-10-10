<?php

namespace App\Support\Nk;

use App\Models\ConsumptionNotice;
use App\Models\Lease;
use App\Models\Meter;
use App\Models\NkSetting;
use App\Models\Property;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Monatliche Verbrauchsinformation nach § 6a Heizkostenverordnung (Paket 26).
 * Pflicht, sobald fernablesbare Zähler eingebaut sind: Verbrauch im Vormonat, Vergleich mit dem Vormonat,
 * dem gleichen Monat im Vorjahr und einem Vergleichsnutzer.
 */
class Verbrauchsinfo
{
    /** Tage, die eine Ablesung vom Monatsende abweichen darf. */
    public const TOLERANCE = 5;

    /** Verteilung des Jahres-Heizwärmebedarfs auf die Monate in Promille (Gradtagszahlen nach VDI 2067). */
    public const MONTH_WEIGHTS = [1 => 170, 2 => 150, 3 => 130, 4 => 80, 5 => 40, 6 => 13.3, 7 => 13.3, 8 => 13.3, 9 => 30, 10 => 80, 11 => 120, 12 => 160];

    public const MONTHS = [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];

    public static function monthLabel(string $month): string
    {
        return self::MONTHS[(int) substr($month, 5, 2)].' '.substr($month, 0, 4);
    }

    public static function previousMonth(?string $today = null): string
    {
        return (new DateTimeImmutable(($today ?? date('Y-m-d'))))->modify('first day of last month')->format('Y-m');
    }

    /** Objekte mit fernablesbaren Zählern für Heizung oder Warmwasser (dort ist die Verbrauchsinfo Pflicht). */
    public static function properties(User $user, ?int $ownerId = null): Collection
    {
        $ids = Meter::query()->whereIn('kind', Meter::HEAT_KINDS)->where('active', true)->where('remote', true)->distinct()->pluck('property_id')->all();
        if ($ids === []) {
            return collect();
        }

        return Property::visibleTo($user)->whereIn('properties.id', $ids)->when($ownerId, fn ($q) => $q->where('properties.owner_id', $ownerId))->orderBy('name')->get();
    }

    /** Objekte, für die die Verbrauchsinfo des Vormonats noch nicht versendet oder erledigt ist. */
    public static function open(User $user, ?int $ownerId = null, ?string $today = null): Collection
    {
        $month = self::previousMonth($today).'-01';

        return self::properties($user, $ownerId)->filter(fn ($p) => ! ConsumptionNotice::query()->where('property_id', $p->id)->where('month', $month)->exists())->values();
    }

    /**
     * @return array{month: string, label: string, rows: array<int, array>, has_data: bool, heat_unit: string}
     */
    public static function build(Property $property, string $month): array
    {
        $setting = NkSetting::forProperty((int) $property->id);
        $units = $property->units()->orderBy('label')->get();
        $meters = Meter::query()->where('property_id', $property->id)->whereIn('kind', Meter::HEAT_KINDS)->where('active', true)->with('readings')->get()->groupBy('unit_id');
        $m = (int) substr($month, 5, 2);
        $prev = (new DateTimeImmutable($month.'-01'))->modify('-1 month')->format('Y-m');
        $lastYear = ((int) substr($month, 0, 4) - 1).'-'.substr($month, 5, 2);
        $end = (new DateTimeImmutable($month.'-01'))->modify('last day of this month')->format('Y-m-d');

        $rows = [];
        foreach ($units as $u) {
            $list = $meters->get($u->id, collect());
            if ($list->isEmpty()) {
                continue;
            }
            $heatMeters = $list->whereIn('kind', ['waerme', 'hkv']);
            $waterMeters = $list->where('kind', 'warmwasser');
            $lease = Lease::query()->where('unit_id', $u->id)->where('start_date', '<=', $end)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $end))
                ->where('lease_type', '!=', 'montage')->with(['tenant.contacts', 'unit.property', 'owner'])->orderByDesc('start_date')->first();
            $rows[$u->id] = [
                'unit' => $u,
                'area' => (float) ($u->area_sqm ?? 0),
                'lease' => $lease,
                'heat_unit' => $heatMeters->isNotEmpty() && $heatMeters->every(fn ($x) => $x->kind === 'waerme') ? 'kWh' : 'Einheiten',
                'heat' => $heatMeters->isEmpty() ? null : [
                    'value' => self::sum($heatMeters, $month),
                    'prev' => self::sum($heatMeters, $prev),
                    'last_year' => self::sum($heatMeters, $lastYear),
                ],
                'water' => $waterMeters->isEmpty() ? null : [
                    'value' => self::sum($waterMeters, $month),
                    'prev' => self::sum($waterMeters, $prev),
                    'last_year' => self::sum($waterMeters, $lastYear),
                ],
                'remote' => $list->every(fn ($x) => $x->remote),
            ];
        }

        // Durchschnitt im Haus je m² (nur Einheiten mit Wert und Fläche)
        foreach (['heat', 'water'] as $k) {
            $sum = 0.0;
            $area = 0.0;
            foreach ($rows as $r) {
                if ($r[$k] !== null && $r[$k]['value'] !== null && $r['area'] > 0) {
                    $sum += $r[$k]['value'];
                    $area += $r['area'];
                }
            }
            foreach ($rows as &$r) {
                if ($r[$k] !== null) {
                    $r[$k]['avg'] = $area > 0 && $r['area'] > 0 && count($rows) > 1 ? round($sum / $area * $r['area'], 1) : null;
                }
            }
            unset($r);
        }

        // Vergleichsnutzer (Wert aus den Einstellungen, kWh je m² und Jahr, nach Gradtagszahlen auf den Monat verteilt)
        $bench = $setting->benchmark_kwh !== null ? (float) $setting->benchmark_kwh : null;
        foreach ($rows as &$r) {
            if ($r['heat'] !== null) {
                $r['heat']['bench'] = $bench !== null && $r['heat_unit'] === 'kWh' && $r['area'] > 0 ? round($bench * $r['area'] * self::MONTH_WEIGHTS[$m] / 1000, 1) : null;
            }
        }
        unset($r);

        $hasData = collect($rows)->contains(fn ($r) => ($r['heat']['value'] ?? null) !== null || ($r['water']['value'] ?? null) !== null);

        return ['month' => $month, 'label' => self::monthLabel($month), 'rows' => $rows, 'has_data' => $hasData, 'benchmark' => $bench];
    }

    /** Summe des Monatsverbrauchs mehrerer Zähler, null wenn ein Stand fehlt. */
    private static function sum(Collection $meters, string $month): ?float
    {
        $total = 0.0;
        foreach ($meters as $m) {
            $v = self::monthValue($m, $month);
            if ($v === null) {
                return null;
            }
            $total += $v;
        }

        return round($total, 3);
    }

    /** Verbrauch eines Zählers im Monat: Stand am Monatsende minus Stand am Ende des Vormonats (je ± 5 Tage). */
    public static function monthValue(Meter $m, string $month): ?float
    {
        $start = (new DateTimeImmutable($month.'-01'))->modify('-1 day');
        $end = (new DateTimeImmutable($month.'-01'))->modify('last day of this month');
        $a = self::near($m, $start);
        $b = self::near($m, $end);
        if ($a === null || $b === null || $b[1] === $a[1] || $b[0] < $a[0]) {
            return null;
        }

        return ($b[0] - $a[0]) * (float) $m->factor;
    }

    /** @return array{0: float, 1: string}|null [Wert, Datum] */
    private static function near(Meter $m, DateTimeImmutable $target): ?array
    {
        $best = null;
        $bestDiff = null;
        foreach ($m->readings as $r) {
            $diff = abs((int) $target->diff(new DateTimeImmutable($r->read_on->format('Y-m-d')))->days);
            if ($diff <= self::TOLERANCE && ($bestDiff === null || $diff < $bestDiff)) {
                $best = [(float) $r->value, $r->read_on->format('Y-m-d')];
                $bestDiff = $diff;
            }
        }

        return $best;
    }

    /** Veränderung in Prozent, z. B. „+12 %“. */
    public static function change(?float $now, ?float $before): ?string
    {
        if ($now === null || $before === null || $before <= 0) {
            return null;
        }
        $p = (int) round(($now - $before) / $before * 100);

        return ($p > 0 ? '+' : ($p < 0 ? '−' : '±')).abs($p).' %';
    }

    public static function fmt(?float $v, int $dec = 1): string
    {
        return $v === null ? '–' : number_format($v, $dec, ',', '.');
    }
}
