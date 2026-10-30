<?php

namespace App\Support\Calendar;

use DateTimeImmutable;
use DateTimeZone;

/** Schreibt Termine als iCalendar-Datei (RFC 5545) für das Kalender-Abo (Paket 33). */
class Ics
{
    /** Text für ICS: Rückstrich, Semikolon, Komma und Zeilenumbruch maskieren. */
    public static function esc(string $s): string
    {
        $s = str_replace(["\r\n", "\r"], "\n", $s);

        return str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], $s);
    }

    /** Zeilen auf höchstens 75 Byte kürzen, Fortsetzungszeilen beginnen mit einem Leerzeichen. Bricht nie mitten in einem UTF-8-Zeichen. */
    public static function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }
        $out = '';
        $cur = '';
        $limit = 75;
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (strlen($cur) + strlen($ch) > $limit) {
                $out .= $cur."\r\n";
                $cur = ' ';
                $limit = 75;
            }
            $cur .= $ch;
        }

        return $out.$cur;
    }

    /**
     * @param  array<int, array{uid: string, summary: string, description: string, url: string, start: DateTimeImmutable, all_day: bool, warn: bool, category: string}>  $events
     */
    public static function build(array $events, string $calendarName, ?DateTimeImmutable $now = null): string
    {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $stamp = $now->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
        $lines = [
            'BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Immobilien-CRM//Kalender//DE', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::esc($calendarName), 'X-WR-TIMEZONE:Europe/Berlin', 'REFRESH-INTERVAL;VALUE=DURATION:PT1H', 'X-PUBLISHED-TTL:PT1H',
        ];
        foreach ($events as $e) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$e['uid'];
            $lines[] = 'DTSTAMP:'.$stamp;
            if ($e['all_day']) {
                $d = $e['start']->setTime(0, 0);
                $lines[] = 'DTSTART;VALUE=DATE:'.$d->format('Ymd');
                $lines[] = 'DTEND;VALUE=DATE:'.$d->modify('+1 day')->format('Ymd');
                $lines[] = 'TRANSP:TRANSPARENT';
            } else {
                $utc = new DateTimeZone('UTC');
                $lines[] = 'DTSTART:'.$e['start']->setTimezone($utc)->format('Ymd\THis\Z');
                $lines[] = 'DTEND:'.($e['end'] ?? $e['start']->modify('+1 hour'))->setTimezone($utc)->format('Ymd\THis\Z');
            }
            $lines[] = 'SUMMARY:'.self::esc($e['summary']);
            if ($e['description'] !== '') {
                $lines[] = 'DESCRIPTION:'.self::esc($e['description']);
            }
            if ($e['url'] !== '') {
                $lines[] = 'URL:'.$e['url'];
            }
            $lines[] = 'CATEGORIES:'.self::esc($e['category']);
            $lines[] = 'STATUS:CONFIRMED';
            if (! empty($e['alarm'])) {
                $lines[] = 'BEGIN:VALARM';
                $lines[] = 'ACTION:DISPLAY';
                $lines[] = 'DESCRIPTION:'.self::esc($e['summary']);
                $lines[] = 'TRIGGER:'.$e['alarm'];
                $lines[] = 'END:VALARM';
            } elseif ($e['warn']) {
                foreach ($e['all_day'] ? ['-P6DT15H', '-PT15H'] : ['-P1D', '-PT1H'] as $trigger) {
                    $lines[] = 'BEGIN:VALARM';
                    $lines[] = 'ACTION:DISPLAY';
                    $lines[] = 'DESCRIPTION:'.self::esc($e['summary']);
                    $lines[] = 'TRIGGER:'.$trigger;
                    $lines[] = 'END:VALARM';
                }
            }
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", array_map([self::class, 'fold'], $lines))."\r\n";
    }
}
