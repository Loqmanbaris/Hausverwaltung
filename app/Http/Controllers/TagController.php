<?php

namespace App\Http\Controllers;

use App\Models\PersonalItem;
use App\Models\PersonalItemMark;
use App\Support\Tag\MeinTag;
use App\Support\Tag\Vorlagen;
use DateTimeImmutable;
use Illuminate\Http\Request;

/** „Mein Tag“ (Paket 42): Tages- und Wochenansicht, eigene Termine und To-dos, Menü-Ansicht. */
class TagController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $today = (new DateTimeImmutable('now', MeinTag::tz()))->format('Y-m-d');
        $date = $this->date((string) $request->query('datum'), $today);
        $week = $request->query('ansicht') === 'woche';
        if ($week) {
            $from = date('Y-m-d', strtotime($date.' monday this week'));
            $to = date('Y-m-d', strtotime($from.' +6 days'));
            $prev = date('Y-m-d', strtotime($from.' -7 days'));
            $next = date('Y-m-d', strtotime($from.' +7 days'));
        } else {
            $from = $to = $date;
            $prev = date('Y-m-d', strtotime($date.' -1 day'));
            $next = date('Y-m-d', strtotime($date.' +1 day'));
        }

        return view('tag.index', [
            'week' => $week, 'date' => $date, 'from' => $from, 'to' => $to, 'prev' => $prev, 'next' => $next, 'today' => $today,
            'days' => MeinTag::entries($user, $from, $to),
            'overdue' => $date === $today || $week ? MeinTag::overdue($user, $today) : collect(),
            'reminders' => MeinTag::dueReminders($user),
            'dayMail' => (bool) $user->day_mail,
            'item' => null,
            'greeting' => MeinTag::greeting(),
            'tiles' => MeinTag::tiles($user, $today),
            'openTodos' => MeinTag::openCount($user, $today),
            'templates' => Vorlagen::TEMPLATES,
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
            'week' => false, 'date' => $row->due_on->format('Y-m-d'), 'from' => $row->due_on->format('Y-m-d'), 'to' => $row->due_on->format('Y-m-d'),
            'prev' => null, 'next' => null, 'today' => $today, 'days' => [], 'overdue' => collect(), 'reminders' => [],
            'dayMail' => (bool) $request->user()->day_mail, 'item' => $row,
            'greeting' => '', 'tiles' => [], 'openTodos' => 0, 'templates' => [],
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

            return redirect()->back();
        }
        $row->update(['done_at' => $row->done_at === null ? now() : null]);

        return redirect()->back();
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

        return redirect()->route('tag.index', ['ansicht' => 'woche'])->with('status', Vorlagen::TEMPLATES[$key]['title'].': '.implode(', ', $parts).'.');
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
