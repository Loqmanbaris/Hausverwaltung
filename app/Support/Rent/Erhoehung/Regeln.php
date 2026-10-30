<?php

namespace App\Support\Rent\Erhoehung;

use DateTimeImmutable;

/**
 * Regeln für Mieterhöhungen nach deutschem Wohnraummietrecht (Paket 27). Reine Rechenfunktionen ohne Datenbank.
 * Rechtsstand: Oktober 2026 (§ 559e BGB in der Fassung vom 29.07.2026; Mietrecht II ist noch nicht in Kraft).
 * Alle Beträge in Cent (int), alle Daten als „JJJJ-MM-TT“.
 *
 * Eine Mietzeile ist ein Array: ['from' => 'JJJJ-MM-TT', 'reason' => string, 'cold' => Cent].
 */
class Regeln
{
    public const CAP_NORMAL = 20;      // § 558 Abs. 3: Kappungsgrenze in Prozent innerhalb von drei Jahren
    public const CAP_TIGHT = 15;       // in Gebieten mit angespanntem Wohnungsmarkt (Landesverordnung)
    public const MOD_PERCENT = 8;      // § 559 Abs. 1
    public const HEATING_PERCENT = 10; // § 559e Abs. 1 (Heizungstausch mit Förderung)
    public const MOD_CAP_CENTS = 300;  // § 559 Abs. 3a: 3 € je m² in sechs Jahren
    public const MOD_CAP_LOW_CENTS = 200; // 2 € je m², wenn die Miete vorher unter 7 € je m² liegt
    public const LOW_RENT_CENTS = 700;
    public const HEATING_CAP_CENTS = 50;  // § 559 Abs. 3a Satz 3 und § 559e Abs. 3: 0,50 € je m² in sechs Jahren
    public const HEATING_UPKEEP = 15;     // § 559e Abs. 2: pauschaler Erhaltungsanteil in Prozent
    public const SIMPLE_LIMIT_CENTS = 1000000; // § 559c: vereinfachtes Verfahren bis 10.000 € je Wohnung
    public const SIMPLE_UPKEEP = 30;      // § 559c: pauschaler Erhaltungsanteil in Prozent

    public const INDEX_CAP_MODES = [
        'keine' => 'Keine Begrenzung (geltendes Recht)',
        'halbteilung' => 'Halbteilung über 3 % (Regierungsentwurf Mietrecht II)',
        'deckel' => 'Höchstens 3,5 % (frühere Entwurfsfassung Mietrecht II)',
    ];

    // ------------------------------------------------------------------ Datumshilfen

    /** Monate addieren, ohne den Monat zu überspringen (31.01. + 1 Monat = 28.02.). */
    public static function addMonths(string $date, int $months): string
    {
        $d = new DateTimeImmutable($date);
        $day = (int) $d->format('j');
        $first = $d->modify('first day of this month')->modify(($months >= 0 ? '+' : '').$months.' months');
        $last = (int) $first->format('t');

        return $first->setDate((int) $first->format('Y'), (int) $first->format('n'), min($day, $last))->format('Y-m-d');
    }

    /** Erster Tag des Monats, der $months Monate nach dem Monat von $date liegt. */
    public static function firstOfMonth(string $date, int $months = 0): string
    {
        return (new DateTimeImmutable($date))->modify('first day of this month')->modify(($months >= 0 ? '+' : '').$months.' months')->format('Y-m-d');
    }

    /** Letzter Tag des Monats, der $months Monate nach dem Monat von $date liegt. */
    public static function lastOfMonth(string $date, int $months = 0): string
    {
        return (new DateTimeImmutable(self::firstOfMonth($date, $months)))->modify('last day of this month')->format('Y-m-d');
    }

    /** Der Tag selbst, wenn er ein Monatserster ist, sonst der nächste Monatserste. */
    public static function ceilFirstOfMonth(string $date): string
    {
        return (int) (new DateTimeImmutable($date))->format('j') === 1 ? $date : self::firstOfMonth($date, 1);
    }

    // ------------------------------------------------------------------ Fristen

    /**
     * Fristen ab Zugang des Schreibens.
     * vergleich:      Zustimmungsfrist bis Ende des zweiten Folgemonats, Wirkung ab Beginn des dritten Folgemonats (§ 558b),
     *                 Klage innerhalb von drei weiteren Monaten, Sonderkündigung des Mieters (§ 561).
     * modernisierung: Wirkung ab Beginn des dritten Folgemonats (§ 559b Abs. 2), bei verspäteter oder zu niedriger Ankündigung sechs Monate später.
     * index:          Wirkung ab Beginn des übernächsten Monats (§ 557b Abs. 3).
     *
     * @return array{effective: string, consent_until: ?string, suit_until: ?string, tenant_terminates_by: ?string, tenant_terminates_to: ?string}
     */
    public static function timeline(string $kind, string $noticeOn, bool $delayed = false): array
    {
        $none = ['consent_until' => null, 'suit_until' => null, 'tenant_terminates_by' => null, 'tenant_terminates_to' => null];

        return match ($kind) {
            'vergleich' => [
                'effective' => self::firstOfMonth($noticeOn, 3),
                'consent_until' => self::lastOfMonth($noticeOn, 2),
                'suit_until' => self::lastOfMonth($noticeOn, 5),
                'tenant_terminates_by' => self::lastOfMonth($noticeOn, 2),
                'tenant_terminates_to' => self::lastOfMonth($noticeOn, 4),
            ],
            'modernisierung' => [
                'effective' => self::firstOfMonth($noticeOn, $delayed ? 9 : 3),
                'tenant_terminates_by' => self::lastOfMonth($noticeOn, 2),
                'tenant_terminates_to' => self::lastOfMonth($noticeOn, 4),
            ] + $none,
            'index' => ['effective' => self::firstOfMonth($noticeOn, 2)] + $none,
            default => ['effective' => self::firstOfMonth($noticeOn, 1)] + $none,
        };
    }

    // ------------------------------------------------------------------ Mietverlauf

    /**
     * Wann wurde die Kaltmiete zuletzt geändert? Modernisierungszuschläge (§§ 559 bis 560 BGB) und reine
     * Änderungen der Vorauszahlungen zählen nicht. Der Vertragsbeginn zählt als erste Miete.
     *
     * @param  array<int, array{from: string, reason: string, cold: int}>  $rents
     * @return ?array{from: string, reason: string, cold: int}
     */
    public static function lastChange(array $rents, ?string $upTo = null): ?array
    {
        $rents = array_values($rents);
        $last = null;
        foreach ($rents as $i => $r) {
            if ($upTo !== null && $r['from'] > $upTo) {
                break;
            }
            if ($r['reason'] === 'modernisierung' || $r['reason'] === 'vorauszahlung') {
                continue;
            }
            $before = $i > 0 ? $rents[$i - 1]['cold'] : null;
            if ($i === 0 || $r['cold'] !== $before) {
                $last = $r;
            }
        }

        return $last;
    }

    /** Kaltmiete (Cent), die an diesem Tag galt; vor dem ersten Eintrag die erste Miete. */
    public static function coldOn(array $rents, string $date): int
    {
        $rents = array_values($rents);
        $cold = $rents[0]['cold'] ?? 0;
        foreach ($rents as $r) {
            if ($r['from'] <= $date) {
                $cold = $r['cold'];
            }
        }

        return $cold;
    }

    /** Summe der Modernisierungszuschläge (Cent je Monat), die in diesem Zeitraum wirksam wurden (von exklusiv bis inklusiv). */
    public static function modernisationDelta(array $rents, string $afterExclusive, string $untilInclusive): int
    {
        $rents = array_values($rents);
        $sum = 0;
        foreach ($rents as $i => $r) {
            if ($i === 0 || $r['reason'] !== 'modernisierung') {
                continue;
            }
            if ($r['from'] > $afterExclusive && $r['from'] <= $untilInclusive) {
                $sum += $r['cold'] - $rents[$i - 1]['cold'];
            }
        }

        return $sum;
    }

    // ------------------------------------------------------------------ § 558 Vergleichsmiete

    /**
     * Frühester Zugang eines Mieterhöhungsverlangens nach § 558 Abs. 1: ein Jahr nach der letzten Änderung, und die
     * Erhöhung darf erst wirksam werden, wenn die Miete seit 15 Monaten unverändert ist.
     */
    public static function earliestNotice(?string $lastChangeFrom): ?string
    {
        if ($lastChangeFrom === null) {
            return null;
        }
        $a = self::addMonths($lastChangeFrom, 12);

        return max($a, self::ceilFirstOfMonth($a));
    }

    /**
     * Kappungsgrenze (§ 558 Abs. 3): höchstens 20 % (15 %) mehr als die Miete vor drei Jahren. Modernisierungszuschläge
     * der letzten drei Jahre bleiben unberücksichtigt, die neue Miete darf also um diese Zuschläge höher sein.
     *
     * @return array{base: int, window_start: string, mod_delta: int, limit: int, percent: int}
     */
    public static function kappung(array $rents, string $effective, int $capPercent): array
    {
        $start = self::addMonths($effective, -36);
        $base = self::coldOn($rents, $start);
        $mod = self::modernisationDelta($rents, $start, $effective);

        return [
            'base' => $base,
            'window_start' => $start,
            'mod_delta' => $mod,
            'limit' => intdiv($base * (100 + $capPercent), 100) + $mod,
            'percent' => $capPercent,
        ];
    }

    /**
     * Prüft ein Mieterhöhungsverlangen auf die ortsübliche Vergleichsmiete.
     *
     * @param  array{lease_type: string, adjustment_type: string, rents: array, notice_on: string, area_sqm: ?float, cap_percent: int, new_cold: int, comparable_sqm: ?int, lease_end: ?string}  $in  comparable_sqm in Cent je m²
     * @return array{checks: array<int, array{0: string, 1: string}>, ok: bool, effective: string, timeline: array, cap: array, max_by_comparable: ?int, max_allowed: ?int, last_change: ?array, earliest_notice: ?string}
     */
    public static function vergleich(array $in): array
    {
        $checks = [];
        $add = function (string $level, string $text) use (&$checks) {
            $checks[] = [$level, $text];
        };

        $rents = $in['rents'];
        $tl = self::timeline('vergleich', $in['notice_on']);
        $effective = $tl['effective'];
        $current = self::coldOn($rents, $in['notice_on']);
        $new = (int) $in['new_cold'];

        if ($in['lease_type'] !== 'wohnen') {
            $add('fehler', 'Die Regeln zur ortsüblichen Vergleichsmiete (§ 558 BGB) gelten nur für Wohnraum. Bei Gewerbe und Montage-Wohnungen kann die Miete nur vereinbart oder über eine Klausel im Vertrag angepasst werden.');
        }
        if ($in['adjustment_type'] === 'staffel') {
            $add('fehler', 'Bei einer Staffelmiete ist eine Erhöhung auf die Vergleichsmiete ausgeschlossen (§ 557a Abs. 2 BGB).');
        } elseif ($in['adjustment_type'] === 'index') {
            $add('fehler', 'Bei einer Indexmiete ist eine Erhöhung auf die Vergleichsmiete ausgeschlossen (§ 557b Abs. 2 BGB).');
        }

        // Sperrfrist und Wartefrist
        $last = self::lastChange($rents);
        $earliest = self::earliestNotice($last['from'] ?? null);
        if ($last !== null) {
            $lastLabel = self::de($last['from']);
            $minEffective = self::addMonths($last['from'], 15);
            if ($in['notice_on'] < self::addMonths($last['from'], 12)) {
                $add('fehler', 'Das Verlangen darf frühestens ein Jahr nach der letzten Änderung der Kaltmiete (zuletzt '.$lastLabel.') zugehen, also frühestens am '.self::de($earliest).'.');
            } elseif ($effective < $minEffective) {
                $add('fehler', 'Die Miete muss bei Wirksamwerden seit 15 Monaten unverändert sein. Bei Zugang am '.self::de($in['notice_on']).' würde die Erhöhung schon am '.self::de($effective).' gelten, frühestens zulässig ist der '.self::de($minEffective).'.');
            } else {
                $add('ok', 'Die Miete ist seit '.$lastLabel.' unverändert (Sperrfrist ein Jahr und Wartefrist 15 Monate eingehalten).');
            }
        }

        // Fläche und Kappungsgrenze
        $cap = self::kappung($rents, $effective, (int) $in['cap_percent']);
        $maxAllowed = $cap['limit'];
        if ($new > $cap['limit']) {
            $add('fehler', 'Die Kappungsgrenze von '.$cap['percent'].' % in drei Jahren ist überschritten: Ausgangsmiete vor drei Jahren '.self::eur($cap['base']).', höchstens zulässig '.self::eur($cap['limit']).($cap['mod_delta'] > 0 ? ' (einschließlich '.self::eur($cap['mod_delta']).' Modernisierungszuschlag, der nicht mitzählt)' : '').'.');
        } else {
            $add('ok', 'Kappungsgrenze '.$cap['percent'].' % eingehalten (Ausgangsmiete vor drei Jahren '.self::eur($cap['base']).', höchstens '.self::eur($cap['limit']).').');
        }

        // Ortsübliche Vergleichsmiete
        $area = $in['area_sqm'];
        $maxComparable = null;
        if ($area === null || $area <= 0) {
            $add('fehler', 'Bei der Einheit fehlt die Wohnfläche. Ohne sie lässt sich die Miete je m² nicht mit dem Mietspiegel vergleichen.');
        } elseif ($in['comparable_sqm'] === null || $in['comparable_sqm'] <= 0) {
            $add('warn', 'Die ortsübliche Vergleichsmiete (€ je m²) ist noch nicht eingetragen. Ohne sie lässt sich nicht prüfen, ob die neue Miete zu hoch ist.');
        } else {
            $maxComparable = (int) floor($in['comparable_sqm'] * $area);
            $maxAllowed = min($maxAllowed, $maxComparable);
            if ($new > $maxComparable) {
                $add('fehler', 'Die neue Kaltmiete liegt über der ortsüblichen Vergleichsmiete ('.self::eur($in['comparable_sqm']).' je m² × '.self::num($area).' m² = '.self::eur($maxComparable).').');
            } else {
                $add('ok', 'Die neue Kaltmiete liegt nicht über der ortsüblichen Vergleichsmiete ('.self::eur($maxComparable).').');
            }
        }

        if ($new <= $current) {
            $add('fehler', 'Die neue Kaltmiete muss höher sein als die bisherige ('.self::eur($current).').');
        }
        if ($in['lease_end'] !== null && $in['lease_end'] < $effective) {
            $add('warn', 'Der Vertrag endet am '.self::de($in['lease_end']).', vor dem Wirksamwerden der Erhöhung.');
        }

        return [
            'checks' => $checks,
            'ok' => self::noErrors($checks),
            'effective' => $effective,
            'timeline' => $tl,
            'cap' => $cap,
            'max_by_comparable' => $maxComparable,
            'max_allowed' => $maxAllowed,
            'last_change' => $last,
            'earliest_notice' => $earliest,
        ];
    }

    // ------------------------------------------------------------------ § 559 Modernisierung

    /**
     * Berechnet die Mieterhöhung nach Modernisierung.
     *
     * @param  array{lease_type: string, notice_on: string, area_sqm: ?float, old_cold: int, costs: int, upkeep_percent: float, subsidies: int, heating: bool, simplified: bool, delayed: bool, prior_monthly: int, prior_heating_monthly: int}  $in
     * @return array<string, mixed>
     */
    public static function modernisierung(array $in): array
    {
        $checks = [];
        $add = function (string $level, string $text) use (&$checks) {
            $checks[] = [$level, $text];
        };

        $area = $in['area_sqm'];
        $costs = max(0, (int) $in['costs']);
        $subsidies = max(0, (int) $in['subsidies']);
        $type = (string) ($in['heating_type'] ?? '');
        $heating = (bool) ($in['heating'] ?? false) || $type !== '';
        if ($heating && $type === '') {
            $type = 'alt'; // ältere Erfassung ohne Angabe der Heizungsart: Erhaltungsanteil wie eingetragen
        }
        $simplified = (bool) $in['simplified'];
        $subsidized = $heating && $subsidies > 0;
        $pump = $type === 'waermepumpe';
        $proof = (string) ($in['pump_proof'] ?? '');
        // § 559f Abs. 2 BGB: Wärmepumpe ohne Nachweis (Jahresarbeitszahl mindestens 2,5) oder Ausnahme: nur die Hälfte der Kosten
        $halved = $pump && ! in_array($proof, ['nachweis', 'ausnahme'], true);
        $costsBase = $halved ? intdiv($costs, 2) : $costs;
        // § 559e Abs. 2 BGB: bei geförderter Heizung pauschal 15 % Erhaltungsanteil, außer bei Gas- und Ölheizung (§ 43 GModG)
        $flatUpkeep = ! $simplified && $subsidized && in_array($type, ['waermepumpe', 'erneuerbar'], true);
        $upkeepPercent = $simplified ? self::SIMPLE_UPKEEP : ($flatUpkeep ? (float) self::HEATING_UPKEEP : max(0.0, min(100.0, (float) $in['upkeep_percent'])));
        $upkeep = (int) round($costsBase * $upkeepPercent / 100);
        $base = max(0, $costsBase - $upkeep - $subsidies);

        $percent = $subsidized ? self::HEATING_PERCENT : self::MOD_PERCENT;
        $yearly = intdiv($base * $percent, 100);
        $monthly = intdiv($yearly, 12);

        $capSqm = null;
        $capMonthly = null;
        $heatingCap = null;
        if ($area !== null && $area > 0) {
            $perSqmNow = $in['old_cold'] / $area;
            $capSqm = $perSqmNow < self::LOW_RENT_CENTS ? self::MOD_CAP_LOW_CENTS : self::MOD_CAP_CENTS;
            $capMonthly = max(0, (int) floor($capSqm * $area) - (int) $in['prior_monthly']);
            if ($heating) {
                $heatingCap = max(0, (int) floor(self::HEATING_CAP_CENTS * $area) - (int) $in['prior_heating_monthly']);
            }
        }

        $allowed = $monthly;
        $limited = null;
        if ($capMonthly !== null && $capMonthly < $allowed) {
            $allowed = $capMonthly;
            $limited = 'kappung';
        }
        if ($heatingCap !== null && $heatingCap < $allowed) {
            $allowed = $heatingCap;
            $limited = 'heizung';
        }

        $tl = self::timeline('modernisierung', $in['notice_on'], (bool) $in['delayed']);

        if ($in['lease_type'] !== 'wohnen') {
            $add('fehler', 'Die Modernisierungsumlage nach § 559 BGB gilt nur für Wohnraum.');
        }
        if ($area === null || $area <= 0) {
            $add('fehler', 'Bei der Einheit fehlt die Wohnfläche. Sie wird für die Kappungsgrenze gebraucht.');
        }
        if ($costs <= 0) {
            $add('fehler', 'Bitte die für die Wohnung aufgewendeten Kosten eintragen.');
        }
        if ($simplified && $costs > self::SIMPLE_LIMIT_CENTS) {
            $add('fehler', 'Das vereinfachte Verfahren (§ 559c BGB) ist nur bis '.self::eur(self::SIMPLE_LIMIT_CENTS).' Kosten je Wohnung möglich.');
        } elseif ($simplified) {
            $add('ok', 'Vereinfachtes Verfahren: Erhaltungsanteil pauschal '.self::SIMPLE_UPKEEP.' %, kein Härteeinwand. Danach können Sie fünf Jahre lang keine weitere Erhöhung nach § 559 verlangen.');
        }
        if ($heating && $subsidies <= 0) {
            $add('warn', 'Beim Heizungstausch gilt der Satz von 10 % (§ 559e BGB) nur, wenn Sie Fördermittel in Anspruch genommen haben. Ohne Förderung wird mit 8 % gerechnet.');
        }
        if ($heating) {
            $add('ok', 'Heizungstausch: Erhöhung höchstens '.self::eur(self::HEATING_CAP_CENTS).' je m² in sechs Jahren (§ 559e Abs. 3 BGB), gerechnet innerhalb der allgemeinen Grenze.');
            if ($flatUpkeep) {
                $add('ok', 'Der Erhaltungsanteil beträgt pauschal '.self::HEATING_UPKEEP.' % (§ 559e Abs. 2 Satz 1 BGB).');
            } elseif ($type === 'fossil' && ! $simplified) {
                $add('warn', 'Gas- oder Ölheizung (§ 43 GModG): Der pauschale Abzug von '.self::HEATING_UPKEEP.' % entfällt (§ 559e Abs. 2 Satz 2 BGB, Stand 29.07.2026). Bitte den Anteil der tatsächlich ersparten Erhaltungskosten eintragen.');
            } elseif (! $subsidized && ! $simplified) {
                $add('warn', 'Ohne Förderung gilt der Erhaltungsanteil nach § 559 BGB. Bitte den Anteil der tatsächlich ersparten Erhaltungskosten eintragen.');
            }
        }
        if ($pump) {
            if ($halved) {
                $add('warn', 'Wärmepumpe (§ 559f BGB): Ohne Nachweis einer Jahresarbeitszahl von mindestens 2,5 durch einen Fachunternehmer und ohne Ausnahme zählt nur die Hälfte der Kosten. Angesetzt werden '.self::eur($costsBase).' statt '.self::eur($costs).'. Die Fördermittel werden in voller Höhe abgezogen (vorsichtige Auslegung).');
            } elseif ($proof === 'nachweis') {
                $add('ok', 'Wärmepumpe: Der Nachweis der Jahresarbeitszahl (mindestens 2,5, nach VDI 4650, durch einen Fachunternehmer) muss vorliegen. Bitte dem Schreiben beifügen.');
            } else {
                $add('ok', 'Wärmepumpe: Ausnahme nach § 559f Abs. 1 Satz 2 BGB (Gebäude nach 1996 errichtet, Wärmeschutzverordnung 1994, Sanierung nach § 38 GModG oder Vorlauftemperatur höchstens 55 °C). Bitte halten Sie den Beleg bereit.');
            }
        }
        if ($limited === 'kappung') {
            $add('warn', 'Die Erhöhung wird durch die Kappungsgrenze von '.self::eur((int) $capSqm).' je m² in sechs Jahren begrenzt. Die Maßnahme erlaubt rechnerisch '.self::eur($monthly).' im Monat, zulässig sind '.self::eur($allowed).'.');
        } elseif ($limited === 'heizung') {
            $add('warn', 'Die Erhöhung wird durch die Heizungsgrenze von '.self::eur(self::HEATING_CAP_CENTS).' je m² begrenzt. Zulässig sind '.self::eur($allowed).' im Monat.');
        } elseif ($capMonthly !== null && $costs > 0) {
            $add('ok', 'Kappungsgrenze eingehalten ('.self::eur((int) $capSqm).' je m² in sechs Jahren, bisher davon genutzt: '.self::eur((int) $in['prior_monthly']).' im Monat).');
        }
        if ($in['delayed']) {
            $add('warn', 'Die Erhöhung wirkt wegen fehlender oder zu niedriger Ankündigung erst sechs Monate später (§ 559b Abs. 2 BGB).');
        } else {
            $add('ok', 'Voraussetzung: Die Maßnahme wurde mindestens drei Monate vor Beginn in Textform angekündigt (§ 555c BGB), und die Erhöhung übersteigt die angekündigte nicht um mehr als 10 %. Sonst bitte „verspätet“ ankreuzen.');
        }
        if ($allowed <= 0 && $costs > 0) {
            $add('fehler', 'Es bleibt keine Erhöhung übrig.');
        }

        return [
            'checks' => $checks,
            'ok' => self::noErrors($checks),
            'costs' => $costs,
            'costs_base' => $costsBase,
            'halved' => $halved,
            'halved_amount' => $costs - $costsBase,
            'heating_type' => $type,
            'pump_proof' => $proof,
            'flat_upkeep' => $flatUpkeep,
            'upkeep_percent' => $upkeepPercent,
            'upkeep' => $upkeep,
            'subsidies' => $subsidies,
            'base' => $base,
            'percent' => $percent,
            'yearly' => $yearly,
            'monthly' => $monthly,
            'cap_sqm' => $capSqm,
            'cap_monthly' => $capMonthly,
            'heating_cap' => $heatingCap,
            'allowed' => $allowed,
            'limited' => $limited,
            'new_cold' => (int) $in['old_cold'] + $allowed,
            'effective' => $tl['effective'],
            'timeline' => $tl,
        ];
    }

    // ------------------------------------------------------------------ § 557b Index

    /**
     * Indexanpassung. Index mit einer Nachkommastelle (z. B. 121.4).
     *
     * @param  array{lease_type: string, adjustment_type: string, base_index: ?float, new_index: float, old_cold: int, last_change_from: ?string, notice_on: string, mode: string}  $in
     * @return array<string, mixed>
     */
    public static function index(array $in): array
    {
        $checks = [];
        $add = function (string $level, string $text) use (&$checks) {
            $checks[] = [$level, $text];
        };

        $tl = self::timeline('index', $in['notice_on']);
        $effective = $tl['effective'];
        $base = $in['base_index'];
        $percent = null;
        $applied = null;
        $newCold = (int) $in['old_cold'];

        if ($in['adjustment_type'] !== 'index') {
            $add('fehler', 'Der Vertrag ist nicht als Indexmiete angelegt. Eine Indexanpassung ist nur möglich, wenn sie im Mietvertrag vereinbart wurde.');
        }
        if ($base === null || $base <= 0) {
            $add('fehler', 'Beim Vertrag fehlt der Ausgangswert des Index.');
        } else {
            $percent = ($in['new_index'] / $base - 1) * 100;
            $applied = self::cappedPercent($percent, $in['mode']);
            $newCold = (int) round($in['old_cold'] * (1 + $applied / 100));
            if ($applied < $percent - 0.0001) {
                $add('warn', 'Die Veränderung des Index ('.self::pct($percent).') wird nach der eingestellten Begrenzung auf '.self::pct($applied).' gekürzt. Das Gesetz (Mietrecht II) ist noch nicht in Kraft: Wenden Sie diese Begrenzung nur an, wenn Sie sie freiwillig oder nach Inkrafttreten brauchen.');
            }
            if ($newCold <= $in['old_cold']) {
                $add('warn', 'Der Index ist nicht gestiegen. Die Miete würde nicht erhöht'.($newCold < $in['old_cold'] ? ', der Mieter könnte eine Senkung verlangen' : '').'.');
            }
        }

        if ($in['lease_type'] === 'wohnen') {
            if ($in['last_change_from'] !== null) {
                $minEffective = self::ceilFirstOfMonth(self::addMonths($in['last_change_from'], 12));
                if ($effective < $minEffective) {
                    $add('fehler', 'Die Miete muss mindestens ein Jahr unverändert bleiben (§ 557b Abs. 2 BGB). Letzte Änderung '.self::de($in['last_change_from']).', frühestens ab '.self::de($minEffective).'.');
                } else {
                    $add('ok', 'Ein Jahr seit der letzten Änderung der Kaltmiete ist abgelaufen.');
                }
            }
        } else {
            $add('warn', 'Bei Gewerbe gilt nicht § 557b BGB, sondern die Wertsicherungsklausel im Vertrag. Bitte Fristen und Berechnung dort prüfen.');
        }
        $add('ok', 'Die neue Miete ist ab dem '.self::de($effective).' zu zahlen (Beginn des übernächsten Monats nach Zugang, § 557b Abs. 3 BGB). Die Erklärung muss in Textform erfolgen und die Änderung des Index sowie die Miete oder den Erhöhungsbetrag angeben.');

        return [
            'checks' => $checks,
            'ok' => self::noErrors($checks),
            'percent' => $percent,
            'applied_percent' => $applied,
            'new_cold' => $newCold,
            'effective' => $effective,
            'timeline' => $tl,
        ];
    }

    /** Veränderung des Index in Prozent, ggf. begrenzt nach dem gewählten Modell (Mietrecht II, noch nicht in Kraft). */
    public static function cappedPercent(float $percent, string $mode): float
    {
        if ($percent <= 0) {
            return $percent;
        }

        return match ($mode) {
            'halbteilung' => $percent > 3.0 ? 3.0 + ($percent - 3.0) / 2 : $percent,
            'deckel' => min($percent, 3.5),
            default => $percent,
        };
    }

    // ------------------------------------------------------------------ Formatierung

    public static function noErrors(array $checks): bool
    {
        foreach ($checks as $c) {
            if ($c[0] === 'fehler') {
                return false;
            }
        }

        return true;
    }

    public static function de(?string $date): string
    {
        return $date === null ? '–' : (new DateTimeImmutable($date))->format('d.m.Y');
    }

    public static function eur(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.').' €';
    }

    public static function num(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    public static function pct(float $value): string
    {
        return number_format($value, 2, ',', '').' %';
    }

    /** „650.00“ → 65000 */
    public static function cents(mixed $euro): int
    {
        return (int) round(((float) $euro) * 100);
    }

    /** 65000 → „650.00“ */
    public static function euro(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}
