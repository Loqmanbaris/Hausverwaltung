<?php

namespace App\Console\Commands;

use App\Support\Calendar\Ics;
use App\Support\Calendar\Kalender;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 33 (Kalender-Abo). */
class KalenderSelbsttest extends Command
{
    protected $signature = 'kalender:selbsttest';

    protected $description = 'Selbsttest Kalender-Abo (Paket 33)';

    public function handle(): int
    {
        $now = new \DateTimeImmutable('2026-10-12 10:00:00', new \DateTimeZone('UTC'));
        $ics = Ics::build([
            ['uid' => 'a@x', 'summary' => 'Kündigungsfrist: Strom, Haus A; wichtig', 'description' => "Zeile 1\nZeile 2", 'url' => 'https://x.de/a', 'start' => new \DateTimeImmutable('2026-12-30'), 'all_day' => true, 'warn' => true, 'category' => 'Verträge'],
            ['uid' => 'b@x', 'summary' => 'Besichtigung', 'description' => '', 'url' => '', 'start' => new \DateTimeImmutable('2026-11-03 15:30:00', new \DateTimeZone('Europe/Berlin')), 'all_day' => false, 'warn' => false, 'category' => 'Besichtigungen'],
        ], 'Test', $now);
        $long = Ics::fold('DESCRIPTION:'.str_repeat('Äöü-lange Zeile ', 20));
        $foldOk = true;
        foreach (explode("\r\n", $long) as $l) {
            $foldOk = $foldOk && strlen($l) <= 75 && mb_check_encoding($l, 'UTF-8');
        }
        $checks = [
            'Tabelle' => Schema::hasTable('calendar_feeds'),
            'Textzeichen maskiert' => Ics::esc("a,b;c\\d\ne") === 'a\\,b\\;c\\\\d\\ne',
            'Lange Zeilen gekürzt' => $foldOk,
            'Ganztägiger Termin' => str_contains($ics, "DTSTART;VALUE=DATE:20261230\r\n") && str_contains($ics, "DTEND;VALUE=DATE:20261231\r\n"),
            'Termin mit Uhrzeit in UTC' => str_contains($ics, "DTSTART:20261103T143000Z\r\n"),
            'Erinnerung bei Fristen' => substr_count($ics, 'BEGIN:VALARM') === 2,
            'Aufbau' => str_starts_with($ics, "BEGIN:VCALENDAR\r\n") && str_ends_with($ics, "END:VCALENDAR\r\n") && substr_count($ics, 'BEGIN:VEVENT') === 2,
            'Arten erkannt' => Kalender::category('/laufende-vertraege/3') === 'vertraege' && Kalender::category('/mietvertraege/4') === 'miete' && Kalender::category('/wartung?objekt=1') === 'technik' && Kalender::category('/interessenten/2') === 'interessenten',
            'Link-Schlüssel' => Kalender::byToken('kurz') === null && Kalender::byToken(str_repeat('a', 40)) === null,
            'Seiten' => Route::has('calendar.index') && Route::has('calendar.feed'),
            'Ansicht' => view()->exists('calendar.index'),
        ];
        $this->line('Selbsttest Paket 33 (Kalender-Abo)');
        $failed = 0;
        foreach ($checks as $label => $ok) {
            $failed += $ok ? 0 : 1;
            $this->line(($ok ? '  [OK]     ' : '  [FEHLER] ').$label);
        }
        if ($failed > 0) {
            $this->error($failed.' Prüfung(en) fehlgeschlagen.');

            return self::FAILURE;
        }
        $this->info('Alle Prüfungen in Ordnung.');

        return self::SUCCESS;
    }
}
