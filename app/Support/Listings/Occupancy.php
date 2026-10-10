<?php

namespace App\Support\Listings;

use App\Models\Lease;
use App\Models\UnitBooking;
use DateTimeImmutable;

/**
 * Belegung einer Einheit aus Buchungen (Montage) und Mietverträgen.
 * Zeiträume sind halboffen: [von, bis) – „bis“ ist der Abreisetag und an diesem Tag wieder frei.
 */
class Occupancy
{
    /**
     * @return array<int, array{from: string, to: string, status: string, label: ?string, booking: ?UnitBooking}>
     */
    public static function periods(int $unitId, string $from, string $to): array
    {
        $out = [];
        foreach (UnitBooking::query()->where('unit_id', $unitId)->where('date_from', '<', $to)->where('date_to', '>', $from)->orderBy('date_from')->get() as $b) {
            $out[] = ['from' => $b->date_from->format('Y-m-d'), 'to' => $b->date_to->format('Y-m-d'), 'status' => $b->status, 'label' => $b->label, 'booking' => $b];
        }
        $leases = Lease::query()->where('unit_id', $unitId)->where('start_date', '<', $to)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
            ->with('tenant')->get();
        foreach ($leases as $l) {
            $end = $l->end_date ? $l->end_date->copy()->addDay()->format('Y-m-d') : '9999-12-31';
            $out[] = ['from' => $l->start_date->format('Y-m-d'), 'to' => $end, 'status' => 'vertrag', 'label' => 'Mietvertrag '.$l->tenant?->display_name, 'booking' => null];
        }
        usort($out, fn ($a, $b) => $a['from'] <=> $b['from']);

        return $out;
    }

    /** Überschneidung mit einer anderen Belegung? Gibt die erste gefundene zurück. */
    public static function conflict(int $unitId, string $from, string $to, ?int $ignoreBookingId = null): ?array
    {
        foreach (self::periods($unitId, $from, $to) as $p) {
            if ($p['booking'] && (int) $p['booking']->id === $ignoreBookingId) {
                continue;
            }
            if ($p['from'] < $to && $p['to'] > $from) {
                return $p;
            }
        }

        return null;
    }

    /**
     * Kalender für mehrere Monate. Je Tag: frei | belegt | reserviert | gesperrt (Belegung über Nacht).
     *
     * @return array<int, array{label: string, offset: int, days: array<int, array{day: int, date: string, state: string, past: bool}>}>
     */
    public static function calendar(int $unitId, string $firstMonth, int $months, ?string $today = null): array
    {
        $today ??= date('Y-m-d');
        $start = new DateTimeImmutable(substr($firstMonth, 0, 7).'-01');
        $end = $start->modify('+'.$months.' months');
        $periods = self::periods($unitId, $start->format('Y-m-d'), $end->format('Y-m-d'));
        $names = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];

        $out = [];
        for ($m = 0; $m < $months; $m++) {
            $first = $start->modify('+'.$m.' months');
            $days = [];
            for ($d = 1; $d <= (int) $first->format('t'); $d++) {
                $date = $first->format('Y-m-').str_pad((string) $d, 2, '0', STR_PAD_LEFT);
                $state = 'frei';
                foreach ($periods as $p) {
                    if ($p['from'] <= $date && $p['to'] > $date) {
                        $state = $p['status'] === 'vertrag' ? 'belegt' : $p['status'];
                        break;
                    }
                }
                $days[] = ['day' => $d, 'date' => $date, 'state' => $state, 'past' => $date < $today];
            }
            $out[] = ['label' => $names[(int) $first->format('n')].' '.$first->format('Y'), 'offset' => (int) $first->format('N') - 1, 'days' => $days];
        }

        return $out;
    }

    /** Nächster freier Tag ab heute (für die Übersicht), null wenn im Jahr nichts frei ist. */
    public static function nextFree(int $unitId, ?string $today = null): ?string
    {
        $today ??= date('Y-m-d');
        $date = $today;
        $limit = (new DateTimeImmutable($today))->modify('+365 days')->format('Y-m-d');
        $periods = self::periods($unitId, $today, $limit);
        $moved = true;
        while ($moved && $date < $limit) {
            $moved = false;
            foreach ($periods as $p) {
                if ($p['from'] <= $date && $p['to'] > $date) {
                    $date = $p['to'];
                    $moved = true;
                }
            }
        }

        return $date < $limit ? $date : null;
    }
}
