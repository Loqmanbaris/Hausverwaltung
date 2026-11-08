<?php

namespace App\Support\Tag;

use App\Models\PersonalItem;
use App\Models\PersonalItemMark;
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

    /** Wiederholung: Schlüssel => Bezeichnung (Regel:alle n). */
    public const REPEATS = [
        '' => 'Nicht wiederholen',
        'weekly:1' => 'Jede Woche',
        'weekly:2' => 'Alle 2 Wochen',
        'monthly:1' => 'Jeden Monat (gleiches Datum)',
        'nth:1' => 'Jeden Monat (gleicher Wochentag, z. B. 2. Montag)',
        'nth:6' => 'Alle 6 Monate (gleicher Wochentag)',
        'yearly:1' => 'Jedes Jahr',
    ];

    public const REPEATS_SHORT = [
        'weekly:1' => 'jede Woche', 'weekly:2' => 'alle 2 Wochen', 'monthly:1' => 'jeden Monat', 'nth:1' => 'jeden Monat (Wochentag)',
        'nth:6' => 'alle 6 Monate', 'yearly:1' => 'jedes Jahr',
    ];

    /** @return array{0: ?string, 1: int} Regel und Abstand aus dem Formularwert */
    public static function parseRepeat(?string $v): array
    {
        if ($v === null || $v === '' || ! array_key_exists($v, self::REPEATS)) {
            return [null, 1];
        }
        [$rule, $every] = explode(':', $v);

        return [$rule, max(1, (int) $every)];
    }

    public static function isRecurring(PersonalItem $i): bool
    {
        return $i->repeat !== null && $i->repeat !== '';
    }

    public static function repeatKey(PersonalItem $i): string
    {
        return self::isRecurring($i) ? $i->repeat.':'.max(1, (int) $i->repeat_every) : '';
    }

    public static function repeatShort(PersonalItem $i): string
    {
        return self::REPEATS_SHORT[self::repeatKey($i)] ?? '';
    }

    /**
     * Alle Tage einer Serie (oder der Einzeltermin) zwischen zwei Tagen, einschließlich.
     *
     * @return array<int, string> Tage als Y-m-d
     */
    public static function occurrences(PersonalItem $i, string $from, string $to): array
    {
        $start = $i->due_on->format('Y-m-d');
        if (! self::isRecurring($i)) {
            return $start >= $from && $start <= $to ? [$start] : [];
        }
        $until = $i->repeat_until?->format('Y-m-d');
        $end = ($until !== null && $until < $to) ? $until : $to;
        if ($start > $end) {
            return [];
        }
        $n = max(1, (int) $i->repeat_every);
        $utc = new DateTimeZone('UTC');
        $out = [];
        if ($i->repeat === 'weekly') {
            $step = 7 * $n;
            $s = new DateTimeImmutable($start, $utc);
            $k = 0;
            if ($from > $start) {
                $gap = (int) $s->diff(new DateTimeImmutable($from, $utc))->days;
                $k = intdiv($gap, $step);
            }
            for ($guard = 0; $guard < 2000; $guard++, $k++) {
                $d = $s->modify('+'.($k * $step).' days')->format('Y-m-d');
                if ($d > $end) {
                    break;
                }
                if ($d >= $from) {
                    $out[] = $d;
                }
            }

            return $out;
        }
        $stepMonths = match ($i->repeat) {
            'yearly' => 12 * $n,
            default => $n, // monthly, nth
        };
        [$y, $m, $d] = array_map('intval', explode('-', $start));
        $weekday = (int) date('w', mktime(0, 0, 0, $m, $d, $y));
        $nth = intdiv($d - 1, 7) + 1;
        $base = $y * 12 + ($m - 1);
        $k = 0;
        if ($from > $start) {
            [$fy, $fm] = array_map('intval', explode('-', $from));
            $k = max(0, intdiv(($fy * 12 + $fm - 1) - $base, $stepMonths) - 1);
        }
        for ($guard = 0; $guard < 2000; $guard++, $k++) {
            $total = $base + $k * $stepMonths;
            $yy = intdiv($total, 12);
            $mm = $total % 12 + 1;
            $dim = (int) date('t', mktime(0, 0, 0, $mm, 1, $yy));
            if ($i->repeat === 'nth') {
                $firstWeekday = (int) date('w', mktime(0, 0, 0, $mm, 1, $yy));
                $dd = 1 + (($weekday - $firstWeekday + 7) % 7) + ($nth - 1) * 7;
                if ($dd > $dim) {
                    $dd -= 7; // gibt es den fünften Wochentag nicht, gilt der letzte
                }
            } else {
                $dd = min($d, $dim); // 31. im Februar: letzter Tag des Monats
            }
            $day = sprintf('%04d-%02d-%02d', $yy, $mm, $dd);
            if ($day > $end) {
                break;
            }
            if ($day >= $from) {
                $out[] = $day;
            }
        }

        return $out;
    }

    /** Beginn als Zeitpunkt; ohne Uhrzeit der Anfang des Tages. $day = Tag einer Serie, sonst der Tag des Eintrags. */
    public static function startOf(PersonalItem $i, ?string $day = null): DateTimeImmutable
    {
        $time = $i->start_time ?: '00:00';

        return new DateTimeImmutable(($day ?? $i->due_on->format('Y-m-d')).' '.$time, self::tz());
    }

    /** Zeitpunkt der Erinnerung oder null. Ohne Uhrzeit zählt jede Erinnerung als „am Morgen“. */
    public static function remindAt(PersonalItem $i, ?string $day = null): ?DateTimeImmutable
    {
        $r = (string) $i->remind;
        if ($r === '' || ! array_key_exists($r, self::REMINDERS)) {
            return null;
        }
        $day ??= $i->due_on->format('Y-m-d');
        if ($r === 'morgen' || ! $i->start_time) {
            return new DateTimeImmutable($day.' '.self::MORNING, self::tz());
        }

        return self::startOf($i, $day)->modify('-'.(int) $r.' minutes');
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

    private static function plain($q)
    {
        return $q->where(fn ($w) => $w->whereNull('repeat')->orWhere('repeat', ''));
    }

    /** Einträge des Benutzers, die zwischen den Tagen vorkommen können: Einzeltermine im Zeitraum und Serien, die dann laufen. */
    private static function itemsBetween(User $user, string $from, string $to)
    {
        return PersonalItem::query()->where('user_id', $user->id)->where(function ($q) use ($from, $to) {
            $q->where(fn ($a) => self::plain($a)->whereDate('due_on', '>=', $from)->whereDate('due_on', '<=', $to))
                ->orWhere(fn ($b) => $b->whereNotNull('repeat')->where('repeat', '!=', '')->whereDate('due_on', '<=', $to)
                    ->where(fn ($c) => $c->whereNull('repeat_until')->orWhereDate('repeat_until', '>=', $from)));
        })->get();
    }

    /** @return array<string, string> "id|Tag" => done|skip */
    private static function marks($items, string $from, string $to): array
    {
        $ids = $items->filter(fn ($i) => self::isRecurring($i))->pluck('id')->all();
        if ($ids === []) {
            return [];
        }
        $out = [];
        foreach (PersonalItemMark::query()->whereIn('personal_item_id', $ids)->whereDate('on_date', '>=', $from)->whereDate('on_date', '<=', $to)->get() as $m) {
            $out[$m->personal_item_id.'|'.$m->on_date->format('Y-m-d')] = $m->kind;
        }

        return $out;
    }

    /**
     * Alles zwischen zwei Tagen: eigene Einträge (auch Serien) und automatische Fristen.
     *
     * @return array<string, array<int, array<string, mixed>>> Tag => Zeilen
     */
    public static function entries(User $user, string $from, string $to): array
    {
        $days = [];
        $items = self::itemsBetween($user, $from, $to);
        $marks = self::marks($items, $from, $to);
        foreach ($items as $i) {
            $recurring = self::isRecurring($i);
            foreach (self::occurrences($i, $from, $to) as $d) {
                $mark = $marks[$i->id.'|'.$d] ?? null;
                if ($mark === 'skip') {
                    continue;
                }
                $days[$d][] = [
                    'source' => 'own', 'id' => $i->id, 'date' => $d, 'kind' => $i->kind, 'title' => $i->title, 'detail' => (string) $i->notes,
                    'time' => $i->start_time ?: null, 'end' => $i->end_time ?: null,
                    'done' => $recurring ? $mark === 'done' : $i->done_at !== null,
                    'url' => '', 'tone' => 'info', 'remind' => (string) $i->remind,
                    'recurring' => $recurring, 'repeat' => $recurring ? self::repeatShort($i) : '',
                ];
            }
        }
        foreach (Kalender::events($user, null, $from, false) as $e) {
            $d = $e['start']->format('Y-m-d');
            if ($d < $from || $d > $to) {
                continue;
            }
            $days[$d][] = [
                'source' => 'frist', 'id' => null, 'date' => $d, 'kind' => 'frist', 'title' => $e['summary'], 'detail' => '',
                'time' => $e['all_day'] ? null : $e['start']->format('H:i'), 'end' => null, 'done' => false,
                'url' => $e['url'], 'tone' => $e['warn'] ? 'warn' : 'info', 'remind' => '', 'recurring' => false, 'repeat' => '',
            ];
        }
        foreach ($days as &$rows) {
            self::sortRows($rows);
        }
        unset($rows);
        ksort($days);

        return $days;
    }

    /** Offene einmalige To-dos aus der Vergangenheit. Serien zählen hier nicht: Ein verpasster Tag einer Serie wird nicht angehäuft. */
    public static function overdue(User $user, string $today)
    {
        return self::plain(PersonalItem::query()->where('user_id', $user->id)->where('kind', 'todo')->whereNull('done_at'))
            ->whereDate('due_on', '<', $today)->orderBy('due_on')->get();
    }

    /** Offene eigene To-dos für heute und früher (für die Zahl im Menü), Serien nur mit dem heutigen Tag. */
    public static function openCount(User $user, ?string $today = null): int
    {
        if (! Schema::hasTable('personal_items')) {
            return 0;
        }
        $today ??= date('Y-m-d');
        $n = (int) self::plain(PersonalItem::query()->where('user_id', $user->id)->where('kind', 'todo')->whereNull('done_at'))->whereDate('due_on', '<=', $today)->count();
        $series = PersonalItem::query()->where('user_id', $user->id)->where('kind', 'todo')->whereNotNull('repeat')->where('repeat', '!=', '')->whereDate('due_on', '<=', $today)->get();
        $marks = self::marks($series, $today, $today);
        foreach ($series as $i) {
            if (self::occurrences($i, $today, $today) !== [] && ! isset($marks[$i->id.'|'.$today])) {
                $n++;
            }
        }

        return $n;
    }

    /** Erinnerungen, die jetzt fällig sind: Zeitpunkt erreicht, Eintrag noch nicht vorbei und nicht erledigt. */
    public static function dueReminders(User $user, ?DateTimeImmutable $now = null): array
    {
        $now ??= new DateTimeImmutable('now', self::tz());
        $today = $now->format('Y-m-d');
        $to = date('Y-m-d', strtotime($today.' +2 days'));
        $out = [];
        $items = self::itemsBetween($user, $today, $to)->filter(fn ($i) => (string) $i->remind !== '' && $i->done_at === null);
        $marks = self::marks($items, $today, $to);
        foreach ($items as $i) {
            foreach (self::occurrences($i, $today, $to) as $d) {
                if (isset($marks[$i->id.'|'.$d])) {
                    continue;
                }
                $at = self::remindAt($i, $d);
                if ($at === null || $at > $now) {
                    continue;
                }
                $end = $i->start_time ? self::startOf($i, $d)->modify('+1 hour') : new DateTimeImmutable($d.' 23:59', self::tz());
                if ($i->end_time) {
                    $end = new DateTimeImmutable($d.' '.$i->end_time, self::tz());
                }
                if ($end < $now) {
                    continue;
                }
                $c = $i->replicate();
                $c->id = $i->id; // Kennung behalten (Mitteilung nur einmal je Eintrag und Tag)
                $c->due_on = $d;
                $out[] = $c;
            }
        }
        usort($out, fn ($a, $b) => self::startOf($a) <=> self::startOf($b));

        return $out;
    }

    /** iCalendar-Regel einer Serie. */
    public static function icsRule(PersonalItem $i): ?string
    {
        if (! self::isRecurring($i)) {
            return null;
        }
        $n = max(1, (int) $i->repeat_every);
        $rule = match ($i->repeat) {
            'weekly' => 'FREQ=WEEKLY;INTERVAL='.$n,
            'monthly' => 'FREQ=MONTHLY;INTERVAL='.$n,
            'yearly' => 'FREQ=YEARLY;INTERVAL='.$n,
            'nth' => (function () use ($i, $n) {
                $d = (int) $i->due_on->format('j');
                $nth = intdiv($d - 1, 7) + 1;
                $code = ['SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA'][(int) $i->due_on->format('w')];

                return 'FREQ=MONTHLY;INTERVAL='.$n.';BYDAY='.($nth >= 5 ? '-1' : (string) $nth).$code;
            })(),
            default => null,
        };
        if ($rule !== null && $i->repeat_until !== null) {
            $until = $i->repeat_until->format('Y-m-d');
            $rule .= ';UNTIL='.($i->start_time
                ? (new DateTimeImmutable($until.' 23:59:59', self::tz()))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z')
                : str_replace('-', '', $until));
        }

        return $rule;
    }

    /** Eigene Einträge als Kalender-Abo-Termine. Serien als ein Termin mit Wiederholung, ausgelassene und erledigte Tage als Ausnahme. */
    public static function feedEvents(User $user, string $today): array
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'crm';
        $out = [];
        $from = date('Y-m-d', strtotime($today.' -7 days'));
        $to = date('Y-m-d', strtotime($today.' +'.Kalender::DAYS.' days'));
        $items = self::itemsBetween($user, $from, $to)->filter(fn ($i) => self::isRecurring($i) || $i->done_at === null);
        $marks = [];
        foreach ($items->filter(fn ($i) => self::isRecurring($i)) as $i) {
            foreach (PersonalItemMark::query()->where('personal_item_id', $i->id)->get() as $m) {
                $marks[$i->id][] = $m->on_date->format('Y-m-d');
            }
        }
        foreach ($items as $i) {
            $start = self::startOf($i);
            $end = $i->end_time ? new DateTimeImmutable($i->due_on->format('Y-m-d').' '.$i->end_time, self::tz()) : null;
            $exdates = array_map(fn ($d) => self::startOf($i, $d), $marks[$i->id] ?? []);
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
                'rrule' => self::icsRule($i),
                'exdates' => $exdates,
                'category' => Kalender::CATEGORIES['eigene'],
            ];
        }

        return $out;
    }

    public static function greeting(?DateTimeImmutable $now = null): string
    {
        $h = (int) ($now ?? new DateTimeImmutable('now', self::tz()))->format('G');

        return $h < 11 ? 'Guten Morgen' : ($h < 17 ? 'Guten Tag' : 'Guten Abend');
    }

    /**
     * Zähl-Kacheln für „Mein Tag“. Jede Zahl ist einzeln abgesichert; fällt eine aus, zeigt die Kachel 0.
     *
     * @return array<int, array{label: string, count: int, color: string, url: string}>
     */
    public static function tiles(User $user, string $today): array
    {
        $safe = function (callable $f): int {
            try {
                return max(0, (int) $f());
            } catch (\Throwable $e) {
                report($e);

                return 0;
            }
        };
        $week = [];
        try {
            $week = self::entries($user, $today, date('Y-m-d', strtotime($today.' +6 days')));
        } catch (\Throwable $e) {
            report($e);
        }
        $count = fn (array $days, callable $keep) => array_sum(array_map(fn ($rows) => count(array_filter($rows, $keep)), $days));

        return [
            ['label' => 'Termine heute', 'color' => 'green', 'url' => route('tag.index'),
                'count' => $count([$week[$today] ?? []], fn ($r) => $r['kind'] !== 'todo' && ! $r['done'])],
            ['label' => 'To-dos offen', 'color' => 'brown', 'url' => route('tag.index'), 'count' => $safe(fn () => self::openCount($user, $today))],
            ['label' => 'Fristen diese Woche', 'color' => 'white', 'url' => route('tag.index').'#woche',
                'count' => $count($week, fn ($r) => $r['source'] === 'frist')],
            ['label' => 'Mahnungen vorgeschlagen', 'color' => 'orange', 'url' => route('dunning.index'),
                'count' => $safe(fn () => \App\Support\Dunning\Mahnwesen::proposalCountFor($user))],
            ['label' => 'Neue Anfragen', 'color' => 'purple', 'url' => route('inquiries.index'), 'count' => $safe(fn () => \App\Models\Inquiry::newCountFor($user))],
            ['label' => 'Schäden gemeldet', 'color' => 'teal', 'url' => route('workorders.index'),
                'count' => $safe(fn () => \App\Models\WorkOrder::visibleTo($user)->where('status', 'gemeldet')->count())],
            ['label' => 'Kontoumsätze offen', 'color' => 'pink', 'url' => route('bank.transactions'),
                'count' => $safe(fn () => \App\Support\Bank\BankService::openCountFor($user))],
        ];
    }

    /**
     * Mieteingang des laufenden Monats: wie viele Mieten bezahlt sind, was noch offen ist, ob heute etwas fällig ist.
     *
     * @return array{total: int, paid: int, open: int, open_sum: float, due_today: int, late: int}|null null ohne Mieten
     */
    public static function rentStatus(User $user, string $today): ?array
    {
        try {
            \App\Support\Rent\RentLedger::syncAllIfStale();
            $period = substr($today, 0, 7).'-01';
            $charges = \App\Models\RentCharge::visibleTo($user)->where('rent_charges.kind', 'miete')->whereDate('period', $period)
                ->with(['lease.unit.property', 'lease.tenant'])->get(['rent_charges.id', 'rent_charges.lease_id', 'total', 'paid_amount', 'due_date']);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
        if ($charges->isEmpty()) {
            return null;
        }
        $open = $charges->filter(fn ($c) => (float) $c->paid_amount + 0.004 < (float) $c->total);

        return [
            'total' => $charges->count(),
            'paid' => $charges->count() - $open->count(),
            'open' => $open->count(),
            'open_sum' => round((float) $open->sum(fn ($c) => (float) $c->total - (float) $c->paid_amount), 2),
            'due_today' => $charges->filter(fn ($c) => $c->due_date && $c->due_date->format('Y-m-d') === $today)->count(),
            'late' => $open->filter(fn ($c) => $c->due_date && $c->due_date->format('Y-m-d') < $today)->count(),
            // Offene Mieten zum direkten Abhaken in „Mein Tag“
            'items' => $open->sortBy(fn ($c) => [$c->due_date?->format('Y-m-d'), $c->lease?->unit?->property?->name, $c->lease?->unit?->label])->take(25)
                ->map(fn ($c) => ['id' => $c->id, 'lease_id' => (int) $c->lease_id, 'title' => $c->lease?->title() ?? 'Mietvertrag', 'open' => round((float) $c->total - (float) $c->paid_amount, 2),
                    'due' => $c->due_date?->format('Y-m-d'), 'late' => $c->due_date && $c->due_date->format('Y-m-d') < $today])->values()->all(),
        ];
    }

    /**
     * Mietverträge, bei denen in den nächsten Tagen etwas ansteht: Vertragsende und mögliche Indexmieterhöhung.
     *
     * @return array<int, array{title: string, sub: string, url: string, days: int, kind: string}>
     */
    public static function contractDates(User $user, string $today, int $days = 30): array
    {
        $out = [];
        try {
            $d = \App\Support\Rent\RentDashboard::forUser($user);
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
        if ($d === null) {
            return [];
        }
        $limit = date('Y-m-d', strtotime($today.' +'.$days.' days'));
        $in = fn (string $date) => (int) round((strtotime($date) - strtotime($today)) / 86400);
        foreach ($d['ending'] as $l) {
            $end = $l->end_date->format('Y-m-d');
            if ($end <= $limit) {
                $n = $in($end);
                $out[] = ['kind' => 'ende', 'title' => 'Mietvertrag endet: '.$l->title(), 'days' => $n,
                    'sub' => 'am '.$l->end_date->format('d.m.Y').($n === 0 ? ' (heute)' : ' (in '.$n.' '.($n === 1 ? 'Tag' : 'Tagen').')').' – Übergabe und Kaution planen',
                    'url' => route('leases.show', $l)];
            }
        }
        foreach ($d['index_due'] as $x) {
            $from = $x['since']->copy()->addYear()->format('Y-m-d');
            if ($from <= $limit) {
                $n = $in($from);
                $out[] = ['kind' => 'index', 'title' => 'Indexmiete prüfen: '.$x['lease']->title(), 'days' => $n,
                    'sub' => ($n <= 0 ? 'Erhöhung seit '.date('d.m.Y', strtotime($from)).' möglich' : 'Erhöhung ab '.date('d.m.Y', strtotime($from)).' möglich (in '.$n.' '.($n === 1 ? 'Tag' : 'Tagen').')'),
                    'url' => \Illuminate\Support\Facades\Route::has('rent_increase.create') ? route('rent_increase.create', $x['lease']) : route('leases.show', $x['lease'])];
            }
        }
        usort($out, fn ($a, $b) => $a['days'] <=> $b['days']);

        return $out;
    }

    /**
     * Wie entries(), dazu offene Aufträge mit Termin als eigene Zeilen. Die Kalender-Einträge „Handwerker … Uhr“
     * derselben Aufträge fallen weg, damit nichts doppelt steht.
     */
    public static function entriesWithOrders(User $user, string $from, string $to): array
    {
        $all = self::entries($user, $from, $to);
        $orderUrl = rtrim(route('workorders.index'), '/').'/';
        foreach ($all as $d => $rows) {
            $all[$d] = array_values(array_filter($rows, fn ($r) => ! ($r['source'] === 'frist' && str_starts_with((string) ($r['url'] ?? ''), $orderUrl))));
        }
        foreach (self::orderRows($user, $from, $to) as $d => $rows) {
            $all[$d] = array_merge($all[$d] ?? [], $rows);
            usort($all[$d], fn ($a, $b) => strcmp($a['time'] ?? '99', $b['time'] ?? '99'));
        }
        ksort($all);

        return $all;
    }

    /**
     * Offene Aufträge mit Termin als Zeilen für die Zeitleiste (gleiche Form wie entries()).
     *
     * @return array<string, array<int, array>> Datum => Zeilen
     */
    public static function orderRows(User $user, string $from, string $to): array
    {
        $out = [];
        try {
            $orders = \App\Models\WorkOrder::visibleTo($user)->whereIn('status', \App\Models\WorkOrder::OPEN)->whereNotNull('appointment_at')
                ->whereBetween('appointment_at', [$from.' 00:00:00', $to.' 23:59:59'])->with(['property', 'unit', 'vendor'])->orderBy('appointment_at')->get();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }
        foreach ($orders as $o) {
            $at = $o->appointment_at->copy()->timezone('Europe/Berlin');
            $time = $at->format('H:i');
            $out[$at->format('Y-m-d')][] = [
                'source' => 'auftrag', 'id' => $o->id, 'date' => $at->format('Y-m-d'), 'kind' => 'auftrag', 'title' => $o->title,
                'detail' => collect([$o->vendor?->name, $o->property?->name, $o->unit?->label])->filter()->implode(' · '),
                'time' => $time === '00:00' ? null : $time, 'end' => null, 'done' => false, 'recurring' => false,
                'url' => route('workorders.show', $o), 'tone' => in_array($o->priority, ['notfall', 'dringend'], true) ? 'warn' : '',
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
