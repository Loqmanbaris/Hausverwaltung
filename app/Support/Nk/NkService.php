<?php

namespace App\Support\Nk;

use App\Models\Lease;
use App\Models\LeasePerson;
use App\Models\Meter;
use App\Models\NkCostKey;
use App\Models\NkSetting;
use App\Models\NkSettlement;
use App\Models\NkSettlementCost;
use App\Models\Receipt;
use App\Models\RentCharge;
use App\Models\RentPayment;
use App\Models\Unit;
use App\Models\User;
use App\Support\Audit;
use App\Support\Kostenarten;
use App\Support\Rent\RentLedger;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Nebenkostenabrechnung: Kosten einlesen, Daten zusammenstellen, berechnen, abschließen. */
class NkService
{
    /** Kostenarten mit haushaltsnahen Dienstleistungen (§ 35a EStG), Anteil wird auf der Abrechnung ausgewiesen. */
    public const SECTION_35A = ['hausmeister', 'gartenpflege', 'reinigung', 'winterdienst', 'schornsteinfeger', 'wartung', 'aufzug'];

    /** Tage, die eine Ablesung vom Stichtag abweichen darf. */
    public const READING_TOLERANCE = 31;

    /** Belege des Objekts im Zeitraum mit umlagefähiger Kostenart übernehmen (nur noch nicht vorhandene). */
    public static function importReceipts(NkSettlement $s): int
    {
        $allocatable = DB::table('cost_types')->where('allocatable', true)->pluck('key')->all();
        $have = NkSettlementCost::query()->where('nk_settlement_id', $s->id)->whereNotNull('receipt_id')->pluck('receipt_id')->all();

        $receipts = Receipt::query()
            ->where('property_id', $s->property_id)
            ->whereBetween('receipt_date', [$s->period_from->format('Y-m-d'), $s->period_to->format('Y-m-d')])
            ->whereIn('cost_category', $allocatable)
            ->whereNotNull('amount_gross')
            ->whereNotIn('id', $have)
            ->orderBy('receipt_date')
            ->get();

        foreach ($receipts as $r) {
            NkSettlementCost::create([
                'nk_settlement_id' => $s->id,
                'receipt_id' => $r->id,
                'cost_key' => $r->cost_category,
                'amount' => $r->amount_gross,
                'unit_id' => $r->unit_id,
                'label' => trim(($r->vendor_name ?? '').($r->invoice_number ? ', Nr. '.$r->invoice_number : '')) ?: null,
                'included' => true,
            ]);
        }

        return $receipts->count();
    }

    /**
     * Rechnet die Abrechnung durch.
     *
     * @return array{units: array, segments: array, lines: array, leases: array, owner: array, warnings: string[], totals: array, settings: array}
     */
    public static function compute(NkSettlement $s): array
    {
        $from = $s->period_from->format('Y-m-d');
        $to = $s->period_to->format('Y-m-d');
        $days = (int) $s->period_from->diff($s->period_to)->days + 1;
        $settings = NkSetting::forProperty((int) $s->property_id);
        $keys = NkCostKey::query()->where('property_id', $s->property_id)->get()->keyBy('cost_key');

        $unitModels = Unit::query()->where('property_id', $s->property_id)->orderBy('label')->get();
        $units = [];
        $segments = [];
        $warnings = [];

        foreach ($unitModels as $u) {
            $units[$u->id] = [
                'label' => $u->label,
                'area' => (float) ($u->area_sqm ?? 0),
                'commercial' => in_array($u->usage_type, ['gewerbe_option', 'gewerbe_ohne_option'], true),
                'consumption' => self::consumption($u->id, $from, $to, $warnings),
            ];
            foreach (self::segmentsForUnit($u, $from, $to) as $seg) {
                $segments[] = $seg;
            }
        }

        // Angaben nach § 6a Abs. 3 HeizkostenV: Verbrauch der Einheit im Vorjahreszeitraum und Durchschnitt im Haus
        if ($settings->heating_mode === 'zaehler') {
            $prevFrom = $s->period_from->copy()->subYear()->format('Y-m-d');
            $prevTo = $s->period_to->copy()->subYear()->format('Y-m-d');
            $ignore = [];
            foreach ($units as $uid => &$u) {
                $u['consumption_prev'] = self::consumption($uid, $prevFrom, $prevTo, $ignore);
                $kinds = Meter::query()->where('unit_id', $uid)->whereIn('kind', ['waerme', 'hkv'])->pluck('kind')->unique()->all();
                $u['heat_unit'] = $kinds === ['waerme'] ? 'kWh' : 'Einheiten';
            }
            unset($u);
        }

        $lines = [];
        $costRows = NkSettlementCost::query()->where('nk_settlement_id', $s->id)->where('included', true)->get();
        $byKey = [];
        foreach ($costRows as $c) {
            $type = $keys->get($c->cost_key)?->key_type ?? NkCostKey::defaultType($c->cost_key, $settings->heating_mode);
            if ($type === 'nicht') {
                continue;
            }
            $unit = $c->unit_id && isset($units[$c->unit_id]) ? (int) $c->unit_id : null;
            // Gleiche Kostenart und gleicher Schlüssel werden zu einer Zeile zusammengefasst
            $k = $c->cost_key.'|'.($unit ?? '');
            $byKey[$k] ??= [
                'id' => $k,
                'cost_key' => $c->cost_key,
                'label' => Kostenarten::label($c->cost_key).($unit ? ' ('.$units[$unit]['label'].')' : ''),
                'amount' => 0.0,
                'key_type' => $type,
                'commercial_percent' => $keys->get($c->cost_key)?->commercial_percent,
                'unit' => $unit,
            ];
            $byKey[$k]['amount'] += (float) $c->amount;
        }
        $lines = array_values($byKey);

        // CO2-Kosten: Anteil des Vermieters wird wie die Heizkosten verteilt und abgezogen (Paket 26)
        $co2 = self::co2Info($s, $settings, $units, $days, $lines, $warnings);
        if ($co2 !== null && ! $co2['missing'] && $co2['landlord_cents'] > 0) {
            $heatType = $keys->get('heizung')?->key_type ?? NkCostKey::defaultType('heizung', $settings->heating_mode);
            if ($heatType !== 'nicht') {
                $lines[] = [
                    'id' => 'co2',
                    'cost_key' => 'heizung',
                    'label' => 'abzüglich CO2-Anteil Vermieter ('.Co2Split::pct($co2['landlord_pct']).')',
                    'amount' => -$co2['landlord_cents'] / 100,
                    'key_type' => $heatType,
                    'commercial_percent' => $keys->get('heizung')?->commercial_percent,
                    'unit' => null,
                ];
            }
        }
        foreach (self::meterWarnings($s) as $w) {
            $warnings[] = $w;
        }

        $calc = NkCalculator::calculate($days, $units, $segments, $lines, max(50, min(70, (int) $settings->consumption_share)));
        $warnings = array_values(array_unique(array_merge($warnings, $calc['warnings'])));

        // Ergebnis je Mietvertrag
        $leases = [];
        $ownerCents = 0;
        $dummy = [];
        foreach ($calc['lines'] as $li => $line) {
            foreach ($line['alloc'] as $segIndex => $cents) {
                if ($segIndex === 'owner') {
                    $ownerCents += $cents;

                    continue;
                }
                $seg = $segments[$segIndex];
                if ($seg['lease'] === null) {
                    $ownerCents += $cents;

                    continue;
                }
                $id = $seg['lease'];
                $leases[$id] ??= ['lease_id' => $id, 'label' => $seg['label'], 'unit' => $units[$seg['unit']]['label'], 'unit_id' => $seg['unit'],
                    'from' => $seg['from'], 'to' => $seg['to'], 'days' => 0, 'person_days' => null, 'lines' => [], 'cost_cents' => 0, 'heat_cents' => 0, 'c35a' => []];
                $leases[$id]['from'] = min($leases[$id]['from'], $seg['from']);
                $leases[$id]['to'] = max($leases[$id]['to'], $seg['to']);
                $leases[$id]['lines'][$li] = ($leases[$id]['lines'][$li] ?? ['cents' => 0, 'base' => 0.0]);
                $leases[$id]['lines'][$li]['cents'] += $cents;
                $leases[$id]['lines'][$li]['base'] += NkCalculator::weight($line['basis'], $units[$seg['unit']], $seg, $days, $units, $segments, $dummy, '');
                $leases[$id]['cost_cents'] += $cents;
                if (in_array($line['cost_key'], ['heizung', 'warmwasser'], true)) {
                    $leases[$id]['heat_cents'] += $cents;
                }
                if (in_array($line['cost_key'], self::SECTION_35A, true)) {
                    $leases[$id]['c35a'][$line['cost_key']] = ($leases[$id]['c35a'][$line['cost_key']] ?? 0) + $cents;
                }
            }
        }
        foreach ($segments as $seg) {
            if ($seg['lease'] !== null && isset($leases[$seg['lease']])) {
                $leases[$seg['lease']]['days'] += $seg['days'];
            }
        }

        // Vorauszahlungen (Soll laut Mietvertrag) im Zeitraum
        $leaseModels = Lease::query()->whereIn('id', array_keys($leases))->with(['tenant', 'rents'])->get()->keyBy('id');
        foreach ($leases as $id => &$l) {
            $l['prepaid_cents'] = self::prepaidCents($id, $from, $to);
            $l['result_cents'] = $l['cost_cents'] - $l['prepaid_cents'];
            $lease = $leaseModels->get($id);
            $l['vat_rate'] = (float) ($lease?->vat_rate ?? 0);
            $l['tenant'] = $lease?->tenant?->display_name ?? $l['label'];
            // Vorschlag neue monatliche Vorauszahlung (auf volle Euro aufgerundet, bezogen auf 12 Monate Mietdauer)
            $months = max(1.0, $l['days'] / ($days / 12));
            $heat = (int) ceil($l['heat_cents'] / $months / 100);
            $other = (int) ceil(($l['cost_cents'] - $l['heat_cents']) / $months / 100);
            $current = $lease?->currentRent();
            $l['suggest'] = ['operating' => $other, 'heating' => $heat,
                'current_operating' => (float) ($current?->prepay_operating ?? 0), 'current_heating' => (float) ($current?->prepay_heating ?? 0),
                'active' => $lease && ($lease->end_date === null || $lease->end_date->format('Y-m-d') > date('Y-m-d'))];
        }
        unset($l);

        // CO2-Angaben je Mieter: Anteil an den CO2-Kosten (wie die Heizkosten) und abgezogener Vermieteranteil
        if ($co2 !== null && ! $co2['missing']) {
            $heatTotal = 0;
            $co2Index = [];
            foreach ($calc['lines'] as $li => $line) {
                if ($line['id'] === 'co2') {
                    $co2Index[] = $li;
                } elseif ($line['cost_key'] === 'heizung') {
                    $heatTotal += $line['cents'];
                }
            }
            foreach ($leases as &$l) {
                $mine = 0;
                $credit = 0;
                foreach ($l['lines'] as $li => $v) {
                    if (in_array($li, $co2Index, true)) {
                        $credit -= $v['cents'];
                    } elseif ($calc['lines'][$li]['cost_key'] === 'heizung') {
                        $mine += $v['cents'];
                    }
                }
                $share = $heatTotal > 0 ? (int) round($co2['cost_cents'] * $mine / $heatTotal) : 0;
                $l['co2'] = ['share_cents' => $share, 'landlord_cents' => $credit, 'tenant_cents' => $share - $credit];
            }
            unset($l);
        }

        $totalCents = array_sum(array_column($calc['lines'], 'cents'));

        return [
            'days' => $days,
            'units' => $units,
            'segments' => $segments,
            'lines' => $calc['lines'],
            'leases' => $leases,
            'owner' => ['cents' => $ownerCents],
            'warnings' => $warnings,
            'totals' => [
                'cost_cents' => $totalCents,
                'tenant_cents' => array_sum(array_column($leases, 'cost_cents')),
                'prepaid_cents' => array_sum(array_column($leases, 'prepaid_cents')),
            ],
            'settings' => ['heating_mode' => $settings->heating_mode, 'share' => (int) $settings->consumption_share, 'fuel' => $settings->fuel],
            'co2' => $co2,
        ];
    }

    /**
     * CO2-Aufteilung für die Abrechnung. null, wenn sie nicht in Frage kommt (vor 2023, Mieter zahlt direkt, keine CO2-Kosten).
     * 'missing' = true, wenn CO2-Menge oder -Kosten noch fehlen.
     */
    public static function co2Info(NkSettlement $s, NkSetting $settings, array $units, int $days, array $lines, array &$warnings): ?array
    {
        if ($s->period_from->format('Y-m-d') < Co2Split::FROM || $settings->heating_mode === 'direkt') {
            return null;
        }
        $hasHeat = collect($lines)->contains(fn ($l) => in_array($l['cost_key'], ['heizung', 'warmwasser'], true));
        if ($settings->fuel === null || $settings->fuel === '') {
            if ($hasHeat) {
                $warnings[] = 'Bitte in den Nebenkosten-Einstellungen des Objekts den Brennstoff angeben (für die CO2-Kostenaufteilung).';
            }

            return null;
        }
        if (! $settings->hasCo2Costs()) {
            return null;
        }

        $residential = 0.0;
        $commercial = 0.0;
        foreach ($units as $u) {
            $u['commercial'] ? $commercial += (float) $u['area'] : $residential += (float) $u['area'];
        }
        $use = $settings->building_use ?: ($commercial > $residential ? 'nichtwohn' : 'wohn');
        $area = $s->co2_area !== null && (float) $s->co2_area > 0 ? (float) $s->co2_area : $residential + $commercial;

        if ($s->co2_kg === null || $s->co2_cost === null) {
            $warnings[] = 'CO2-Kosten fehlen ('.$settings->fuelLabel().'): Bitte CO2-Menge und CO2-Kosten laut Rechnung des Versorgers eintragen. Ohne Aufteilung darf jeder Mieter seinen Anteil an den Heizkosten um 3 % kürzen (§ 7 Abs. 4 CO2KostAufG).';

            return ['missing' => true, 'fuel' => $settings->fuelLabel(), 'use' => $use, 'area' => $area];
        }
        if ($area <= 0) {
            $warnings[] = 'Für die CO2-Aufteilung fehlt die Wohnfläche (Flächen der Einheiten oder Fläche bei den CO2-Angaben eintragen).';

            return ['missing' => true, 'fuel' => $settings->fuelLabel(), 'use' => $use, 'area' => $area];
        }
        if (! $hasHeat) {
            $warnings[] = 'CO2-Kosten sind eingetragen, aber es gibt keine Heizkosten in dieser Abrechnung.';
        }

        $split = Co2Split::calculate((float) $s->co2_kg, (float) $s->co2_cost, $area, $days, $use, $settings->co2_restriction ?: 'keine');

        return $split + ['missing' => false, 'fuel' => $settings->fuelLabel(), 'restriction_note' => $settings->co2_restriction_note, 'area_custom' => $s->co2_area !== null];
    }

    /** Hinweise zu Zählern: Fernablesbarkeit (ab 2027) und Eichfrist. */
    public static function meterWarnings(NkSettlement $s): array
    {
        $out = [];
        $settings = NkSetting::forProperty((int) $s->property_id);
        if ($settings->heating_mode !== 'zaehler') {
            return $out;
        }
        $meters = Meter::query()->where('property_id', $s->property_id)->where('active', true)->get();
        $year = (int) $s->period_to->format('Y');
        $notRemote = $meters->filter(fn ($m) => $m->isHeat() && ! $m->remote)->count();
        if ($notRemote > 0 && $s->period_to->format('Y-m-d') >= '2027-01-01') {
            $out[] = $notRemote.' Zähler für Heizung/Warmwasser sind nicht fernablesbar. Seit 2027 dürfen Mieter ihren Anteil an den Heizkosten deshalb um 3 % kürzen (§ 12 HeizkostenV).';
        }
        $expired = $meters->filter(fn ($m) => $m->calibrated_until !== null && (int) $m->calibrated_until < $year)->count();
        if ($expired > 0) {
            $out[] = $expired.' Zähler mit abgelaufener Eichfrist im Abrechnungszeitraum. Werte nicht geeichter Zähler können vom Mieter angezweifelt werden – Zähler tauschen lassen.';
        }

        return $out;
    }

    /** Zeitabschnitte einer Einheit: Mietverträge und Lücken (Leerstand). Montage-Wohnungen zählen zum Eigentümer. */
    public static function segmentsForUnit(Unit $u, string $from, string $to): array
    {
        $leases = Lease::query()->where('unit_id', $u->id)
            ->where('start_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
            ->with('tenant')->orderBy('start_date')->get();

        $segs = [];
        $cursor = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        foreach ($leases as $l) {
            $ls = max($cursor, new DateTimeImmutable($l->start_date->format('Y-m-d')));
            $le = $l->end_date ? min($end, new DateTimeImmutable($l->end_date->format('Y-m-d'))) : $end;
            if ($ls > $cursor) {
                $segs[] = self::seg($u->id, null, 'Leerstand', $cursor, $ls->modify('-1 day'), null);
            }
            if ($le >= $ls) {
                $montage = $l->lease_type === 'montage';
                $segs[] = self::seg($u->id, $montage ? null : (int) $l->id, $montage ? 'Montage-Wohnung ('.$l->tenant?->display_name.')' : (string) $l->tenant?->display_name, $ls, $le,
                    $montage ? null : self::personDays((int) $l->id, $ls, $le));
                $cursor = $le->modify('+1 day');
            }
        }
        if ($cursor <= $end) {
            $segs[] = self::seg($u->id, null, 'Leerstand', $cursor, $end, null);
        }

        return $segs;
    }

    private static function seg(int $unit, ?int $lease, string $label, DateTimeImmutable $f, DateTimeImmutable $t, ?float $personDays): array
    {
        return ['unit' => $unit, 'lease' => $lease, 'label' => $label, 'from' => $f->format('Y-m-d'), 'to' => $t->format('Y-m-d'),
            'days' => (int) $f->diff($t)->days + 1, 'person_days' => $personDays];
    }

    /** Personentage im Abschnitt, null wenn keine Personenzahl erfasst ist. */
    public static function personDays(int $leaseId, DateTimeImmutable $f, DateTimeImmutable $t): ?float
    {
        $rows = LeasePerson::query()->where('lease_id', $leaseId)->orderBy('valid_from')->get();
        if ($rows->isEmpty()) {
            return null;
        }
        $sum = 0.0;
        for ($d = $f; $d <= $t; $d = $d->modify('+1 day')) {
            $day = $d->format('Y-m-d');
            $p = $rows->filter(fn ($r) => $r->valid_from->format('Y-m-d') <= $day)->last() ?? $rows->first();
            $sum += (int) $p->persons;
        }

        return $sum;
    }

    /**
     * Verbrauch einer Einheit im Zeitraum je Art (wasser = Kalt- und Warmwasser, warmwasser, heizung = Wärme und Heizkostenverteiler).
     * null, wenn es keine Zähler gibt oder ein Zählerstand fehlt.
     */
    public static function consumption(int $unitId, string $from, string $to, array &$warnings): array
    {
        $meters = Meter::query()->where('unit_id', $unitId)->with('readings')->get();
        $sums = ['wasser' => null, 'warmwasser' => null, 'heizung' => null];
        $missing = ['wasser' => false, 'warmwasser' => false, 'heizung' => false];

        foreach ($meters as $m) {
            $start = self::readingNear($m, $from);
            $end = self::readingNear($m, $to);
            $kinds = match ($m->kind) {
                'wasser' => ['wasser'],
                'warmwasser' => ['wasser', 'warmwasser'],
                default => ['heizung'],
            };
            if ($start === null || $end === null || $end < $start) {
                foreach ($kinds as $k) {
                    $missing[$k] = true;
                }

                continue;
            }
            $value = ($end - $start) * (float) $m->factor;
            foreach ($kinds as $k) {
                $sums[$k] = ($sums[$k] ?? 0.0) + $value;
            }
        }
        foreach ($missing as $k => $isMissing) {
            if ($isMissing) {
                $sums[$k] = null;
            }
        }

        return $sums;
    }

    /** Zählerstand möglichst nah am Stichtag (höchstens 31 Tage daneben). Für den Beginn zählt auch der Stand vom Vortag. */
    public static function readingNear(Meter $m, string $date): ?float
    {
        $best = null;
        $bestDiff = null;
        $target = new DateTimeImmutable($date);
        foreach ($m->readings as $r) {
            $diff = abs((int) $target->diff(new DateTimeImmutable($r->read_on->format('Y-m-d')))->days);
            if ($diff <= self::READING_TOLERANCE && ($bestDiff === null || $diff < $bestDiff)) {
                $best = (float) $r->value;
                $bestDiff = $diff;
            }
        }

        return $best;
    }

    /** Vorauszahlungen (Nebenkosten und Heizkosten, brutto) laut Soll im Zeitraum. */
    public static function prepaidCents(int $leaseId, string $from, string $to): int
    {
        $sum = 0;
        foreach (RentCharge::query()->where('lease_id', $leaseId)->where('kind', 'miete')->whereBetween('period_from', [$from, $to])->get() as $c) {
            $net = (int) round(((float) $c->prepay_operating + (float) $c->prepay_heating) * 100);
            $sum += $net + (int) round($net * (float) $c->vat_rate / 100);
        }

        return $sum;
    }

    /** Abschließen: Ergebnis festhalten, Nachzahlungen als offene Posten, Guthaben als Gutschrift ins Mieterkonto. */
    public static function finalize(NkSettlement $s, string $issuedOn, User $user): array
    {
        if ($s->isFinal()) {
            throw new RuntimeException('Die Abrechnung ist schon abgeschlossen.');
        }
        $result = self::compute($s);
        $due = (new DateTimeImmutable($issuedOn))->modify('+30 days')->format('Y-m-d');
        $period = $s->period_to->copy()->addDay()->startOfMonth()->format('Y-m-d');
        $label = 'Nebenkostenabrechnung '.$s->periodLabel();

        DB::transaction(function () use ($s, $result, $issuedOn, $due, $period, $label, $user) {
            foreach ($result['leases'] as $id => $l) {
                $lease = Lease::query()->find($id);
                if ($lease === null) {
                    continue;
                }
                $cents = $l['result_cents'];
                if ($cents > 0) {
                    $heatShare = $l['cost_cents'] > 0 ? (int) round($cents * $l['heat_cents'] / $l['cost_cents']) : 0;
                    $heatShare = max(0, min($cents, $heatShare));
                    RentCharge::create([
                        'lease_id' => $lease->id,
                        'owner_id' => $lease->owner_id,
                        'kind' => 'nk',
                        'nk_settlement_id' => $s->id,
                        'label' => $label,
                        'period' => $period,
                        'period_from' => $s->period_from->format('Y-m-d'),
                        'period_to' => $s->period_to->format('Y-m-d'),
                        'due_date' => $due,
                        'prepay_operating' => number_format(($cents - $heatShare) / 100, 2, '.', ''),
                        'prepay_heating' => number_format($heatShare / 100, 2, '.', ''),
                        'net_total' => number_format($cents / 100, 2, '.', ''),
                        'vat_rate' => 0,
                        'vat_amount' => 0,
                        'total' => number_format($cents / 100, 2, '.', ''),
                        'paid_amount' => 0,
                    ]);
                } elseif ($cents < 0) {
                    RentPayment::create([
                        'lease_id' => $lease->id,
                        'owner_id' => $lease->owner_id,
                        'paid_on' => $issuedOn,
                        'amount' => number_format(-$cents / 100, 2, '.', ''),
                        'method' => 'sonstiges',
                        'source' => 'nk',
                        'nk_settlement_id' => $s->id,
                        'note' => 'Guthaben aus der '.$label,
                        'created_by' => $user->id,
                    ]);
                }
            }

            $s->update([
                'status' => 'abgeschlossen',
                'result' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'issued_on' => $issuedOn,
                'finalized_by' => $user->id,
                'finalized_at' => now(),
            ]);
            Audit::log('nk.finalized', $s, $label.' abgeschlossen: '.$s->property?->name);
        });

        foreach (array_keys($result['leases']) as $id) {
            $lease = Lease::query()->find($id);
            if ($lease) {
                RentLedger::sync($lease);
            }
        }

        return $result;
    }

    /** Wieder öffnen: Buchungen im Mieterkonto entfernen, Abrechnung wird wieder Entwurf. */
    public static function reopen(NkSettlement $s): void
    {
        $leaseIds = [];
        DB::transaction(function () use ($s, &$leaseIds) {
            $charges = RentCharge::query()->where('nk_settlement_id', $s->id)->get();
            $payments = RentPayment::withTrashed()->where('nk_settlement_id', $s->id)->get();
            $leaseIds = array_unique(array_merge($charges->pluck('lease_id')->all(), $payments->pluck('lease_id')->all()));
            DB::table('rent_allocations')->whereIn('rent_charge_id', $charges->pluck('id'))->delete();
            DB::table('rent_allocations')->whereIn('rent_payment_id', $payments->pluck('id'))->delete();
            RentCharge::query()->where('nk_settlement_id', $s->id)->delete();
            RentPayment::withTrashed()->where('nk_settlement_id', $s->id)->forceDelete();
            $s->update(['status' => 'entwurf', 'result' => null, 'finalized_by' => null, 'finalized_at' => null]);
            Audit::log('nk.reopened', $s, 'Nebenkostenabrechnung wieder geöffnet: '.$s->property?->name.', '.$s->periodLabel());
        });
        foreach ($leaseIds as $id) {
            $lease = Lease::query()->find($id);
            if ($lease) {
                RentLedger::sync($lease);
            }
        }
    }
}
