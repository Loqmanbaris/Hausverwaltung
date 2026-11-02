<?php

namespace App\Support\Bescheinigung;

use DateTimeImmutable;

/**
 * Regeln für Abmahnung und Kündigung durch den Vermieter (Paket 45). Reine Logik ohne Datenbank.
 *
 * Wohnraum: ordentliche Kündigung nur mit berechtigtem Interesse (§ 573 BGB), Frist nach § 573c BGB
 * (3 Monate, nach 5 Jahren 6, nach 8 Jahren 9; Zugang spätestens am 3. Werktag eines Monats zum Ablauf
 * des übernächsten Monats). Gewerbe: § 580a Abs. 2 BGB (Zugang spätestens am 3. Werktag eines Quartals
 * zum Ablauf des nächsten Quartals), soweit der Vertrag nichts anderes regelt.
 * Werktage sind Montag bis Samstag ohne bundesweite Feiertage (Samstag mitgezählt = die vorsichtige Rechnung).
 */
class Kuendigung
{
    public const ABMAHNUNG_GRUENDE = [
        'zahlung' => 'Unpünktliche oder unvollständige Mietzahlung',
        'ruhe' => 'Ruhestörung / Lärm',
        'hausordnung' => 'Verstoß gegen die Hausordnung',
        'untervermietung' => 'Unerlaubte Untervermietung / Gebrauchsüberlassung',
        'tiere' => 'Unerlaubte Tierhaltung',
        'gebrauch' => 'Vertragswidriger Gebrauch der Mietsache',
        'sonstiges' => 'Sonstiger Vertragsverstoß',
    ];

    public const ARTEN = [
        'ordentlich' => 'Ordentliche Kündigung',
        'fristlos_zahlung' => 'Fristlose Kündigung wegen Zahlungsverzug',
        'fristlos_sonst' => 'Fristlose Kündigung aus wichtigem Grund',
    ];

    /** Gründe der ordentlichen Kündigung von Wohnraum (§ 573 Abs. 2 BGB) */
    public const GRUENDE = [
        'eigenbedarf' => 'Eigenbedarf (§ 573 Abs. 2 Nr. 2 BGB)',
        'pflicht' => 'Schuldhafte, nicht unerhebliche Pflichtverletzung (§ 573 Abs. 2 Nr. 1 BGB)',
        'verwertung' => 'Hinderung an angemessener wirtschaftlicher Verwertung (§ 573 Abs. 2 Nr. 3 BGB)',
        'sonstiges' => 'Sonstiges berechtigtes Interesse (§ 573 Abs. 1 BGB)',
    ];

    public static function ostern(int $year): DateTimeImmutable
    {
        // Gaußsche Osterformel (gregorianisch)
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }

    /** Bundesweite gesetzliche Feiertage eines Jahres (JJJJ-MM-TT). */
    public static function feiertage(int $year): array
    {
        $o = self::ostern($year);

        return [
            $year.'-01-01', $o->modify('-2 days')->format('Y-m-d'), $o->modify('+1 day')->format('Y-m-d'),
            $year.'-05-01', $o->modify('+39 days')->format('Y-m-d'), $o->modify('+50 days')->format('Y-m-d'),
            $year.'-10-03', $year.'-12-25', $year.'-12-26',
        ];
    }

    public static function istWerktag(DateTimeImmutable $d): bool
    {
        return (int) $d->format('N') <= 6 && ! in_array($d->format('Y-m-d'), self::feiertage((int) $d->format('Y')), true);
    }

    /** Dritter Werktag des Monats, in dem $date liegt. */
    public static function dritterWerktag(string $date): string
    {
        $d = (new DateTimeImmutable($date))->modify('first day of this month');
        $n = 0;
        while (true) {
            if (self::istWerktag($d) && ++$n === 3) {
                return $d->format('Y-m-d');
            }
            $d = $d->modify('+1 day');
        }
    }

    /** Volle Jahre zwischen zwei Daten. */
    public static function jahre(string $from, string $to): int
    {
        return (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->invert ? 0 : (new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->y;
    }

    /** Kündigungsfrist des Vermieters bei Wohnraum in Monaten (§ 573c Abs. 1 BGB). */
    public static function fristMonate(string $mietbeginn, string $zugang): int
    {
        $j = self::jahre($mietbeginn, $zugang);

        return $j >= 8 ? 9 : ($j >= 5 ? 6 : 3);
    }

    /**
     * Ende des Mietverhältnisses bei ordentlicher Kündigung.
     *
     * @return array{ende: string, monate: int, spaetestens: string, regel: string}
     */
    public static function ordentlichesEnde(string $leaseType, string $mietbeginn, string $zugang): array
    {
        $z = new DateTimeImmutable($zugang);
        $third = self::dritterWerktag($zugang);
        $first = $z->modify('first day of this month');
        if ($leaseType === 'gewerbe') {
            // Quartal, in dem der Zugang liegt; nach dem 3. Werktag zählt das nächste Quartal als Zugangsquartal
            $qStart = $first->setDate((int) $first->format('Y'), intdiv((int) $first->format('n') - 1, 3) * 3 + 1, 1);
            $qThird = self::dritterWerktag($qStart->format('Y-m-d'));
            $base = $zugang <= $qThird ? $qStart : $qStart->modify('+3 months');
            $ende = $base->modify('+6 months')->modify('-1 day');
            $nextQ = $qStart->modify('+3 months');

            return [
                'ende' => $ende->format('Y-m-d'),
                'monate' => 0,
                'spaetestens' => $zugang <= $qThird ? $qThird : self::dritterWerktag($nextQ->format('Y-m-d')),
                'regel' => '§ 580a Abs. 2 BGB: Zugang spätestens am dritten Werktag eines Kalendervierteljahres zum Ablauf des nächsten Kalendervierteljahres',
            ];
        }

        $monate = self::fristMonate($mietbeginn, $zugang);
        $base = $zugang <= $third ? $first : $first->modify('+1 month');
        $ende = $base->modify('+'.$monate.' months')->modify('-1 day');

        return [
            'ende' => $ende->format('Y-m-d'),
            'monate' => $monate,
            'spaetestens' => $zugang <= $third ? $third : self::dritterWerktag($first->modify('+1 month')->format('Y-m-d')),
            'regel' => '§ 573c Abs. 1 BGB: Kündigungsfrist '.$monate.' Monate (Mietdauer '.($monate === 3 ? 'unter 5' : ($monate === 6 ? 'mindestens 5' : 'mindestens 8')).' Jahre), Zugang spätestens am dritten Werktag eines Monats',
        ];
    }

    /**
     * Prüft grob, ob der Rückstand eine fristlose Kündigung nach § 543 Abs. 2 Nr. 3 BGB trägt
     * (Wohnraum: mehr als eine Monatsmiete aus zwei aufeinanderfolgenden Terminen, oder insgesamt zwei Monatsmieten).
     *
     * @param  array<int, float>  $offeneMieten  offene Beträge je Monatsmiete, nach Fälligkeit sortiert
     * @return array{ok: bool, text: string}
     */
    public static function zahlungsverzug(array $offeneMieten, float $monatsmiete): array
    {
        $sum = array_sum($offeneMieten);
        if ($monatsmiete <= 0) {
            return ['ok' => false, 'text' => 'Keine Monatsmiete hinterlegt.'];
        }
        if ($sum + 0.004 >= 2 * $monatsmiete) {
            return ['ok' => true, 'text' => 'Der Rückstand erreicht mindestens zwei Monatsmieten.'];
        }
        $vals = array_values($offeneMieten);
        for ($i = 1; $i < count($vals); $i++) {
            if ($vals[$i - 1] > 0.004 && $vals[$i] > 0.004 && $vals[$i - 1] + $vals[$i] > $monatsmiete + 0.004) {
                return ['ok' => true, 'text' => 'Aus zwei aufeinanderfolgenden Terminen ist mehr als eine Monatsmiete offen.'];
            }
        }

        return ['ok' => false, 'text' => 'Der Rückstand reicht für eine fristlose Kündigung wegen Zahlungsverzug voraussichtlich nicht aus (nötig: mehr als eine Monatsmiete aus zwei aufeinanderfolgenden Terminen oder insgesamt zwei Monatsmieten).'];
    }
}
