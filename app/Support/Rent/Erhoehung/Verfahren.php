<?php

namespace App\Support\Rent\Erhoehung;

use App\Models\Lease;
use App\Models\LeaseRent;
use App\Models\RentIncrease;
use App\Models\User;
use App\Support\Audit;
use App\Support\Money;
use App\Support\Rent\Mieten;
use App\Support\Rent\RentLedger;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Mieterhöhungsverfahren: berechnen, als Entwurf speichern, erklären, wirksam machen (Paket 27). */
class Verfahren
{
    public const BASIS_TYPES = [
        'mietspiegel' => 'Mietspiegel',
        'qualifiziert' => 'Qualifizierter Mietspiegel',
        'mietdatenbank' => 'Auskunft aus einer Mietdatenbank',
        'gutachten' => 'Gutachten eines Sachverständigen',
        'vergleichswohnungen' => 'Mindestens drei Vergleichswohnungen',
    ];

    /** Zuordnung zur Art der Mietänderung im Mietstand. */
    private const RENT_REASON = [
        'vergleich' => 'erhoehung',
        'vereinbarung' => 'erhoehung',
        'modernisierung' => 'modernisierung',
        'index' => 'index',
    ];

    // ------------------------------------------------------------------ Daten des Vertrags

    /** Mietverlauf in der Form, die Regeln erwartet (Kaltmiete in Cent). */
    public static function rentsOf(Lease $lease): array
    {
        $rows = $lease->relationLoaded('rents') ? $lease->rents : $lease->rents()->get();

        return $rows->map(fn (LeaseRent $r) => [
            'from' => $r->valid_from->format('Y-m-d'),
            'reason' => (string) $r->reason,
            'cold' => Regeln::cents($r->rent_cold),
        ])->sortBy('from')->values()->all();
    }

    public static function areaOf(Lease $lease): ?float
    {
        $a = $lease->unit?->area_sqm;

        return $a !== null && (float) $a > 0 ? (float) $a : null;
    }

    /** Zuletzt gewählte Kappungsgrenze für Wohnungen im selben Objekt, sonst 20 %. */
    public static function defaultCap(Lease $lease): int
    {
        $propertyId = $lease->unit?->property_id;
        if ($propertyId) {
            $last = RentIncrease::query()->where('kind', 'vergleich')
                ->whereHas('lease.unit', fn ($q) => $q->where('property_id', $propertyId))
                ->orderByDesc('id')->first();
            $cap = (int) ($last?->datum('cap_percent') ?? 0);
            if (in_array($cap, [Regeln::CAP_NORMAL, Regeln::CAP_TIGHT], true)) {
                return $cap;
            }
        }

        return Regeln::CAP_NORMAL;
    }

    public static function indexMode(): string
    {
        $mode = (string) Settings::get('rent_index_cap_mode', 'keine');

        return array_key_exists($mode, Regeln::INDEX_CAP_MODES) ? $mode : 'keine';
    }

    // ------------------------------------------------------------------ Berechnen

    /**
     * Prüft und berechnet ein Verfahren aus den Formularwerten, ohne etwas zu speichern.
     *
     * @return array{kind: string, ok: bool, checks: array, old_cold: int, new_cold: ?int, effective: ?string, timeline: array, area: ?float, data: array, max_allowed: ?int}
     */
    public static function compute(Lease $lease, string $kind, array $in): array
    {
        $lease->loadMissing(['unit.property', 'tenant', 'rents']);
        $rents = self::rentsOf($lease);
        $today = date('Y-m-d');
        $notice = self::date($in['notice_on'] ?? null) ?? $today;
        $area = self::areaOf($lease);
        $oldCold = Regeln::coldOn($rents, $notice);
        $note = trim((string) ($in['note'] ?? ''));

        $checks = [];
        $data = ['kind' => $kind];
        $new = null;
        $effective = null;
        $timeline = [];
        $maxAllowed = null;

        switch ($kind) {
            case 'vergleich':
                $cap = (int) ($in['cap_percent'] ?? Regeln::CAP_NORMAL);
                $cap = in_array($cap, [Regeln::CAP_NORMAL, Regeln::CAP_TIGHT], true) ? $cap : Regeln::CAP_NORMAL;
                $newStr = Money::parse($in['new_cold'] ?? null);
                $cmpStr = Money::parse($in['comparable_sqm'] ?? null);
                $res = Regeln::vergleich([
                    'lease_type' => (string) $lease->lease_type,
                    'adjustment_type' => (string) $lease->adjustment_type,
                    'rents' => $rents,
                    'notice_on' => $notice,
                    'area_sqm' => $area,
                    'cap_percent' => $cap,
                    'new_cold' => $newStr !== null ? Regeln::cents($newStr) : 0,
                    'comparable_sqm' => $cmpStr !== null ? Regeln::cents($cmpStr) : null,
                    'lease_end' => $lease->end_date?->format('Y-m-d'),
                ]);
                $checks = $res['checks'];
                if ($newStr === null) {
                    $checks[] = ['fehler', 'Bitte die neue Kaltmiete eintragen.'];
                }
                $basis = (string) ($in['basis_type'] ?? 'mietspiegel');
                $basis = array_key_exists($basis, self::BASIS_TYPES) ? $basis : 'mietspiegel';
                $source = trim((string) ($in['source_name'] ?? ''));
                if ($source === '') {
                    $checks[] = ['fehler', 'Bitte die Begründung angeben: Name des Mietspiegels, des Gutachtens oder der Datenbank. Ohne Begründung ist das Verlangen unwirksam (§ 558a BGB).'];
                }
                if ($basis === 'vergleichswohnungen' && trim((string) ($in['comparables'] ?? '')) === '') {
                    $checks[] = ['fehler', 'Bitte die drei Vergleichswohnungen beschreiben (Anschrift, Größe, Miete).'];
                }
                $new = $newStr !== null ? Regeln::cents($newStr) : null;
                $effective = $res['effective'];
                $timeline = $res['timeline'];
                $maxAllowed = $res['max_allowed'];
                $data += [
                    'cap_percent' => $cap,
                    'comparable_sqm' => $cmpStr,
                    'basis_type' => $basis,
                    'source_name' => $source,
                    'source_detail' => trim((string) ($in['source_detail'] ?? '')),
                    'range_from' => Money::parse($in['range_from'] ?? null),
                    'range_to' => Money::parse($in['range_to'] ?? null),
                    'comparables' => trim((string) ($in['comparables'] ?? '')),
                    'cap' => $res['cap'],
                    'last_change' => $res['last_change'],
                    'earliest_notice' => $res['earliest_notice'],
                    'max_by_comparable' => $res['max_by_comparable'],
                ];
                break;

            case 'modernisierung':
                $costsStr = Money::parse($in['costs'] ?? null);
                $subStr = Money::parse($in['subsidies'] ?? null);
                $upkeep = (float) str_replace(',', '.', (string) ($in['upkeep_percent'] ?? '0'));
                $heatingType = in_array((string) ($in['heating_type'] ?? ''), ['waermepumpe', 'erneuerbar', 'fossil'], true) ? (string) $in['heating_type'] : '';
                $pumpProof = in_array((string) ($in['pump_proof'] ?? ''), ['nachweis', 'ausnahme'], true) ? (string) $in['pump_proof'] : '';
                $heating = ! empty($in['heating']) || $heatingType !== '';
                $simplified = ! empty($in['simplified']);
                $delayed = ! empty($in['delayed']);
                $prior = self::priorModernisation($lease, $notice);
                $res = Regeln::modernisierung([
                    'lease_type' => (string) $lease->lease_type,
                    'notice_on' => $notice,
                    'area_sqm' => $area,
                    'old_cold' => $oldCold,
                    'costs' => $costsStr !== null ? Regeln::cents($costsStr) : 0,
                    'upkeep_percent' => $upkeep,
                    'subsidies' => $subStr !== null ? Regeln::cents($subStr) : 0,
                    'heating' => $heating,
                    'heating_type' => $heatingType,
                    'pump_proof' => $pumpProof,
                    'simplified' => $simplified,
                    'delayed' => $delayed,
                    'prior_monthly' => $prior['all'],
                    'prior_heating_monthly' => $prior['heating'],
                ]);
                $checks = $res['checks'];
                $measure = trim((string) ($in['measure'] ?? ''));
                if ($measure === '') {
                    $checks[] = ['fehler', 'Bitte die Modernisierungsmaßnahme beschreiben. Sie muss in der Erklärung genannt werden.'];
                }
                $new = $res['allowed'] > 0 ? $res['new_cold'] : null;
                $effective = $res['effective'];
                $timeline = $res['timeline'];
                $data += [
                    'measure' => $measure,
                    'costs' => $costsStr,
                    'upkeep_percent' => $res['upkeep_percent'],
                    'subsidies' => $subStr ?? '0.00',
                    'heating' => $heating,
                    'heating_type' => $heatingType,
                    'pump_proof' => $pumpProof,
                    'simplified' => $simplified,
                    'delayed' => $delayed,
                    'announced_on' => self::date($in['announced_on'] ?? null),
                    'completed_on' => self::date($in['completed_on'] ?? null),
                    'calc' => array_diff_key($res, ['checks' => 1, 'ok' => 1, 'timeline' => 1]),
                    'prior_monthly' => $prior['all'],
                    'prior_heating_monthly' => $prior['heating'],
                ];
                break;

            case 'index':
                $newIndexStr = Money::parse($in['new_index'] ?? null);
                $indexMonth = preg_match('/^\d{4}-\d{2}$/', (string) ($in['index_month'] ?? '')) ? (string) $in['index_month'] : null;
                $mode = self::indexMode();
                $last = Regeln::lastChange($rents);
                $res = Regeln::index([
                    'lease_type' => (string) $lease->lease_type,
                    'adjustment_type' => (string) $lease->adjustment_type,
                    'base_index' => $lease->index_base_value !== null ? (float) $lease->index_base_value : null,
                    'new_index' => $newIndexStr !== null ? (float) $newIndexStr : 0.0,
                    'old_cold' => $oldCold,
                    'last_change_from' => $last['from'] ?? null,
                    'notice_on' => $notice,
                    'mode' => $mode,
                ]);
                $checks = $res['checks'];
                if ($newIndexStr === null || (float) $newIndexStr <= 0) {
                    $checks[] = ['fehler', 'Bitte den neuen Indexwert eintragen (Verbraucherpreisindex).'];
                }
                if ($indexMonth === null) {
                    $checks[] = ['fehler', 'Bitte den Monat des neuen Indexwerts angeben.'];
                }
                $new = $newIndexStr !== null && (float) $newIndexStr > 0 ? $res['new_cold'] : null;
                $effective = $res['effective'];
                $timeline = $res['timeline'];
                $data += [
                    'new_index' => $newIndexStr,
                    'index_month' => $indexMonth,
                    'base_index' => $lease->index_base_value !== null ? (string) $lease->index_base_value : null,
                    'base_month' => $lease->index_base_month?->format('Y-m'),
                    'mode' => $mode,
                    'percent' => $res['percent'],
                    'applied_percent' => $res['applied_percent'],
                ];
                break;

            case 'staffel':
                $rentId = (int) ($in['lease_rent_id'] ?? 0);
                $row = $lease->rents->firstWhere('id', $rentId);
                if ($row === null || $row->reason !== 'staffel') {
                    $checks[] = ['fehler', 'Bitte die Staffel auswählen, über die informiert werden soll.'];
                } else {
                    $prev = collect($rents)->filter(fn ($r) => $r['from'] < $row->valid_from->format('Y-m-d'))->last();
                    $oldCold = $prev['cold'] ?? $oldCold;
                    $new = Regeln::cents($row->rent_cold);
                    $effective = $row->valid_from->format('Y-m-d');
                    $data += ['lease_rent_id' => $row->id];
                    $checks[] = ['ok', 'Die Staffel ist im Mietvertrag vereinbart und gilt ohne weitere Erklärung. Die Mitteilung ist freiwillig, aber üblich.'];
                    if ($effective <= $notice) {
                        $checks[] = ['warn', 'Die Staffel gilt schon seit '.Regeln::de($effective).'.'];
                    }
                }
                break;

            case 'vereinbarung':
                $newStr = Money::parse($in['new_cold'] ?? null);
                $eff = self::date($in['effective_from'] ?? null);
                $new = $newStr !== null ? Regeln::cents($newStr) : null;
                $effective = $eff;
                if ($newStr === null) {
                    $checks[] = ['fehler', 'Bitte die neue Kaltmiete eintragen.'];
                } elseif ($new <= $oldCold) {
                    $checks[] = ['warn', 'Die neue Kaltmiete ist nicht höher als die bisherige ('.Regeln::eur($oldCold).').'];
                }
                if ($eff === null) {
                    $checks[] = ['fehler', 'Bitte angeben, ab wann die neue Miete gelten soll.'];
                } elseif (date('d', strtotime($eff)) !== '01') {
                    $checks[] = ['fehler', 'Die neue Miete muss zum Ersten eines Monats beginnen, damit sich die Soll-Mieten sauber berechnen lassen.'];
                }
                if ($lease->lease_type === 'wohnen' && $lease->adjustment_type !== 'fest') {
                    $checks[] = ['warn', 'Der Vertrag ist als '.$lease->adjustmentLabel().' angelegt. Eine zusätzliche Vereinbarung ist erlaubt, aber nur mit Zustimmung des Mieters.'];
                }
                $checks[] = ['ok', 'Eine Vereinbarung braucht die Zustimmung des Mieters; die gesetzlichen Grenzen der Vergleichsmiete gelten dafür nicht. Bei Wohnraum ist die Mietpreisbremse nur bei Neuvermietung relevant.'];
                $timeline = ['effective' => $eff];
                break;

            default:
                throw new RuntimeException('Unbekannte Art der Mieterhöhung.');
        }

        if ($effective !== null && $lease->end_date !== null && $lease->end_date->format('Y-m-d') < $effective && $kind !== 'vergleich') {
            $checks[] = ['warn', 'Der Vertrag endet am '.$lease->end_date->format('d.m.Y').', vor dem Wirksamwerden der Erhöhung.'];
        }

        $data['notice_on'] = $notice;
        $data['note'] = $note;
        $data['checks'] = $checks;

        return [
            'kind' => $kind,
            'ok' => Regeln::noErrors($checks) && $new !== null && $effective !== null,
            'checks' => $checks,
            'old_cold' => $oldCold,
            'new_cold' => $new,
            'effective' => $effective,
            'timeline' => $timeline,
            'area' => $area,
            'data' => $data,
            'max_allowed' => $maxAllowed,
            'notice_on' => $notice,
            'note' => $note,
        ];
    }

    /**
     * Bisherige Modernisierungszuschläge der letzten sechs Jahre (Euro-Cent je Monat), insgesamt und davon für Heizungstausch.
     *
     * @return array{all: int, heating: int}
     */
    public static function priorModernisation(Lease $lease, string $on): array
    {
        $rents = self::rentsOf($lease);
        $from = Regeln::addMonths($on, -72);
        $all = Regeln::modernisationDelta($rents, $from, '9999-12-31');
        $heating = 0;
        foreach (RentIncrease::query()->where('lease_id', $lease->id)->where('kind', 'modernisierung')->where('status', 'wirksam')->get() as $inc) {
            if ($inc->datum('heating') && $inc->effective_from->format('Y-m-d') > $from) {
                $heating += max(0, Regeln::cents($inc->new_cold) - Regeln::cents($inc->old_cold));
            }
        }

        return ['all' => $all, 'heating' => $heating];
    }

    // ------------------------------------------------------------------ Speichern und Statuswechsel

    /** Legt den Entwurf an. Gibt es Fehler in der Prüfung, wird nichts gespeichert. */
    public static function create(Lease $lease, string $kind, array $in, User $user): RentIncrease
    {
        $calc = self::compute($lease, $kind, $in);
        if (! $calc['ok']) {
            throw new RuntimeException('Die Prüfung hat Fehler ergeben. Bitte die rot markierten Punkte beheben.');
        }

        return DB::transaction(function () use ($lease, $kind, $calc, $user) {
            $inc = RentIncrease::create([
                'lease_id' => $lease->id,
                'owner_id' => $lease->owner_id,
                'kind' => $kind,
                'status' => 'entwurf',
                'notice_on' => $calc['notice_on'],
                'effective_from' => $calc['effective'],
                'consent_until' => $calc['timeline']['consent_until'] ?? null,
                'suit_until' => $calc['timeline']['suit_until'] ?? null,
                'old_cold' => Regeln::euro($calc['old_cold']),
                'new_cold' => Regeln::euro($calc['new_cold']),
                'area_sqm' => $calc['area'],
                'data' => $calc['data'],
                'note' => $calc['note'] !== '' ? $calc['note'] : null,
                'created_by' => $user->id,
            ]);
            Audit::log('rent_increase.created', $inc, 'Mieterhöhung (Entwurf): '.$inc->title().', '.$lease->title(), null, ['alt' => $inc->old_cold, 'neu' => $inc->new_cold, 'art' => $kind]);

            return $inc;
        });
    }

    /** Rechnet einen Entwurf mit einem neuen Zugangsdatum neu (Fristen verschieben sich). */
    public static function redate(RentIncrease $inc, string $noticeOn, User $user): RentIncrease
    {
        if ($inc->status !== 'entwurf') {
            throw new RuntimeException('Nur ein Entwurf kann neu datiert werden.');
        }
        $lease = $inc->lease()->with(['unit.property', 'tenant', 'rents'])->first();
        $in = self::inputsOf($inc);
        $in['notice_on'] = $noticeOn;
        if ($inc->kind === 'vereinbarung') {
            $in['effective_from'] = $inc->effective_from->format('Y-m-d');
        }
        $calc = self::compute($lease, $inc->kind, $in);
        if (! $calc['ok']) {
            $first = collect($calc['checks'])->firstWhere(0, 'fehler');
            throw new RuntimeException('Mit dem Datum '.Regeln::de($noticeOn).' ist die Erhöhung nicht zulässig: '.($first[1] ?? 'Prüfung fehlgeschlagen.'));
        }
        $inc->update([
            'notice_on' => $calc['notice_on'],
            'effective_from' => $calc['effective'],
            'consent_until' => $calc['timeline']['consent_until'] ?? null,
            'suit_until' => $calc['timeline']['suit_until'] ?? null,
            'old_cold' => Regeln::euro($calc['old_cold']),
            'new_cold' => Regeln::euro($calc['new_cold']),
            'data' => $calc['data'],
        ]);
        Audit::log('rent_increase.redated', $inc, 'Mieterhöhung neu datiert auf '.Regeln::de($noticeOn).': '.$lease->title());

        return $inc->refresh();
    }

    /** Die Formularwerte, aus denen ein Verfahren berechnet wurde (für Neuberechnung). */
    public static function inputsOf(RentIncrease $inc): array
    {
        $d = $inc->data ?? [];
        $in = ['notice_on' => $inc->notice_on->format('Y-m-d'), 'note' => (string) $inc->note];
        $keep = match ($inc->kind) {
            'vergleich' => ['cap_percent', 'comparable_sqm', 'basis_type', 'source_name', 'source_detail', 'range_from', 'range_to', 'comparables'],
            'modernisierung' => ['measure', 'costs', 'upkeep_percent', 'subsidies', 'heating', 'heating_type', 'pump_proof', 'simplified', 'delayed', 'announced_on', 'completed_on'],
            'index' => ['new_index', 'index_month'],
            'staffel' => ['lease_rent_id'],
            default => [],
        };
        foreach ($keep as $k) {
            $in[$k] = $d[$k] ?? null;
        }
        if ($inc->kind === 'vergleich' || $inc->kind === 'vereinbarung') {
            $in['new_cold'] = (string) $inc->new_cold;
        }

        return $in;
    }

    /** Das Schreiben ist raus: Entwurf wird zu „erklärt“. Bei Staffelmitteilungen ist damit alles erledigt. */
    public static function declare(RentIncrease $inc, User $user, ?string $noticeOn = null): RentIncrease
    {
        if ($inc->status !== 'entwurf') {
            throw new RuntimeException('Das Schreiben ist schon als erklärt vermerkt.');
        }
        if ($noticeOn !== null && $noticeOn !== $inc->notice_on->format('Y-m-d')) {
            $inc = self::redate($inc, $noticeOn, $user);
        }
        if ($inc->kind === 'staffel') {
            $inc->update(['status' => 'wirksam', 'decided_on' => $inc->notice_on, 'lease_rent_id' => $inc->datum('lease_rent_id')]);
        } else {
            $inc->update(['status' => 'erklaert']);
        }
        Audit::log('rent_increase.declared', $inc, 'Mieterhöhung erklärt (Zugang '.$inc->notice_on->format('d.m.Y').'): '.$inc->lease?->title());

        return $inc->refresh();
    }

    /** Mieter hat zugestimmt (Vergleichsmiete, Vereinbarung): Mietstand wird angelegt. */
    public static function consent(RentIncrease $inc, string $on, User $user): RentIncrease
    {
        if (! $inc->needsConsent()) {
            throw new RuntimeException('Diese Erhöhung braucht keine Zustimmung.');
        }
        if (! in_array($inc->status, ['erklaert', 'abgelehnt'], true)) {
            throw new RuntimeException('Die Zustimmung kann nur nach dem Versand des Schreibens vermerkt werden.');
        }

        return self::makeEffective($inc, $on, $user, 'Zustimmung des Mieters');
    }

    /** Einseitige Erhöhung (Modernisierung, Index) in den Mietstand übernehmen. */
    public static function adopt(RentIncrease $inc, User $user): RentIncrease
    {
        if ($inc->needsConsent() || $inc->kind === 'staffel') {
            throw new RuntimeException('Diese Erhöhung wird über die Zustimmung des Mieters übernommen.');
        }
        if ($inc->status !== 'erklaert') {
            throw new RuntimeException('Erst nach dem Versand des Schreibens kann die Erhöhung übernommen werden.');
        }

        return self::makeEffective($inc, date('Y-m-d'), $user, 'Übernahme in den Mietstand');
    }

    private static function makeEffective(RentIncrease $inc, string $on, User $user, string $what): RentIncrease
    {
        $lease = $inc->lease()->with(['unit.property', 'tenant', 'rents'])->first();
        $from = $inc->effective_from->format('Y-m-d');

        if ($lease->end_date !== null && $lease->end_date->format('Y-m-d') < $from) {
            throw new RuntimeException('Der Vertrag endet vor dem '.Regeln::de($from).'. Die Erhöhung wird nicht mehr wirksam.');
        }
        if ($from <= $lease->start_date->format('Y-m-d')) {
            throw new RuntimeException('Die Erhöhung läge vor dem Vertragsbeginn.');
        }
        if ($lease->rents->contains(fn ($r) => $r->valid_from->format('Y-m-d') === $from)) {
            throw new RuntimeException('Für den '.Regeln::de($from).' gibt es schon eine Mietänderung. Bitte diese zuerst beim Mietvertrag entfernen.');
        }
        $later = $lease->rents->first(fn ($r) => $r->valid_from->format('Y-m-d') > $from);
        if ($later !== null) {
            throw new RuntimeException('Es gibt schon eine spätere Mietänderung (ab '.$later->valid_from->format('d.m.Y').'). Bitte die Mieten beim Mietvertrag von Hand anpassen, damit sich die Beträge nicht überschneiden.');
        }

        DB::transaction(function () use ($inc, $lease, $from, $on, $user, $what) {
            $src = $lease->currentRent($from);
            $components = [];
            foreach (array_keys(Mieten::COMPONENTS) as $col) {
                $components[$col] = $src?->{$col} ?? 0;
            }
            $components['rent_cold'] = $inc->new_cold;
            $extra = [];
            if ($inc->kind === 'index') {
                $extra = ['index_value' => $inc->datum('new_index'), 'index_month' => $inc->datum('index_month').'-01'];
            }
            $row = $lease->rents()->create($components + $extra + [
                'valid_from' => $from,
                'reason' => self::RENT_REASON[$inc->kind] ?? 'erhoehung',
                'note' => 'Mieterhöhung #'.$inc->id.' ('.$inc->kindLabel().')',
                'created_by' => $user->id,
            ]);
            if ($inc->kind === 'index') {
                $data = $inc->data ?? [];
                $data['previous_base_index'] = $lease->index_base_value !== null ? (string) $lease->index_base_value : null;
                $data['previous_base_month'] = $lease->index_base_month?->format('Y-m');
                $inc->data = $data;
                $lease->update(['index_base_value' => $inc->datum('new_index'), 'index_base_month' => $inc->datum('index_month').'-01']);
            }
            $inc->update(['status' => 'wirksam', 'decided_on' => $on, 'lease_rent_id' => $row->id]);
            Audit::log('rent_increase.effective', $inc, $what.': '.$inc->title().', '.$lease->title(), ['rent_cold' => (string) $inc->old_cold], ['rent_cold' => (string) $inc->new_cold]);
        });

        RentLedger::sync($lease);

        return $inc->refresh();
    }

    public static function reject(RentIncrease $inc, string $on, User $user): RentIncrease
    {
        if (! $inc->needsConsent() || $inc->status !== 'erklaert') {
            throw new RuntimeException('Nur eine erklärte Erhöhung, die Zustimmung braucht, kann abgelehnt werden.');
        }
        $inc->update(['status' => 'abgelehnt', 'decided_on' => $on]);
        Audit::log('rent_increase.rejected', $inc, 'Mieterhöhung vom Mieter abgelehnt: '.$inc->title().', '.$inc->lease?->title());

        return $inc->refresh();
    }

    public static function withdraw(RentIncrease $inc, User $user): RentIncrease
    {
        if (! in_array($inc->status, ['entwurf', 'erklaert', 'abgelehnt'], true)) {
            throw new RuntimeException('Eine wirksame Erhöhung kann nur der Administrator rückgängig machen.');
        }
        $inc->update(['status' => 'zurueckgezogen', 'decided_on' => date('Y-m-d')]);
        Audit::log('rent_increase.withdrawn', $inc, 'Mieterhöhung zurückgezogen: '.$inc->title().', '.$inc->lease?->title());

        return $inc->refresh();
    }

    /** Nur Administrator: macht eine wirksame Erhöhung rückgängig (entfernt den Mietstand). */
    public static function undo(RentIncrease $inc, User $user): RentIncrease
    {
        if (! $user->isAdmin()) {
            throw new RuntimeException('Das kann nur der Administrator.');
        }
        if ($inc->status !== 'wirksam' || $inc->kind === 'staffel') {
            throw new RuntimeException('Nur eine wirksame Erhöhung kann rückgängig gemacht werden.');
        }
        $lease = $inc->lease()->first();
        DB::transaction(function () use ($inc, $lease) {
            $rent = $inc->lease_rent_id ? LeaseRent::query()->find($inc->lease_rent_id) : null;
            $later = $lease->rents()->whereDate('valid_from', '>', $inc->effective_from->format('Y-m-d'))->exists();
            if ($later) {
                throw new RuntimeException('Nach dieser Erhöhung gibt es schon weitere Mietänderungen. Bitte zuerst diese beim Mietvertrag entfernen.');
            }
            $rent?->delete();
            if ($inc->kind === 'index' && $inc->datum('previous_base_index') !== null) {
                $lease->update(['index_base_value' => $inc->datum('previous_base_index'), 'index_base_month' => $inc->datum('previous_base_month') ? $inc->datum('previous_base_month').'-01' : null]);
            }
            $inc->update(['status' => 'erklaert', 'decided_on' => null, 'lease_rent_id' => null]);
            Audit::log('rent_increase.undone', $inc, 'Mieterhöhung rückgängig gemacht: '.$inc->title().', '.$lease->title());
        });
        RentLedger::sync($lease);

        return $inc->refresh();
    }

    public static function delete(RentIncrease $inc): void
    {
        if ($inc->status !== 'entwurf') {
            throw new RuntimeException('Nur Entwürfe können gelöscht werden. Alles andere bleibt als Verlauf erhalten (Erhöhung bei Bedarf zurückziehen).');
        }
        Audit::log('rent_increase.deleted', $inc, 'Entwurf einer Mieterhöhung gelöscht: '.$inc->title().', '.$inc->lease?->title());
        $inc->delete();
    }

    // ------------------------------------------------------------------ Übersicht

    /**
     * Verträge, bei denen eine Erhöhung ansteht oder möglich ist.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function possible(User $user, ?int $ownerId, string $today): array
    {
        $leases = Lease::query()->visibleTo($user)->activeOn($today)
            ->when($ownerId, fn ($q) => $q->where('leases.owner_id', $ownerId))
            ->with(['unit.property', 'tenant', 'rents'])->get();
        $open = RentIncrease::query()->visibleTo($user)->whereIn('status', ['entwurf', 'erklaert'])->get()->groupBy('lease_id');

        $rows = [];
        foreach ($leases as $lease) {
            $rents = self::rentsOf($lease);
            $cold = Regeln::coldOn($rents, $today);
            $area = self::areaOf($lease);
            $last = Regeln::lastChange($rents);
            $row = [
                'lease' => $lease,
                'cold' => $cold,
                'per_sqm' => $area ? $cold / $area : null,
                'last' => $last['from'] ?? null,
                'open' => $open->get($lease->id)?->first(),
                'kind' => null,
                'earliest' => null,
                'ready' => false,
                'note' => null,
            ];

            if ($lease->adjustment_type === 'index') {
                $row['kind'] = 'index';
                if ($last !== null) {
                    $eff = Regeln::ceilFirstOfMonth(Regeln::addMonths($last['from'], 12));
                    $row['earliest'] = Regeln::firstOfMonth($eff, -2);
                    $row['ready'] = $row['earliest'] <= $today;
                } else {
                    $row['ready'] = true;
                }
            } elseif ($lease->adjustment_type === 'staffel') {
                $next = $lease->rents->first(fn ($r) => $r->reason === 'staffel' && $r->valid_from->format('Y-m-d') > $today);
                $row['kind'] = 'staffel';
                $row['next_step'] = $next;
                $row['earliest'] = $next?->valid_from->format('Y-m-d');
                $row['ready'] = $next !== null;
                $row['note'] = $next === null ? 'Keine weitere Staffel eingetragen.' : null;
            } elseif ($lease->lease_type === 'wohnen') {
                $row['kind'] = 'vergleich';
                $row['earliest'] = Regeln::earliestNotice($last['from'] ?? null);
                $row['ready'] = $row['earliest'] === null || $row['earliest'] <= $today;
            } else {
                continue;
            }
            $rows[] = $row;
        }

        usort($rows, function ($a, $b) {
            if ($a['ready'] !== $b['ready']) {
                return $a['ready'] ? -1 : 1;
            }

            return strcmp((string) $a['earliest'], (string) $b['earliest']);
        });

        return $rows;
    }

    // ------------------------------------------------------------------ Hilfen

    public static function date(mixed $value): ?string
    {
        $v = trim((string) $value);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }

        return checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
    }
}
