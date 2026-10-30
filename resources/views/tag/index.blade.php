@extends('layouts.app')
@section('title', 'Mein Tag')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Support\Tag\MeinTag;
  $wd = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
  $label = fn ($d) => $wd[(int) date('w', strtotime($d))].', '.date('d.m.Y', strtotime($d));
  $box = 'padding:.5rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;width:100%;box-sizing:border-box';
  $editing = $item !== null;
  $val = function (string $key, $default = '') use ($item, $editing) {
      if (! $editing) {
          return old($key, $default);
      }
      $current = match ($key) {
          'due_on' => $item->due_on->format('Y-m-d'),
          default => $item->{$key},
      };
      return old($key, $current ?? $default);
  };
  $formDate = $editing ? $item->due_on->format('Y-m-d') : $date;
@endphp
<style>
  .mt-bar{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:.4rem 0 1rem}
  .mt-bar .sd-btn{white-space:nowrap}
  .mt-day{margin-bottom:1rem}
  .mt-day h2{margin:0 0 .5rem;font-size:1.05rem}
  .mt-day.today h2{color:var(--brand,#1f6feb)}
  .mt-row{display:flex;gap:.6rem;align-items:flex-start;padding:.55rem 0;border-top:1px solid #e5e8ec}
  .mt-row:first-of-type{border-top:0}
  .mt-time{min-width:3.6rem;font-variant-numeric:tabular-nums;color:#374151;font-weight:600}
  .mt-main{flex:1;min-width:0;overflow-wrap:anywhere}
  .mt-done .mt-title{text-decoration:line-through;color:#8a94a0}
  .mt-tag{display:inline-block;font-size:.72rem;padding:.05rem .4rem;border-radius:1rem;background:#eef1f5;color:#4b5563;margin-right:.3rem}
  .mt-tag.warn{background:#fdecea;color:#b42318}
  .mt-tag.todo{background:#e8f1fe;color:#1d4ed8}
  .mt-check{border:1px solid #c5ccd3;background:#fff;border-radius:.4rem;width:2.2rem;height:2.2rem;font-size:1.1rem;cursor:pointer}
  .mt-banner{background:#fff7e6;border:1px solid #f5c26b;border-radius:.5rem;padding:.6rem .8rem;margin-bottom:1rem}
  .mt-form{display:grid;gap:.7rem}
  .mt-two{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
  .mt-kind label{margin-right:1rem}
  .mt-link{font-size:.85rem}
</style>

<div class="sd-head">
  <h1>@if ($editing) Eintrag bearbeiten @else Mein Tag @endif</h1>
</div>

@if ($editing)
  <a class="sd-btn sec" href="{{ route('tag.index', ['datum' => $formDate]) }}">Zurück</a>
@else
  <div class="mt-bar">
    <a class="sd-btn sec" href="{{ route('tag.index', ['datum' => $prev, 'ansicht' => $week ? 'woche' : null]) }}">‹</a>
    <a class="sd-btn sec" href="{{ route('tag.index', ['ansicht' => $week ? 'woche' : null]) }}">Heute</a>
    <a class="sd-btn sec" href="{{ route('tag.index', ['datum' => $next, 'ansicht' => $week ? 'woche' : null]) }}">›</a>
    <a class="sd-btn {{ $week ? 'sec' : '' }}" href="{{ route('tag.index', ['datum' => $date]) }}">Tag</a>
    <a class="sd-btn {{ $week ? '' : 'sec' }}" href="{{ route('tag.index', ['datum' => $date, 'ansicht' => 'woche']) }}">Woche</a>
  </div>
  <p class="sd-muted" style="margin-top:0">@if ($week) Woche vom {{ date('d.m.', strtotime($from)) }} bis {{ date('d.m.Y', strtotime($to)) }} @else {{ $label($date) }} @endif</p>

  @if (count($reminders) > 0)
    <div class="mt-banner">
      <strong>Erinnerung</strong>
      @foreach ($reminders as $r)
        <div>{{ $r->start_time ? $r->start_time.' Uhr' : 'Heute' }}: {{ $r->title }}</div>
      @endforeach
    </div>
  @endif

  @if ($overdue->count() > 0)
    <div class="sd-card mt-day">
      <h2 style="color:#b42318">Überfällig</h2>
      @foreach ($overdue as $o)
        <div class="mt-row">
          <form method="POST" action="{{ route('tag.done', $o->id) }}">@csrf<button class="mt-check" type="submit" title="Erledigt">○</button></form>
          <div class="mt-time">{{ $o->due_on->format('d.m.') }}</div>
          <div class="mt-main"><span class="mt-title">{{ $o->title }}</span> <a class="mt-link" href="{{ route('tag.edit', $o->id) }}">bearbeiten</a></div>
        </div>
      @endforeach
    </div>
  @endif

  @php
    $dayList = [];
    $cursor = $from;
    while ($cursor <= $to) {
        $dayList[] = $cursor;
        $cursor = date('Y-m-d', strtotime($cursor.' +1 day'));
    }
  @endphp
  @foreach ($dayList as $d)
    @php($rows = $days[$d] ?? [])
    <div class="sd-card mt-day {{ $d === $today ? 'today' : '' }}">
      @if ($week)<h2><a href="{{ route('tag.index', ['datum' => $d]) }}" style="color:inherit;text-decoration:none">{{ $label($d) }}@if ($d === $today) (heute)@endif</a></h2>@endif
      @forelse ($rows as $r)
        <div class="mt-row {{ $r['done'] ? 'mt-done' : '' }}">
          @if ($r['source'] === 'own')
            @if ($r['kind'] === 'todo')
              <form method="POST" action="{{ route('tag.done', $r['id']) }}">@csrf<button class="mt-check" type="submit" title="{{ $r['done'] ? 'Wieder öffnen' : 'Erledigt' }}">{{ $r['done'] ? '✓' : '○' }}</button></form>
            @else
              <div style="width:2.2rem"></div>
            @endif
          @else
            <div style="width:2.2rem"></div>
          @endif
          <div class="mt-time">{{ $r['time'] ?? 'ganztägig' }}@if ($r['end'])<br><span style="font-weight:400;color:#6b7280">bis {{ $r['end'] }}</span>@endif</div>
          <div class="mt-main">
            @if ($r['source'] === 'frist')<span class="mt-tag {{ $r['tone'] === 'warn' ? 'warn' : '' }}">Frist</span>@elseif ($r['kind'] === 'todo')<span class="mt-tag todo">To-do</span>@else<span class="mt-tag">Termin</span>@endif
            @if ($r['source'] === 'frist' && $r['url'] !== '')<a class="mt-title" href="{{ $r['url'] }}">{{ $r['title'] }}</a>@else<span class="mt-title">{{ $r['title'] }}</span>@endif
            @if ($r['detail'] !== '')<div class="sd-muted">{{ $r['detail'] }}</div>@endif
            @if ($r['source'] === 'own')
              @if ($r['remind'] !== '')<div class="sd-muted">Erinnerung: {{ MeinTag::REMINDERS[$r['remind']] ?? '' }}</div>@endif
              <a class="mt-link" href="{{ route('tag.edit', $r['id']) }}">bearbeiten</a>
            @endif
          </div>
        </div>
      @empty
        <p class="sd-muted" style="margin:0">@if ($week) Nichts eingetragen. @else Für diesen Tag steht nichts an. @endif</p>
      @endforelse
    </div>
  @endforeach
@endif

<div class="sd-card">
  <h2>@if ($editing) Eintrag ändern @else Neuer Eintrag @endif</h2>
  <form class="mt-form" method="POST" action="{{ $editing ? route('tag.update', $item->id) : route('tag.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    <div class="mt-kind">
      <label><input type="radio" name="kind" value="termin" @checked($val('kind', 'termin') === 'termin')> Termin</label>
      <label><input type="radio" name="kind" value="todo" @checked($val('kind', 'termin') === 'todo')> To-do</label>
    </div>
    <div>
      <label for="mt-title">Titel *</label>
      <input id="mt-title" name="title" type="text" maxlength="190" required value="{{ $val('title') }}" style="{{ $box }}">
      @error('title')<small style="color:#b42318">{{ $message }}</small>@enderror
    </div>
    <div>
      <label for="mt-due">Tag *</label>
      <input id="mt-due" name="due_on" type="date" required value="{{ $val('due_on', $formDate) }}" style="{{ $box }}">
      @error('due_on')<small style="color:#b42318">{{ $message }}</small>@enderror
    </div>
    <div class="mt-two">
      <div>
        <label for="mt-start">Uhrzeit (leer = ganztägig)</label>
        <input id="mt-start" name="start_time" type="time" value="{{ $val('start_time') }}" style="{{ $box }}">
        @error('start_time')<small style="color:#b42318">{{ $message }}</small>@enderror
      </div>
      <div>
        <label for="mt-end">Bis (freiwillig)</label>
        <input id="mt-end" name="end_time" type="time" value="{{ $val('end_time') }}" style="{{ $box }}">
        @error('end_time')<small style="color:#b42318">{{ $message }}</small>@enderror
      </div>
    </div>
    <div>
      <label for="mt-remind">Erinnerung</label>
      <select id="mt-remind" name="remind" style="{{ $box }}">
        @foreach (MeinTag::REMINDERS as $k => $text)
          <option value="{{ $k }}" @selected((string) $val('remind', '') === (string) $k)>{{ $text }}</option>
        @endforeach
      </select>
      <small class="sd-muted">Die Erinnerung kommt als Alarm in dein Handy, wenn du das Kalender-Abo eingerichtet hast. Zusätzlich erscheint sie hier.</small>
    </div>
    <div>
      <label for="mt-notes">Notiz (freiwillig)</label>
      <textarea id="mt-notes" name="notes" rows="3" maxlength="2000" style="{{ $box }}">{{ $val('notes') }}</textarea>
    </div>
    <div class="sd-actions"><button class="sd-btn" type="submit">Speichern</button></div>
  </form>
  @if ($editing)
    <form method="POST" action="{{ route('tag.destroy', $item->id) }}" onsubmit="return confirm('Eintrag löschen?')" style="margin-top:.6rem">@csrf @method('DELETE')<button class="sd-btn sec" type="submit">Löschen</button></form>
  @endif
</div>

@unless ($editing)
  <div class="sd-card">
    <h2>Erinnerungen aufs Handy</h2>
    <p class="sd-muted" style="margin-top:0">Eigene Einträge stehen auch im <a href="{{ route('calendar.index') }}">Kalender-Abo</a>. Dein Handy-Kalender löst dann den Alarm aus. Wie schnell neue Einträge dort erscheinen, bestimmt die Kalender-App, oft dauert es einige Stunden.</p>
    <form method="POST" action="{{ route('tag.mail') }}">
      @csrf
      <label><input type="checkbox" name="on" value="1" @checked($dayMail) onchange="this.form.submit()"> „Mein Tag“ morgens per E-Mail an meine Adresse schicken (nur wenn etwas ansteht)</label>
      <noscript><button class="sd-btn sec" type="submit">Speichern</button></noscript>
    </form>
  </div>
@endunless
@endsection
