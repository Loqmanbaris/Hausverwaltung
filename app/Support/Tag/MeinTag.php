<?php

namespace App\Support\Tag;

use App\Models\PersonalItem;
use App\Models\User;
use App\Support\Calendar\Kalender;
use App\Support\Mail\MailService;
use App\Support\Mail\MailSettings;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * „Mein Tag“ (Paket 42): eigene Termine und To-dos mit Tag und Uhrzeit, dazu die automatischen Fristen aus dem CRM.
 * Erinnerungen: im Kalender-Abo (Alarm im Handy-Kalender), als Hinweis in „Mein Tag“ und optional morgens per E-Mail.
 * Der tägliche Lauf bei IONOS kann keine Minutentermine auslösen, deshalb gibt es keine E-Mail zur Minute.
 */
class MeinTag
{
    public const KINDS = ['todo' => 'To-do', 'termin' => 'Termin'];

    /** Schlüssel => Bezeichnung */
    public const REMINDERS = [
        '' => 'Keine Erinnerung',
        'morgen' => 'Am Morgen des Tages (8:00 Uhr)',
        '0' => 'Zur Uhrzeit',
        '15' => '15 Minuten vorher',
        '60' => '1 Stunde vorher',
        '1440' => '1 Tag vorher',
    ];

    public const MORNING = '08:00';

    public static function tz(): DateTimeZone
    {
        return new DateTimeZone((string) config('app.timezone', 'Europe/Berlin'));
    }

    public static function validTime(?string $t): bool
    {
        return $t !== null && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $t) === 1;
    }

    /** Beginn als Zeitpunkt; ohne Uhrzeit der Anfang des Tages. */
    public static function startOf(PersonalItem $i): DateTimeImmutable
    {
        $time = $i->start_time ?: '00:00';

        return new DateTimeImmutable($i->due_on->format('Y-m-d').' '.$time, self::tz());
    }

    /** Zeitpunkt der Erinnerung oder null. Ohne Uhrzeit zählt jede Erinnerung als „am Morgen“. */
    public static function remindAt(PersonalItem $i): ?DateTimeImmutable
    {
        $r = (string) $i->remind;
        if ($r === '' || ! array_key_exists($r, self::REMINDERS)) {
            return null;
        }
        $day = $i->due_on->format('Y-m-d');
        if ($r === 'morgen' || ! $i->start_time) {
            return new DateTimeImmutable($day.' '.self::MORNING, self::tz());
        }

        return self::startOf($i)->modify('-'.(int) $r.' minutes');
    }

    /** Alarm für das Kalender-Abo (iCalendar-TRIGGER). */
    public static function icsTrigger(PersonalItem $i): ?string
    {
        $at = self::remindAt($i);
        if ($at === null) {
            return null;
        }
        if (! $i->start_time) {
            return 'PT8H'; // ganztägig: 8 Stunden nach Mitternacht
        }
        $minutes = max(0, (int) round((self::startOf($i)->getTimestamp() - $at->getTimestamp()) / 60));

        return '-PT'.$minutes.'M';
    }

    /** Sortierung innerhalb eines Tages: erst mit Uhrzeit, dann ohne. */
    private static function sortRows(array &$rows): void
    {
        usort($rows, fn ($a, $b) => [$a['time'] === null ? 1 : 0, (string) $a['time'], $a['title']] <=> [$b['time'] === null ? 1 : 0, (string) $b['time'], $b['title']]);
    }

    /**
     * Alles zwischen zwei Tagen: eigene Einträge und automatische Fristen.
     *
     * @return array<string, array<int, array<string, mixed>>> Tag => Zeilen
     */
    public static function entries(User $user, string $from, string $to): array
    {
        $days = [];
        $items = PersonalItem::query()->where('user_id', $user->id)->whereDate('due_on', '>=', $from)->whereDate('due_on', '<=', $to)->get();
        foreach ($items as $i) {
            $days[$i->due_on->format('Y-m-d')][] = [
                'source' => 'own', 'id' => $i->id, 'kind' => $i->kind, 'title' => $i->title, 'detail' => (string) $i->notes,
                'time' => $i->start_time ?: null, 'end' => $i->end_time ?: null, 'done' => $i->done_at !== null,
                'url' => '', 'tone' => 'info', 'remind' => (string) $i->remind,
            ];
        }
        foreach (Kalender::events($user, null, $from, false) as $e) {
            $d = $e['start']->format('Y-m-d');
            if ($d < $from || $d > $to) {
                continue;
            }
            $days[$d][] = [
                'source' => 'frist', 'id' => null, 'kind' => 'frist', 'title' => $e['summary'], 'detail' => '',
                'time' => $e['all_day'] ? null : $e['start']->format('H:i'), 'end' => null, 'done' => false,
                'url' => $e['url'], 'tone' => $e['warn'] ? 'warn' : 'info', 'remind' => '',
            ];
        }
        foreach ($days as &$rows) {
            self::sortRows($rows);
        }
        unset($rows);
        ksort($days);

        return $days;
    }

    /** Offene To-dos aus der Vergangenheit. */
    public static function overdue(User $user, string $today)
    {
        return PersonalItem::query()->where('user_id', $user->id)->where('kind', 'todo')->whereNull('done_at')->whereDate('due_on', '<', $today)->orderBy('due_on')->get();
    }

    /** Offene eigene To-dos für heute und früher (für die Zahl im Menü). */
    public static function openCount(User $user, ?string $today = null): int
    {
        if (! Schema::hasTable('personal_items')) {
            return 0;
        }
        $today ??= date('Y-m-d');

        return (int) PersonalItem::query()->where('user_id', $user->id)->where('kind', 'todo')->whereNull('done_at')->whereDate('due_on', '<=', $today)->count();
    }

    /** Erinnerungen, die jetzt fällig sind: Zeitpunkt erreicht, Eintrag noch nicht vorbei und nicht erledigt. */
    public static function dueReminders(User $user, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', self::tz());
        $today = $now->format('Y-m-d');
        $out = [];
        $items = PersonalItem::query()->where('user_id', $user->id)->whereNull('done_at')->whereNotNull('remind')->where('remind', '!=', '')
            ->whereDate('due_on', '>=', $today)->whereDate('due_on', '<=', date('Y-m-d', strtotime($today.' +2 days')))->get();
        foreach ($items as $i) {
            $at = self::remindAt($i);
            if ($at === null || $at > $now) {
                continue;
            }
            $end = $i->start_time ? self::startOf($i)->modify('+1 hour') : new DateTimeImmutable($today.' 23:59', self::tz());
            if ($i->end_time) {
                $end = new DateTimeImmutable($i->due_on->format('Y-m-d').' '.$i->end_time, self::tz());
            }
            if ($end < $now) {
                continue;
            }
            $out[] = $i;
        }
        usort($out, fn ($a, $b) => self::startOf($a) <=> self::startOf($b));

        return $out;
    }

    /** Eigene Einträge als Kalender-Abo-Termine. */
    public static function feedEvents(User $user, string $today): array
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'crm';
        $out = [];
        $items = PersonalItem::query()->where('user_id', $user->id)->whereNull('done_at')
            ->whereDate('due_on', '>=', date('Y-m-d', strtotime($today.' -7 days')))->whereDate('due_on', '<=', date('Y-m-d', strtotime($today.' +'.Kalender::DAYS.' days')))->get();
        foreach ($items as $i) {
            $start = self::startOf($i);
            $end = $i->end_time ? new DateTimeImmutable($i->due_on->format('Y-m-d').' '.$i->end_time, self::tz()) : null;
            $out[] = [
                'uid' => 'tag-'.$i->id.'@'.$host,
                'summary' => ($i->kind === 'todo' ? 'To-do: ' : '').$i->title,
                'description' => (string) $i->notes,
                'url' => route('tag.index', ['datum' => $i->due_on->format('Y-m-d')]),
                'start' => $start,
                'end' => $end !== null && $end > $start ? $end : null,
                'all_day' => ! $i->start_time,
                'warn' => false,
                'alarm' => self::icsTrigger($i),
                'category' => Kalender::CATEGORIES['eigene'],
            ];
        }

        return $out;
    }

    // ------------------------------------------------------------------ E-Mail am Morgen

    public static function dayMailBody(User $user, string $today): ?string
    {
        $rows = self::entries($user, $today, $today)[$today] ?? [];
        $late = self::overdue($user, $today);
        if ($rows === [] && $late->isEmpty()) {
            return null;
        }
        $lines = ['Guten Morgen '.($user->name ?: '').',', '', 'dein Tag am '.date('d.m.Y', strtotime($today)).':', ''];
        foreach ($rows as $r) {
            if ($r['done']) {
                continue;
            }
            $lines[] = ($r['time'] ? $r['time'].' Uhr  ' : '           ').($r['source'] === 'frist' ? 'Frist: ' : ($r['kind'] === 'todo' ? 'To-do: ' : '')).$r['title'];
        }
        if (! $late->isEmpty()) {
            $lines[] = '';
            $lines[] = 'Überfällige To-dos:';
            foreach ($late as $i) {
                $lines[] = '  '.$i->due_on->format('d.m.').'  '.$i->title;
            }
        }
        $lines[] = '';
        $lines[] = 'Öffnen: '.route('tag.index');

        return implode("\n", $lines);
    }

    /** Täglicher Lauf: schickt „Mein Tag“ an alle Benutzer, die es eingeschaltet haben. Gibt die Zahl der E-Mails zurück. */
    public static function sendDayMails(?string $today = null): int
    {
        if (! Schema::hasTable('personal_items') || ! Schema::hasColumn('users', 'day_mail')) {
            return 0;
        }
        if (MailService::$fake === null && ! MailSettings::configured()) {
            return 0;
        }
        $today ??= date('Y-m-d');
        $n = 0;
        foreach (User::query()->where('day_mail', true)->where('is_active', true)->get() as $u) {
            if (! MailService::validAddress((string) $u->email) || ! Cache::add('daymail:'.$u->id.':'.$today, 1, 86400)) {
                continue;
            }
            $body = self::dayMailBody($u, $today);
            if ($body === null) {
                continue;
            }
            try {
                MailService::send([$u->email], 'Mein Tag am '.date('d.m.Y', strtotime($today)), $body, [], ['kind' => 'hinweis'], null, false);
                $n++;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $n;
    }
}
