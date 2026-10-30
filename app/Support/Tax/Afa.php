<?php

namespace App\Support\Tax;

use App\Models\PropertyAfa;
use App\Models\PropertyAfaYear;
use App\Models\PropertyValue;

/**
 * Gebäude-AfA für die Anlage V (Paket 29). Gerechnet wird nur die lineare AfA (§ 7 Abs. 4 EStG):
 * Bemessungsgrundlage mal Satz, im Anschaffungsjahr anteilig ab dem Monat der Anschaffung, höchstens bis die Grundlage verbraucht ist.
 * Degressive AfA und Sonder-AfA werden nicht gerechnet. Dafür trägt man den Jahresbetrag des Steuerberaters ein; er ersetzt die Rechnung für dieses Jahr.
 */
class Afa
{
    public const RATES = [
        '2' => '2 % (Wohngebäude, Fertigstellung ab 1925)',
        '2.5' => '2,5 % (Wohngebäude, Fertigstellung vor 1925)',
        '3' => '3 % (Wohngebäude, Fertigstellung ab 2023)',
    ];

    /**
     * Jahresbeträge in Cent von Beginn bis $untilYear.
     *
     * @param  array{basis: int, rate: float, start: string}  $cfg
     * @param  array<int, int>  $overrides  Jahr => Cent
     * @return array<int, array{cents: int, source: string, months: int}>
     */
    public static function schedule(array $cfg, int $untilYear, array $overrides = []): array
    {
        $basis = max(0, (int) $cfg['basis']);
        $startYear = (int) substr($cfg['start'], 0, 4);
        $startMonth = (int) substr($cfg['start'], 5, 2);
        $annual = (int) round($basis * (float) $cfg['rate'] / 100);
        $used = 0;
        $out = [];
        for ($y = $startYear; $y <= $untilYear; $y++) {
            $months = $y === $startYear ? 13 - $startMonth : 12;
            if (array_key_exists($y, $overrides)) {
                $cents = max(0, (int) $overrides[$y]);
                $source = 'manuell';
            } else {
                $cents = (int) round($annual * $months / 12);
                $source = 'berechnet';
            }
            $cents = min($cents, max(0, $basis - $used));
            $used += $cents;
            $out[$y] = ['cents' => $cents, 'source' => $source, 'months' => $months];
        }

        return $out;
    }

    /** Bemessungsgrundlage in Cent: direkt eingetragen oder (Kaufpreis + Nebenkosten) mal Gebäudeanteil plus nachträgliche Kosten. */
    public static function basisCents(PropertyAfa $a, ?PropertyValue $v): ?int
    {
        if ($a->basis_override !== null && (float) $a->basis_override > 0) {
            return (int) round((float) $a->basis_override * 100);
        }
        if ($a->building_share !== null && $v !== null && (float) $v->purchase_price > 0) {
            $total = (float) $v->purchase_price + (float) ($v->purchase_costs ?? 0);

            return (int) round($total * (float) $a->building_share / 100 * 100) + (int) round((float) $a->extra_basis * 100);
        }

        return null;
    }

    /**
     * AfA eines Objekts für ein Jahr, oder null wenn nichts hinterlegt ist oder das Jahr vor dem Beginn liegt.
     *
     * @return ?array{cents: int, source: string, months: int, basis: int, rate: float, label: string}
     */
    public static function forYear(int $propertyId, int $year): ?array
    {
        $a = PropertyAfa::query()->where('property_id', $propertyId)->first();
        if ($a === null) {
            return null;
        }
        $v = PropertyValue::query()->where('property_id', $propertyId)->first();
        $start = $a->start_date?->format('Y-m-d') ?? $v?->purchase_date?->format('Y-m-d');
        $basis = self::basisCents($a, $v);
        if ($start === null || $basis === null || $year < (int) substr($start, 0, 4)) {
            return null;
        }
        $overrides = PropertyAfaYear::query()->where('property_id', $propertyId)->pluck('amount', 'year')->map(fn ($x) => (int) round((float) $x * 100))->all();
        $row = self::schedule(['basis' => $basis, 'rate' => (float) $a->rate, 'start' => $start], $year, $overrides)[$year] ?? null;
        if ($row === null) {
            return null;
        }
        $eur = fn (int $c) => number_format($c / 100, 2, ',', '.').' €';
        $rate = rtrim(rtrim(number_format((float) $a->rate, 2, ',', ''), '0'), ',');
        $label = $row['source'] === 'manuell'
            ? 'AfA Gebäude (Jahresbetrag laut Steuerberater)'
            : 'AfA Gebäude (linear '.$rate.' % von '.$eur($basis).($row['months'] < 12 ? ', '.$row['months'].' Monate' : '').')';

        return $row + ['basis' => $basis, 'rate' => (float) $a->rate, 'label' => $label];
    }
}
