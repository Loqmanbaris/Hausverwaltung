<?php

namespace App\Http\Controllers;

use App\Models\PersonalItem;
use App\Models\PersonalItemMark;
use App\Models\WorkOrder;
use App\Support\Tag\MeinTag;
use App\Support\Tag\Vorlagen;
use DateTimeImmutable;
use Illuminate\Http\Request;

/** „Mein Tag“ (Paket 42): eine Seite mit Tag, nächsten 7 Tagen und offenen Aufträgen; eigene Termine und To-dos. */
class TagController extends Controller
{
    /**
     * Eine Seite für alles: der gewählte Tag (Standard heute) mit Überfälligem, darunter die nächsten 7 Tage
     * und die offenen Aufträge. Früher drei Reiter (Mein Tag, Kalender, Aufträge), seit 08.11.2026 eine Seite.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $today = (new DateTimeImmutable('now', MeinTag::tz()))->format('Y-m-d');
        $date = $this->date((string) $request->query('datum'), $today);
        $first = date('Y-m-d', strtotime($date.' +1 day'));
        $last = date('Y-m-d', strtotime($date.' +7 days'));

        $all = MeinTag::entriesWithOrders($user, $date, $last);
        $upcoming = array_filter(array_diff_key($all, [$date => true]), fn ($rows) => $rows !== []);
        ksort($upcoming);

        $orders = collect();
        $ordersTotal = 0;
        try {
            $q = WorkOrder::visibleTo($user)->whereIn('status', WorkOrder::OPEN);
            $ordersTotal = (clone $q)->count();
            $orders = $q->with(['property', 'unit', 'vendor'])
                ->orderByRaw("CASE priority WHEN 'notfall' THEN 0 WHEN 'dringend' THEN 1 ELSE 2 END")
                ->orderByRaw('CASE WHEN appointment_at IS NULL THEN 1 ELSE 0 END')->orderBy('appointment_at')->orderByDesc('id')
                ->limit(8)->get();
        } catch (\Throwable $e) {
            report($e);
        }

        // Nur Hinweise, die nicht schon in der Liste stehen, und nur wenn etwas offen ist
        $skip = ['Termine heute', 'To-dos offen', 'Fristen diese Woche', 'Schäden gemeldet', 'Kontoumsätze offen'];
        $alerts = array_values(array_filter(MeinTag::tiles($user, $today), fn ($t) => $t['count'] > 0 && ! in_array($t['label'], $skip, true)));

        return view('tag.index', [
            'date' => $date, 'today' => $today, 'first' => $first, 'last' => $last,
            'prev' => date('Y-m-d', strtotime($date.' -1 day')), 'next' => $first,
            'rows' => $all[$date] ?? [],
            'upcoming' => $upcoming,
            'overdue' => $date === $today ? MeinTag::overdue($user, $today) : collect(),
            'reminders' => MeinTag::dueReminders($user),
            'orders' => $orders, 'ordersTotal' => $ordersTotal,
            'alerts' => $alerts,
            'dayMail' => (bool) $user->day_mail,
            'item' => null,
            'greeting' => MeinTag::greeting(),
            'templates' => Vorlagen::TEMPLATES,
            'rent' => MeinTag::rentStatus($user, $today),
            'bankOpen' => \App\Support\Bank\BankService::openCountFor($user),
            'mailUnread' => $this->safe(fn () => \App\Models\ReceivedEmail::unreadCountFor($user)),
            // Montags: Wochenüberblick als Karte
            'weekCard' => $date === $today && date('N', strtotime($today)) === '1' ? \App\Support\Tag\Woche::overview($user, $today) : null,
            'needsPush' => ! \App\Support\Push\Push::hasDevice($user),
            'vapid' => \App\Support\Push\Push::publicKey(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $item = PersonalItem::query()->create($data + ['user_id' => $request->user()->id]);

        return redirect()->route('tag.index', ['datum' => $item->due_on->format('Y-m-d')])->with('status', ($item->kind === 'todo' ? 'To-do' : 'Termin').' gespeichert.');
    }

    public function edit(Request $request, int $item)
    {
        $row = $this->own($request, $item);
        $today = (new DateTimeImmutable('now', MeinTag::tz()))->format('Y-m-d');

        return view('tag.index', [
            'date' => $row->due_on->format('Y-m-d'), 'today' => $today, 'item' => $row,
            'dayMail' => (bool) $request->user()->day_mail,
        ]);
    }

    public function update(Request $request, int $item)
    {
        $row = $this->own($request, $item);
        $row->update($this->validated($request));

        return redirect()->route('tag.index', ['datum' => $row->due_on->format('Y-m-d')])->with('status', 'Gespeichert.');
    }

    public function done(Request $request, int $item)
    {
        $row = $this->own($request, $item);
        if (MeinTag::isRecurring($row)) {
            $day = $this->occurrence($request, $row);
            $mark = PersonalItemMark::query()->where('personal_item_id', $row->id)->whereDate('on_date', $day)->first();
            if ($mark !== null && $mark->kind === 'done') {
                $mark->delete();
            } else {
                PersonalItemMark::query()->updateOrCreate(['personal_item_id' => $row->id, 'on_date' => $day], ['kind' => 'done']);
            }

            return $request->wantsJson() ? response()->json(['done' => ! ($mark !== null && $mark->kind === 'done')]) : redirect()->back();
        }
        $row->update(['done_at' => $row->done_at === null ? now() : null]);

        return $request->wantsJson() ? response()->json(['done' => $row->done_at !== null]) : redirect()->back();
    }

    /** Überfälliges To-do auf heute oder morgen legen (nur einmalige Einträge). */
    public function move(Request $request, int $item)
    {
        $row = $this->own($request, $item);
        abort_if(MeinTag::isRecurring($row), 404);
        $today = (new DateTimeImmutable('now', MeinTag::tz()))->format('Y-m-d');
        $to = $request->input('auf') === 'morgen' ? date('Y-m-d', strtotime($today.' +1 day')) : $today;
        $row->update(['due_on' => $to]);
        if ($request->wantsJson()) {
            return response()->json(['due_on' => $to]);
        }

        return redirect()->back()->with('status', '„'.$row->title.'“ liegt jetzt auf '.($to === $today ? 'heute' : 'morgen').'.');
    }

    private function safe(callable $f): int
    {
        try {
            return (int) $f();
        } catch (\Throwable $e) {
            report($e);

            return 0;
        }
    }

    /** Einen einzelnen Tag einer Serie auslassen. */
    public function skip(Request $request, int $item)
    {
        $row = $this->own($request, $item);
        abort_unless(MeinTag::isRecurring($row), 404);
        $day = $this->occurrence($request, $row);
        PersonalItemMark::query()->updateOrCreate(['personal_item_id' => $row->id, 'on_date' => $day], ['kind' => 'skip']);

        return redirect()->back()->with('status', 'Dieser Tag wurde ausgelassen. Die Serie läuft weiter.');
    }

    /** Der Tag aus dem Formular muss ein Tag der Serie sein. */
    private function occurrence(Request $request, PersonalItem $row): string
    {
        $day = (string) $request->input('datum');
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1 && MeinTag::occurrences($row, $day, $day) !== [], 404);

        return $day;
    }

    public function destroy(Request $request, int $item)
    {
        $row = $this->own($request, $item);
        $day = $row->due_on->format('Y-m-d');
        $row->delete();

        return redirect()->route('tag.index', ['datum' => $day])->with('status', 'Gelöscht.');
    }

    /** Eine Planvorlage in den eigenen Plan übernehmen. */
    public function template(Request $request, string $key)
    {
        abort_unless(array_key_exists($key, Vorlagen::TEMPLATES), 404);
        $r = Vorlagen::apply($request->user(), $key);
        $parts = [$r['created'].' '.($r['created'] === 1 ? 'Eintrag' : 'Einträge').' angelegt'];
        if ($r['existing'] > 0) {
            $parts[] = $r['existing'].' gab es schon';
        }
        if ($r['past'] > 0) {
            $parts[] = $r['past'].' liegen in der Vergangenheit und wurden übersprungen';
        }

        return redirect()->route('tag.index')->with('status', Vorlagen::TEMPLATES[$key]['title'].': '.implode(', ', $parts).'.');
    }

    /** Wochenüberblick (letzte Woche, offen, diese Woche) als eigene Seite. */
    public function week(Request $request)
    {
        $today = (new DateTimeImmutable('now', MeinTag::tz()))->format('Y-m-d');
        $monday = \App\Support\Tag\Woche::monday($this->date((string) $request->query('datum'), $today));

        return view('tag.week', [
            'o' => \App\Support\Tag\Woche::overview($request->user(), $monday),
            'monday' => $monday,
            'prev' => date('Y-m-d', strtotime($monday.' -7 days')),
            'next' => date('Y-m-d', strtotime($monday.' +7 days')),
            'thisWeek' => $monday === \App\Support\Tag\Woche::monday($today),
            'weekMail' => (bool) ($request->user()->week_mail ?? false),
        ]);
    }

    /** Wochenüberblick per E-Mail ein- oder ausschalten. */
    public function weekMail(Request $request)
    {
        $on = $request->boolean('on');
        $request->user()->forceFill(['week_mail' => $on])->save();

        return redirect()->back()->with('status', $on ? 'Du bekommst den Wochenüberblick montags um 07:30 Uhr per E-Mail.' : 'Wochenüberblick per E-Mail ist aus.');
    }

    /** E-Mail am Morgen ein- oder ausschalten. */
    public function mail(Request $request)
    {
        $on = $request->boolean('on');
        $request->user()->forceFill(['day_mail' => $on])->save();

        return redirect()->back()->with('status', $on ? 'Du bekommst „Mein Tag“ ab morgen früh per E-Mail (wenn etwas ansteht).' : '„Mein Tag“ per E-Mail ist aus.');
    }

    private function own(Request $request, int $id): PersonalItem
    {
        return PersonalItem::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function date(string $value, string $fallback): string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : $fallback;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'kind' => ['required', 'in:todo,termin'],
            'title' => ['required', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'due_on' => ['required', 'date_format:Y-m-d'],
            'start_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'end_time' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'remind' => ['nullable', 'in:morgen,0,15,60,1440'],
            'repeat_rule' => ['nullable', 'in:'.implode(',', array_filter(array_keys(MeinTag::REPEATS)))],
            'repeat_until' => ['nullable', 'date_format:Y-m-d'],
        ], [
            'title.required' => 'Bitte einen Titel eingeben.',
            'due_on.required' => 'Bitte einen Tag wählen.',
            'start_time.regex' => 'Die Uhrzeit muss so aussehen: 14:30.',
            'end_time.regex' => 'Das Ende muss so aussehen: 15:30.',
        ]);
        $data['start_time'] = $data['start_time'] ?? null;
        $data['end_time'] = $data['start_time'] !== null ? ($data['end_time'] ?? null) : null;
        if ($data['end_time'] !== null && $data['end_time'] <= $data['start_time']) {
            $data['end_time'] = null; // Ende vor dem Beginn wird ignoriert
        }
        $data['remind'] = ($data['remind'] ?? '') === '' ? null : $data['remind'];
        $data['notes'] = trim((string) ($data['notes'] ?? '')) ?: null;
        [$rule, $every] = MeinTag::parseRepeat($data['repeat_rule'] ?? null);
        $until = $data['repeat_until'] ?? null;
        $data['repeat'] = $rule;
        $data['repeat_every'] = $every;
        $data['repeat_until'] = ($rule !== null && $until !== null && $until >= $data['due_on']) ? $until : null;
        unset($data['repeat_rule']);

        return $data;
    }
}
