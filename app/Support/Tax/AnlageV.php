<?php

namespace App\Support\Tax;

use App\Models\Lease;
use App\Models\Property;
use App\Models\Receipt;
use App\Models\RentPayment;
use App\Support\Kostenarten;
use App\Support\Nk\NkCalculator;
use Illuminate\Support\Facades\DB;

/**
 * Zusammenstellung für die Anlage V (Einkünfte aus Vermietung und Verpachtung) je Eigentümer, Objekt und Jahr.
 *
 * Einnahmen nach Zufluss: Zahlungseingänge im Jahr, aufgeteilt nach den Monaten, die sie bezahlen, und deren Bestandteilen.
 * Werbungskosten nach Belegdatum (vereinfachend als Zahlungsdatum). AfA ist nicht enthalten.
 * Das Ergebnis ist eine Arbeitshilfe für den Steuerberater, keine fertige Steuererklärung.
 */
class AnlageV
{
    /** Einnahmen-Arten in der Reihenfolge der Anzeige. */
    public const INCOME = [
        'wohnen' => 'Mieteinnahmen Wohnungen (ohne Umlagen)',
        'montage' => 'Mieteinnahmen möblierte Montage-Wohnungen (ohne Umlagen)',
        'gewerbe' => 'Mieteinnahmen andere Räume / Gewerbe (ohne Umlagen, netto)',
        'garage' => 'Einnahmen aus Garagen und Stellplätzen',
        'umlagen' => 'Umlagen (Nebenkosten-Vorauszahlungen und Nachzahlungen)',
        'erstattung' => 'Erstattungen an Mieter (Guthaben aus Nebenkostenabrechnung)',
        'ust' => 'Vereinnahmte Umsatzsteuer',
        'verzugszinsen' => 'Verzugszinsen (Kapitalerträge, nicht in der Summe der Anlage V)',
    ];

    /** Werbungskosten-Gruppen. */
    public const COSTS = [
        'betrieb' => 'Grundsteuer, Versicherungen und laufende Betriebskosten',
        'erhaltung' => 'Erhaltungsaufwendungen (Reparaturen)',
        'modernisierung' => 'Modernisierung und Anschaffungen (mit Steuerberater klären: sofort, verteilt oder AfA)',
        'zinsen' => 'Schuldzinsen',
        'verwaltung' => 'Verwaltungskosten (Steuerberatung, Bankgebühren)',
        'sonstige' => 'Sonstige Werbungskosten',
    ];

    public static function costGroup(string $key): string
    {
        return match ($key) {
            'reparatur' => 'erhaltung',
            'modernisierung' => 'modernisierung',
            'darlehen_zinsen' => 'zinsen',
            'steuerberatung', 'bankgebuehren' => 'verwaltung',
            'sonstiges' => 'sonstige',
            default => self::groupOf($key),
        };
    }

    private static function groupOf(string $key): string
    {
        static $groups = null;
        $groups ??= DB::table('cost_types')->pluck('group_label', 'key')->all();
        $g = (string) ($groups[$key] ?? '');
        if (str_contains($g, 'Betriebskosten')) {
            return 'betrieb';
        }
        if (str_contains($g, 'Instandhaltung')) {
            return 'erhaltung';
        }

        return 'sonstige';
    }

    /**
     * @return array{properties: array<int, array>, unassigned: array, totals: array}
     */
    public static function build(int $ownerId, int $year): array
    {
        $from = $year.'-01-01';
        $to = $year.'-12-31';
        $properties = Property::query()->withTrashed()->where('owner_id', $ownerId)->orderBy('name')->get();

        $out = [];
        foreach ($properties as $p) {
            $out[$p->id] = [
                'property' => $p,
                'income' => array_fill_keys(array_keys(self::INCOME), 0),
                'costs' => array_fill_keys(array_keys(self::COSTS), 0),
                'cost_details' => [],
                'receipts' => 0,
                'payments' => 0,
            ];
        }

        // Einnahmen
        $payments = RentPayment::query()
            ->where('owner_id', $ownerId)
            ->whereBetween('paid_on', [$from, $to])
            ->where('source', '!=', 'import')   // Übernahme beim Import ist kein Zufluss
            ->with(['lease.unit', 'allocations.charge'])
            ->get();

        foreach ($payments as $pay) {
            $lease = $pay->lease;
            $pid = $lease?->unit?->property_id;
            if ($pid === null || ! isset($out[$pid])) {
                continue;
            }
            $out[$pid]['payments']++;
            foreach (self::splitPayment($pay, $lease) as $k => $cents) {
                $out[$pid]['income'][$k] += $cents;
            }
        }

        // Werbungskosten
        $receipts = Receipt::query()
            ->where('owner_id', $ownerId)
            ->whereBetween('receipt_date', [$from, $to])
            ->whereNotNull('amount_gross')
            ->get();

        $unassigned = ['costs' => array_fill_keys(array_keys(self::COSTS), 0), 'cost_details' => [], 'receipts' => 0];
        foreach ($receipts as $r) {
            $cents = (int) round((float) $r->amount_gross * 100);
            $group = self::costGroup((string) $r->cost_category);
            $label = Kostenarten::label($r->cost_category);
            if ($r->property_id && isset($out[$r->property_id])) {
                $t = &$out[$r->property_id];
            } else {
                $t = &$unassigned;
            }
            $t['costs'][$group] += $cents;
            $t['cost_details'][$group][$label] = ($t['cost_details'][$group][$label] ?? 0) + $cents;
            $t['receipts']++;
            unset($t);
        }

        // Schuldzinsen aus den Darlehen (Paket 23): laut Zinsbescheinigung der Bank, sonst laut Tilgungsplan
        if (\Illuminate\Support\Facades\Schema::hasTable('loans')) {
            foreach (\App\Models\Loan::query()->where('owner_id', $ownerId)->with('events')->get() as $loan) {
                $int = \App\Support\Finance\LoanPlan::interestForYear($loan, $year);
                $cents = (int) round($int['amount'] * 100);
                if ($cents === 0) {
                    continue;
                }
                $label = 'Darlehen „'.$loan->name.'“ ('.($int['source'] === 'bank' ? 'laut Bank' : 'laut Tilgungsplan').')';
                if ($loan->property_id && isset($out[$loan->property_id])) {
                    $t = &$out[$loan->property_id];
                } else {
                    $t = &$unassigned;
                }
                $t['costs']['zinsen'] += $cents;
                $t['cost_details']['zinsen'][$label] = ($t['cost_details']['zinsen'][$label] ?? 0) + $cents;
                $t['loans'] = ($t['loans'] ?? 0) + 1;
                unset($t);
            }
        }

        $totals = ['income' => 0, 'costs' => 0];
        foreach ($out as &$o) {
            $o['income_total'] = array_sum($o['income']) - $o['income']['verzugszinsen'];
            $o['cost_total'] = array_sum($o['costs']);
            $o['result'] = $o['income_total'] - $o['cost_total'];
            $totals['income'] += $o['income_total'];
            $totals['costs'] += $o['cost_total'];
        }
        unset($o);
        $unassigned['cost_total'] = array_sum($unassigned['costs']);
        $totals['costs'] += $unassigned['cost_total'];
        $totals['result'] = $totals['income'] - $totals['costs'];

        // Objekte ohne Bewegung im Jahr weglassen
        $out = array_filter($out, fn ($o) => $o['payments'] > 0 || $o['receipts'] > 0 || ($o['loans'] ?? 0) > 0);

        return ['properties' => $out, 'unassigned' => $unassigned, 'totals' => $totals];
    }

    /**
     * Teilt eine Zahlung in Cent nach Einnahmen-Art auf (Summe = Zahlung).
     *
     * @return array<string, int>
     */
    public static function splitPayment(RentPayment $pay, ?Lease $lease): array
    {
        $type = $lease?->lease_type ?? 'wohnen';
        $rentKey = in_array($type, ['wohnen', 'montage', 'gewerbe'], true) ? $type : 'wohnen';
        $total = (int) round((float) $pay->amount * 100);
        $out = array_fill_keys(array_keys(self::INCOME), 0);

        // Guthaben aus der Nebenkostenabrechnung: Erstattung (mindert die Einnahmen)
        if ($pay->source === 'nk' || $pay->source === 'co2') {
            $out['erstattung'] = -$total;

            return $out;
        }
        if ($total <= 0) {
            $out[$rentKey] = $total;

            return $out;
        }

        $used = 0;
        foreach ($pay->allocations as $a) {
            $c = $a->charge;
            if ($c === null) {
                continue;
            }
            $cents = (int) round((float) $a->amount * 100);
            $used += $cents;
            $isNk = ($c->kind ?? 'miete') !== 'miete';
            $weights = [
                $rentKey => self::c($c->rent_cold) + self::c($c->furniture),
                'garage' => self::c($c->parking) + self::c($c->garage),
                'umlagen' => self::c($c->prepay_operating) + self::c($c->prepay_heating),
                'ust' => self::c($c->vat_amount),
            ];
            if ($isNk) {
                // Mahngebühren sind Einnahmen aus Vermietung, Nebenkosten-Nachzahlungen Umlagen
                $weights = [match ($c->kind) {
                    'mahngebuehr' => $rentKey,
                    'zinsen' => 'verzugszinsen',
                    default => 'umlagen',
                } => max(1, self::c($c->total))];
            }
            foreach (NkCalculator::distribute($cents, array_filter($weights, fn ($w) => $w > 0)) as $k => $v) {
                $out[$k] += $v;
            }
        }
        // Rest ohne Soll (Vorauszahlung, Guthaben des Mieters)
        $out[$rentKey] += $total - $used;

        return $out;
    }

    private static function c(mixed $v): int
    {
        return (int) round((float) $v * 100);
    }

    /** CSV für Excel (Semikolon, UTF-8 mit Kennung). */
    public static function csv(array $data, string $ownerName, int $year): string
    {
        $cell = fn ($v) => '"'.str_replace('"', '""', (string) $v).'"';
        $eur = fn (int $c) => number_format($c / 100, 2, ',', '');
        $rows = [[$cell('Anlage V '.$year.' – '.$ownerName)], [$cell('Objekt'), $cell('Bereich'), $cell('Position'), $cell('Betrag in Euro')]];
        foreach ($data['properties'] as $o) {
            $name = $o['property']->name;
            foreach (self::INCOME as $k => $label) {
                if ($o['income'][$k] !== 0) {
                    $rows[] = [$cell($name), $cell('Einnahmen'), $cell($label), $cell($eur($o['income'][$k]))];
                }
            }
            foreach (self::COSTS as $k => $label) {
                if ($o['costs'][$k] !== 0) {
                    $rows[] = [$cell($name), $cell('Werbungskosten'), $cell($label), $cell($eur($o['costs'][$k]))];
                }
            }
            $rows[] = [$cell($name), $cell('Ergebnis'), $cell('Überschuss (ohne AfA)'), $cell($eur($o['result']))];
        }
        foreach (self::COSTS as $k => $label) {
            if ($data['unassigned']['costs'][$k] !== 0) {
                $rows[] = [$cell('ohne Objekt'), $cell('Werbungskosten'), $cell($label), $cell($eur($data['unassigned']['costs'][$k]))];
            }
        }

        return "\xEF\xBB\xBF".implode("\r\n", array_map(fn ($r) => implode(';', $r), $rows))."\r\n";
    }
}
