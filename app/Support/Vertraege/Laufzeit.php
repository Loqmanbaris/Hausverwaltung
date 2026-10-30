<?php

namespace App\Support\Vertraege;

use App\Support\Rent\Erhoehung\Regeln;

/**
 * Laufzeit und Kündigungstermin eines laufenden Vertrags (Paket 32). Reine Rechenfunktionen ohne Datenbank.
 * Die Frist wird vorsichtig gerechnet (einen Tag früher als nötig), damit nichts knapp verpasst wird.
 */
class Laufzeit
{
    /** Letzter Tag, an dem die Kündigung dem Anbieter zugegangen sein sollte. */
    public static function deadline(string $end, int $value, string $unit): string
    {
        $d = match ($unit) {
            'tage' => date('Y-m-d', strtotime($end.' -'.$value.' days')),
            'wochen' => date('Y-m-d', strtotime($end.' -'.($value * 7).' days')),
            default => Regeln::addMonths($end, -$value),
        };

        return date('Y-m-d', strtotime($d.' -1 day'));
    }

    /**
     * Zustand eines Vertrags für Erinnerungen und Listen.
     *
     * @param  array{status: string, term_end: ?string, auto_renew: bool, renew_months: int, notice_value: int, notice_unit: string, remind_days: int}  $c
     * @return array{state: string, next_end: ?string, deadline: ?string, action_date: ?string, days: ?int, missed_end: ?string, label: string}
     */
    public static function info(array $c, string $today): array
    {
        $none = ['next_end' => null, 'deadline' => null, 'action_date' => null, 'days' => null, 'missed_end' => null];
        if ($c['status'] !== 'aktiv') {
            return ['state' => $c['status'], 'label' => $c['status'] === 'gekuendigt' ? 'gekündigt' : 'beendet'] + $none;
        }
        $end = $c['term_end'];
        if ($end === null) {
            return ['state' => 'jederzeit', 'label' => 'jederzeit kündbar, Frist '.$c['notice_value'].' '.$c['notice_unit']] + $none;
        }
        $remind = (int) $c['remind_days'];

        if (! $c['auto_renew']) {
            $days = (int) round((strtotime($end) - strtotime($today)) / 86400);
            if ($days < 0) {
                return ['state' => 'abgelaufen', 'label' => 'abgelaufen seit '.Regeln::de($end), 'next_end' => $end, 'deadline' => null, 'action_date' => $end, 'days' => $days, 'missed_end' => null];
            }

            return [
                'state' => $days <= 7 ? 'dringend' : ($days <= $remind ? 'bald' : 'ok'),
                'label' => 'endet am '.Regeln::de($end), 'next_end' => $end, 'deadline' => null, 'action_date' => $end, 'days' => $days, 'missed_end' => null,
            ];
        }

        $renew = max(1, (int) $c['renew_months']);
        $missed = null;
        $deadline = self::deadline($end, (int) $c['notice_value'], $c['notice_unit']);
        for ($i = 0; $deadline < $today && $i < 240; $i++) {
            $missed ??= $end;
            $end = Regeln::addMonths($end, $renew);
            $deadline = self::deadline($end, (int) $c['notice_value'], $c['notice_unit']);
        }
        $days = (int) round((strtotime($deadline) - strtotime($today)) / 86400);

        return [
            'state' => $days <= 7 ? 'dringend' : ($days <= $remind ? 'bald' : 'ok'),
            'label' => 'kündigen bis '.Regeln::de($deadline).' (zum '.Regeln::de($end).')',
            'next_end' => $end, 'deadline' => $deadline, 'action_date' => $deadline, 'days' => $days, 'missed_end' => $missed,
        ];
    }

    /** Kosten im Jahr in Cent. */
    public static function annualCents(int $amountCents, string $interval): int
    {
        $f = ['monatlich' => 12, 'vierteljaehrlich' => 4, 'halbjaehrlich' => 2, 'jaehrlich' => 1][$interval] ?? 0;

        return $amountCents * $f;
    }
}
