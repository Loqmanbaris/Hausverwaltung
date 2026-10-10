<?php

namespace App\Support\Nk;

/**
 * Aufteilung der CO2-Kosten zwischen Vermieter und Mieter nach dem Kohlendioxidkostenaufteilungsgesetz (CO2KostAufG).
 * Reine Rechenlogik. Gilt für Abrechnungszeiträume ab dem 1.1.2023.
 *
 *  - Wohngebäude: 10-Stufen-Modell nach kg CO2 je m² Wohnfläche und Jahr (auf eine Nachkommastelle gerundet).
 *  - Nichtwohngebäude: hälftige Teilung.
 *  - § 9: Bei Einschränkungen durch öffentliches Recht wird der Anteil des Vermieters halbiert oder entfällt.
 *  - Etagenheizung mit Gasherd am selben Zähler: Erstattungsanspruch um 5 % gekürzt.
 */
class Co2Split
{
    /** [ab kg CO2/m²/a, Anteil Vermieter in %] */
    public const STEPS = [
        [0, 0], [12, 10], [17, 20], [22, 30], [27, 40], [32, 50], [37, 60], [42, 70], [47, 80], [52, 95],
    ];

    public const FROM = '2023-01-01';

    /** Text der Stufe, z. B. „22 bis unter 27 kg“. */
    public static function stepLabel(int $step): string
    {
        $from = self::STEPS[$step - 1][0];
        $to = self::STEPS[$step][0] ?? null;
        if ($step === 1) {
            return 'unter 12 kg';
        }

        return $to === null ? $from.' kg und mehr' : $from.' bis unter '.$to.' kg';
    }

    /**
     * @return array{applies: bool, kg: float, area: float, days: int, kg_per_sqm: float, step: int|null, step_label: string, tenant_pct: float, landlord_base_pct: int, landlord_pct: float, restriction: string, use: string, cost_cents: int, landlord_cents: int, tenant_cents: int}
     */
    public static function calculate(float $kg, float $costEuro, float $area, int $days, string $use = 'wohn', string $restriction = 'keine', bool $gasStove = false): array
    {
        $days = max(1, $days);
        $cost = (int) round($costEuro * 100);
        $perSqm = $area > 0 ? round($kg / $area * ($days >= 365 ? 1 : 365 / $days), 1) : 0.0;  // kürzere Zeiträume aufs Jahr hochrechnen

        if ($use === 'nichtwohn') {
            $step = null;
            $base = 50;
            $label = 'Nichtwohngebäude: hälftige Teilung';
        } else {
            $step = 1;
            foreach (self::STEPS as $i => [$from]) {
                if ($perSqm >= $from) {
                    $step = $i + 1;
                }
            }
            $base = self::STEPS[$step - 1][1];
            $label = 'Stufe '.$step.' ('.self::stepLabel($step).' CO2 je m² und Jahr)';
        }

        $pct = (float) $base;
        if ($restriction === 'halb') {
            $pct = $base / 2;
        } elseif ($restriction === 'entfall') {
            $pct = 0.0;
        }

        $landlord = (int) round($cost * $pct / 100);
        if ($gasStove) {
            $landlord = (int) round($landlord * 0.95);
        }

        return [
            'applies' => $area > 0 && $cost > 0,
            'kg' => $kg,
            'area' => $area,
            'days' => $days,
            'kg_per_sqm' => $perSqm,
            'step' => $step,
            'step_label' => $label,
            'tenant_pct' => 100 - $pct,
            'landlord_base_pct' => $base,
            'landlord_pct' => $pct,
            'restriction' => $restriction,
            'use' => $use,
            'gas_stove' => $gasStove,
            'cost_cents' => $cost,
            'landlord_cents' => $landlord,
            'tenant_cents' => $cost - $landlord,
        ];
    }

    public static function pct(float $p): string
    {
        return rtrim(rtrim(number_format($p, 1, ',', ''), '0'), ',').' %';
    }
}
