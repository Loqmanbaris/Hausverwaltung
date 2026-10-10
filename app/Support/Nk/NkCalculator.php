<?php

namespace App\Support\Nk;

/**
 * Verteilt die Kosten einer Nebenkostenabrechnung auf Mieter und Leerstand. Reine Rechenlogik ohne Datenbank.
 *
 * Eingabe:
 *  - days: Tage des Abrechnungszeitraums
 *  - units: [id => [label, area, commercial(bool), consumption => [wasser, warmwasser, heizung] (null = fehlt)]]
 *  - segments: Zeitabschnitte je Einheit: [unit, lease (null = Eigentümer: Leerstand oder Montage), label, days, person_days]
 *  - lines: Kostenzeilen: [id, cost_key, label, amount, key_type, commercial_percent, unit (nur diese Einheit)]
 *  - share: Anteil nach Verbrauch bei Heizung und Warmwasser in Prozent
 *
 * Ausgabe je Kostenzeile die verteilten Beträge je Abschnitt in Cent (Summe exakt), dazu die Bezugsgrößen.
 */
class NkCalculator
{
    public const BASIS = [
        'flaeche' => 'm²',
        'einheiten' => 'Einheiten',
        'personen' => 'Personen',
        'wasser' => 'm³',
        'heizung_verbrauch' => 'Einh.',
        'warmwasser_verbrauch' => 'm³',
        'direkt' => 'direkt',
    ];

    /**
     * @return array{lines: array<int, array>, segments: array<int, array>, warnings: string[]}
     */
    public static function calculate(int $days, array $units, array $segments, array $lines, int $share): array
    {
        $warnings = [];
        $out = [];

        // Heizung und Warmwasser in Verbrauchs- und Flächenanteil aufteilen
        $expanded = [];
        foreach ($lines as $l) {
            $cents = (int) round(((float) $l['amount']) * 100);
            if ($l['unit'] !== null) {
                $expanded[] = $l + ['cents' => $cents, 'basis' => 'direkt', 'part' => ''];

                continue;
            }
            if (in_array($l['key_type'], ['heizung', 'warmwasser'], true)) {
                $c1 = (int) round($cents * $share / 100);
                $expanded[] = $l + ['cents' => $c1, 'basis' => $l['key_type'].'_verbrauch', 'part' => $share.' % nach Verbrauch'];
                $expanded[] = $l + ['cents' => $cents - $c1, 'basis' => 'flaeche', 'part' => (100 - $share).' % nach Fläche'];

                continue;
            }
            $expanded[] = $l + ['cents' => $cents, 'basis' => $l['key_type'], 'part' => ''];
        }

        // Vorwegabzug Gewerbe: eigene Zeile, unter den Gewerbe-Einheiten nach Fläche verteilt; der Rest auf die übrigen Einheiten
        $hasCommercial = count(array_filter($units, fn ($u) => $u['commercial'])) > 0;
        $split = [];
        foreach ($expanded as $l) {
            $pct = (float) ($l['commercial_percent'] ?? 0);
            if ($l['basis'] === 'direkt' || $pct <= 0) {
                $split[] = $l + ['only' => null];

                continue;
            }
            if (! $hasCommercial) {
                $warnings[] = '„'.$l['label'].'“: Vorwegabzug Gewerbe eingestellt, aber das Objekt hat keine Gewerbe-Einheit. Es wird auf alle verteilt.';
                $split[] = $l + ['only' => null];

                continue;
            }
            $c = (int) round($l['cents'] * $pct / 100);
            $pctLabel = rtrim(rtrim(number_format($pct, 2, ',', ''), '0'), ',');
            $split[] = array_merge($l, ['cents' => $c, 'basis' => 'flaeche', 'only' => true, 'part' => trim($l['part'].' Vorwegabzug Gewerbe '.$pctLabel.' %')]);
            $split[] = array_merge($l, ['cents' => $l['cents'] - $c, 'only' => false, 'part' => trim($l['part'].' übrige '.rtrim(rtrim(number_format(100 - $pct, 2, ',', ''), '0'), ',').' %')]);
        }

        foreach ($split as $l) {
            $groups = [[$l['only'], $l['cents']]];
            $pct = 0;

            $alloc = [];
            $baseTotal = 0.0;
            foreach ($groups as [$commercial, $amount]) {
                $weights = [];
                foreach ($segments as $i => $s) {
                    $u = $units[$s['unit']] ?? null;
                    if ($u === null) {
                        continue;
                    }
                    if ($commercial !== null && $u['commercial'] !== $commercial) {
                        continue;
                    }
                    if ($l['basis'] === 'direkt' && (int) $s['unit'] !== (int) $l['unit']) {
                        continue;
                    }
                    $w = self::weight($l['basis'], $u, $s, $days, $units, $segments, $warnings, $l['label']);
                    if ($w > 0) {
                        $weights[$i] = $w;
                    }
                }

                if ($weights === []) {
                    if ($amount !== 0) {
                        $warnings[] = '„'.$l['label'].'“'.($l['part'] ? ' ('.$l['part'].')' : '').': Keine Bezugsgröße vorhanden (z. B. Fläche, Personen oder Zählerstände fehlen). Der Betrag bleibt beim Eigentümer.';
                        $alloc['owner'] = ($alloc['owner'] ?? 0) + $amount;
                    }

                    continue;
                }
                foreach (self::distribute($amount, $weights) as $i => $c) {
                    $alloc[$i] = ($alloc[$i] ?? 0) + $c;
                }
                foreach ($weights as $i => $w) {
                    $baseTotal += $w;
                }
            }

            $out[] = [
                'id' => $l['id'],
                'cost_key' => $l['cost_key'],
                'label' => $l['label'],
                'part' => $l['part'],
                'basis' => $l['basis'],
                'cents' => $l['cents'],
                'commercial_percent' => $pct > 0 ? $pct : null,
                'base_total' => $baseTotal,
                'alloc' => $alloc,
            ];
        }

        // Bezugsgröße je Abschnitt (für die Anzeige „Ihr Anteil“)
        $segInfo = [];
        foreach ($segments as $i => $s) {
            $segInfo[$i] = $s;
        }

        return ['lines' => $out, 'segments' => $segInfo, 'warnings' => array_values(array_unique($warnings))];
    }

    /** Gewicht eines Abschnitts für einen Schlüssel. Bei Verbrauch wird der Verbrauch der Einheit nach Tagen auf ihre Abschnitte verteilt. */
    public static function weight(string $basis, array $u, array $s, int $days, array $units, array $segments, array &$warnings, string $label): float
    {
        switch ($basis) {
            case 'flaeche':
                if ((float) $u['area'] <= 0) {
                    $warnings[] = 'Bei „'.$u['label'].'“ fehlt die Fläche (wird für „'.$label.'“ gebraucht).';

                    return 0.0;
                }

                return (float) $u['area'] * $s['days'] / $days;
            case 'einheiten':
            case 'direkt':
                return $s['days'] / $days;
            case 'personen':
                if ($s['lease'] !== null && $s['person_days'] === null) {
                    $warnings[] = 'Bei „'.$u['label'].'“ ('.$s['label'].') fehlt die Personenzahl (wird für „'.$label.'“ gebraucht).';

                    return 0.0;
                }

                return (float) ($s['person_days'] ?? 0) / $days;
            case 'wasser':
            case 'heizung_verbrauch':
            case 'warmwasser_verbrauch':
                $kind = ['wasser' => 'wasser', 'heizung_verbrauch' => 'heizung', 'warmwasser_verbrauch' => 'warmwasser'][$basis];
                $consumption = $u['consumption'][$kind] ?? null;
                if ($consumption === null) {
                    $warnings[] = 'Bei „'.$u['label'].'“ fehlen Zähler oder Zählerstände für '.['wasser' => 'Wasser', 'heizung' => 'Heizung', 'warmwasser' => 'Warmwasser'][$kind].' (wird für „'.$label.'“ gebraucht).';

                    return 0.0;
                }
                $unitDays = 0;
                foreach ($segments as $o) {
                    if ((int) $o['unit'] === (int) $s['unit']) {
                        $unitDays += $o['days'];
                    }
                }

                return $unitDays > 0 ? max(0.0, (float) $consumption) * $s['days'] / $unitDays : 0.0;
        }

        return 0.0;
    }

    /**
     * Verteilt Cent nach Gewichten, größter Rest zuerst. Die Summe stimmt immer genau.
     *
     * @param  array<int|string, float>  $weights
     * @return array<int|string, int>
     */
    public static function distribute(int $cents, array $weights): array
    {
        $sum = array_sum($weights);
        $out = [];
        if ($sum <= 0) {
            return $out;
        }
        $sign = $cents < 0 ? -1 : 1;
        $abs = abs($cents);
        $rest = [];
        $given = 0;
        foreach ($weights as $k => $w) {
            $exact = $abs * $w / $sum;
            $out[$k] = (int) floor($exact);
            $rest[$k] = $exact - $out[$k];
            $given += $out[$k];
        }
        arsort($rest);
        foreach (array_keys($rest) as $k) {
            if ($given >= $abs) {
                break;
            }
            $out[$k]++;
            $given++;
        }

        return array_map(fn ($c) => $sign * $c, $out);
    }

    /** Anzeige der Bezugsgröße, z. B. „78,50 m²“ oder „2,0 Personen“. */
    public static function formatBase(string $basis, float $value): string
    {
        $dec = in_array($basis, ['einheiten', 'personen', 'direkt'], true) ? 2 : 2;

        return number_format($value, $dec, ',', '.').' '.(self::BASIS[$basis] ?? '');
    }
}
