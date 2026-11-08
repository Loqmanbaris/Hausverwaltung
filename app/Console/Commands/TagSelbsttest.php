<?php

namespace App\Console\Commands;

use App\Models\PersonalItem;
use App\Support\Calendar\Ics;
use App\Support\Tag\MeinTag;
use DateTimeImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

/** Selbsttest Paket 42 (Mein Tag, eigene Termine und To-dos, Menü-Ansichten). */
class TagSelbsttest extends Command
{
    protected $signature = 'tag:selbsttest';

    protected $description = 'Selbsttest Mein Tag (Paket 42)';

    public function handle(): int
    {
        $timed = new PersonalItem(['kind' => 'termin', 'title' => 'Besichtigung', 'due_on' => '2026-11-05', 'start_time' => '14:30', 'remind' => '60']);
        $allDay = new PersonalItem(['kind' => 'todo', 'title' => 'Rechnung prüfen', 'due_on' => '2026-11-05', 'remind' => '15']);
        $none = new PersonalItem(['kind' => 'todo', 'title' => 'Ohne', 'due_on' => '2026-11-05']);
        $morning = new PersonalItem(['kind' => 'termin', 'title' => 'Morgen', 'due_on' => '2026-11-05', 'start_time' => '11:00', 'remind' => 'morgen']);
        $remindTimed = MeinTag::remindAt($timed);
        $ics = Ics::build([[
            'uid' => 'x@y', 'summary' => 'Test', 'description' => '', 'url' => '', 'start' => new DateTimeImmutable('2026-11-05 14:30', MeinTag::tz()),
            'end' => new DateTimeImmutable('2026-11-05 15:00', MeinTag::tz()), 'all_day' => false, 'warn' => false, 'alarm' => '-PT60M', 'category' => 'Meine Termine und To-dos',
        ]], 'Test');
        $checks = [
            'Tabelle und Spalte' => Schema::hasTable('personal_items') && Schema::hasColumn('users', 'day_mail'),
            'Uhrzeit prüfen' => MeinTag::validTime('09:05') && ! MeinTag::validTime('24:00') && ! MeinTag::validTime('9:5') && ! MeinTag::validTime(null),
            'Erinnerung mit Uhrzeit' => $remindTimed !== null && $remindTimed->format('H:i') === '13:30',
            'Erinnerung ohne Uhrzeit: am Morgen' => MeinTag::remindAt($allDay)?->format('H:i') === '08:00' && MeinTag::remindAt($none) === null,
            'Alarm im Kalender-Abo' => MeinTag::icsTrigger($timed) === '-PT60M' && MeinTag::icsTrigger($allDay) === 'PT8H' && MeinTag::icsTrigger($morning) === '-PT180M',
            'ICS mit Alarm und Ende' => str_contains($ics, 'TRIGGER:-PT60M') && str_contains($ics, 'DTEND:') && ! str_contains($ics, 'TRIGGER:-P1D'),
            'Seiten' => Route::has('tag.index') && Route::has('tag.store') && Route::has('tag.done') && Route::has('tag.move') && Route::has('tag.mail'),
            'Ansicht' => view()->exists('tag.index') && view()->exists('tag.week'),
            'Mitteilungen und Wochenüberblick' => Schema::hasTable('push_subscriptions') && Schema::hasTable('tag_notifications') && Schema::hasColumn('users', 'week_mail')
                && Route::has('push.subscribe') && Route::has('push.test') && Route::has('tag.week') && Route::has('tag.weekmail')
                && class_exists(\Minishlink\WebPush\WebPush::class) && array_key_exists('tag:takt', \Illuminate\Support\Facades\Artisan::all()),
            'Wochenbeginn' => \App\Support\Tag\Woche::monday('2026-11-08') === '2026-11-02' && \App\Support\Tag\Woche::monday('2026-11-09') === '2026-11-09',
        ];
        $this->line('Selbsttest Paket 42 (Mein Tag)');
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
