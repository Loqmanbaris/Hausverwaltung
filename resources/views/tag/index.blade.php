@extends('layouts.app')
@section('title', 'Mein Tag')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Support\Tag\MeinTag;
  $wd = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
  $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  $label = fn ($d) => $wd[(int) date('w', strtotime($d))].', '.date('d.m.Y', strtotime($d));
  $long = fn ($d) => $wd[(int) date('w', strtotime($d))].', '.(int) date('j', strtotime($d)).'. '.$months[(int) date('n', strtotime($d))].' '.date('Y', strtotime($d));
  $box = 'padding:.6rem;border:1px solid #c5ccd3;border-radius:.6rem;font:inherit;width:100%;box-sizing:border-box';
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
  $todoTotal = 0;
  $todoDone = 0;
  foreach ($days as $dayRows) {
      foreach ($dayRows as $dr) {
          if ($dr['source'] === 'own' && $dr['kind'] === 'todo') {
              $todoTotal++;
              $todoDone += $dr['done'] ? 1 : 0;
          }
      }
  }
  $pct = $todoTotal > 0 ? (int) round($todoDone / $todoTotal * 100) : 0;
  $userName = auth()->user()->name ?? '';
@endphp
<style>
  .mt-pills{display:flex;gap:.5rem;margin:0 0 1rem}
  .mt-pills a,.mt-pills button{flex:1;text-align:center;padding:.8rem .4rem;background:#fff;border:1px solid var(--line);border-radius:14px;font:inherit;font-weight:700;color:var(--ink);text-decoration:none;cursor:pointer;white-space:nowrap}
  .mt-pills .on{background:#1d4ed8;border-color:#1d4ed8;color:#fff}
  @@media (min-width:961px){.mt-pills .more{display:none}}
  .mt-hero{background:linear-gradient(160deg,#141b2d 0%,#26345a 100%);color:#fff;border-radius:24px;padding:1.2rem 1.2rem 1.3rem;margin-bottom:1rem;box-shadow:0 8px 24px rgba(20,27,45,.25)}
  .mt-hero-top{display:flex;justify-content:space-between;align-items:flex-start;gap:.8rem;flex-wrap:wrap}
  .mt-eyebrow{color:#e8b64c;font-weight:800;letter-spacing:.09em;text-transform:uppercase;font-size:.85rem}
  .mt-hero h1{margin:.1rem 0 .2rem;font-size:2.5rem;line-height:1.05;color:#fff}
  .mt-sub{color:#aab3c5;font-size:1.02rem}
  .mt-ctrl{display:flex;gap:.45rem;align-items:center}
  .mt-ctrl select,.mt-ctrl a{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.22);color:#fff;border-radius:12px;padding:.55rem .8rem;font:inherit;font-weight:700;text-decoration:none;line-height:1.2}
  .mt-ctrl select option{color:#111827}
  .mt-ctrl a{min-width:2.5rem;text-align:center}
  .mt-chips{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:1.1rem}
  .mt-chip{display:flex;align-items:center;gap:.55rem;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.16);border-radius:999px;padding:.55rem 1rem;font-size:1.05rem}
  .mt-chip b{font-size:1.15rem}
  .mt-ring{width:1.9rem;height:1.9rem;border-radius:50%;display:inline-block;background:conic-gradient(#34d399 calc(var(--p) * 1%),rgba(255,255,255,.2) 0);position:relative}
  .mt-ring::after{content:"";position:absolute;inset:.32rem;border-radius:50%;background:#26345a}
  .mt-install{display:inline-block;margin-top:.9rem;color:#aab3c5;font-size:.9rem}
  .mt-tiles{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-bottom:1.2rem}
  .mt-tile{display:block;border-radius:16px;padding:1rem 1.1rem 1.1rem;min-height:6.4rem;text-decoration:none;color:#fff;border:1px solid transparent}
  .mt-tile b{display:block;font-size:2.7rem;line-height:1.1;font-weight:800}
  .mt-tile span{display:block;font-size:1.08rem;margin-top:.15rem}
  .mt-tile.green{background:#1e8a4c}.mt-tile.brown{background:#a8541b}.mt-tile.orange{background:#f47b14}
  .mt-tile.purple{background:#6b42bd}.mt-tile.teal{background:#0f8b8d}.mt-tile.pink{background:#d614b5}
  .mt-tile.white{background:#fff;color:#111827;border-color:var(--line)}
  .mt-tile.zero{opacity:.82}
  .mt-day{margin-bottom:1rem;border-radius:16px}
  .mt-day h2{margin:0 0 .5rem;font-size:1.05rem}
  .mt-day.today h2{color:#1d4ed8}
  .mt-row{display:flex;gap:.6rem;align-items:flex-start;padding:.6rem 0;border-top:1px solid #e5e8ec}
  .mt-row:first-of-type{border-top:0}
  .mt-time{min-width:3.6rem;font-variant-numeric:tabular-nums;color:#374151;font-weight:600}
  .mt-main{flex:1;min-width:0;overflow-wrap:anywhere}
  .mt-done .mt-title{text-decoration:line-through;color:#8a94a0}
  .mt-tag{display:inline-block;font-size:.72rem;padding:.05rem .45rem;border-radius:1rem;background:#eef1f5;color:#4b5563;margin-right:.3rem}
  .mt-tag.warn{background:#fdecea;color:#b42318}
  .mt-tag.todo{background:#e8f1fe;color:#1d4ed8}
  .mt-check{border:1px solid #c5ccd3;background:#fff;border-radius:.6rem;width:2.4rem;height:2.4rem;font-size:1.1rem;cursor:pointer}
  .mt-banner{background:#fff7e6;border:1px solid #f5c26b;border-radius:14px;padding:.7rem .9rem;margin-bottom:1rem}
  .mt-form{display:grid;gap:.8rem}
  .mt-two{display:grid;grid-template-columns:1fr 1fr;gap:.7rem}
  .mt-kind label{margin-right:1rem}
  .mt-link{font-size:.85rem}
  .mt-fab{position:fixed;right:1.1rem;bottom:1.1rem;width:3.6rem;height:3.6rem;border-radius:50%;background:#1d4ed8;color:#fff;font-size:2rem;line-height:3.5rem;text-align:center;text-decoration:none;box-shadow:0 8px 20px rgba(29,78,216,.4);z-index:20}
  @@media (min-width:700px){.mt-tiles{grid-template-columns:repeat(4,1fr)}}
</style>

@if ($editing)
  <div class="sd-head"><h1>Eintrag bearbeiten</h1></div>
  <a class="sd-btn sec" href="{{ route('tag.index', ['datum' => $formDate]) }}">Zurück</a>
@else
  <nav class="mt-pills" aria-label="Schnellwahl">
    <a class="{{ ! $week ? 'on' : '' }}" href="{{ route('tag.index') }}">Mein Tag</a>
    <a class="{{ $week ? 'on' : '' }}" href="{{ route('tag.index', ['ansicht' => 'woche']) }}">Kalender</a>
    <a href="{{ route('workorders.index') }}">Aufträge</a>
    <button type="button" class="more" onclick="document.body.classList.add('nav-open')">Mehr</button>
  </nav>

  <section class="mt-hero">
    <div class="mt-hero-top">
      <div>
        <div class="mt-eyebrow">{{ $greeting }}, {{ $userName }}</div>
        <h1>Mein Tag</h1>
        <div class="mt-sub">@if ($week) Woche vom {{ date('d.m.', strtotime($from)) }} bis {{ date('d.m.Y', strtotime($to)) }} @else {{ $long($date) }} @endif · KW {{ date('W', strtotime($date)) }}</div>
      </div>
      <div class="mt-ctrl">
        <form method="GET" action="{{ route('tag.index') }}" style="margin:0">
          <input type="hidden" name="datum" value="{{ $date }}">
          <select name="ansicht" onchange="this.form.submit()" aria-label="Ansicht">
            <option value="tag" @selected(! $week)>Tag</option>
            <option value="woche" @selected($week)>Woche</option>
          </select>
          <noscript><button type="submit">OK</button></noscript>
        </form>
        <a href="{{ route('tag.index', ['datum' => $prev, 'ansicht' => $week ? 'woche' : null]) }}" aria-label="Zurück">‹</a>
        <a href="{{ route('tag.index', ['datum' => $next, 'ansicht' => $week ? 'woche' : null]) }}" aria-label="Weiter">›</a>
      </div>
    </div>
    <div class="mt-chips">
      <div class="mt-chip"><span class="mt-ring" style="--p:{{ $pct }}"></span><b>{{ $todoDone }}/{{ $todoTotal }}</b> erledigt</div>
      <div class="mt-chip">☑ <b>{{ $openTodos }}</b> To-dos offen</div>
    </div>
    <a class="mt-install" href="#" data-install>⬇ App installieren</a>
    @if ($date !== $today && ! $week)
      <div style="margin-top:.6rem"><a class="mt-install" href="{{ route('tag.index') }}" style="margin:0">Zurück zu heute</a></div>
    @endif
  </section>

  <div class="mt-tiles">
    @foreach ($tiles as $t)
      <a class="mt-tile {{ $t['color'] }} {{ $t['count'] === 0 ? 'zero' : '' }}" href="{{ $t['url'] }}"><b>{{ $t['count'] }}</b><span>{{ $t['label'] }}</span></a>
    @endforeach
  </div>

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
          @if ($r['source'] === 'own' && $r['kind'] === 'todo')
            <form method="POST" action="{{ route('tag.done', $r['id']) }}">@csrf<button class="mt-check" type="submit" title="{{ $r['done'] ? 'Wieder öffnen' : 'Erledigt' }}">{{ $r['done'] ? '✓' : '○' }}</button></form>
          @else
            <div style="width:2.4rem"></div>
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

<div class="sd-card" id="neu" style="border-radius:16px">
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
  <div class="sd-card" style="border-radius:16px">
    <h2>Erinnerungen aufs Handy</h2>
    <p class="sd-muted" style="margin-top:0">Eigene Einträge stehen auch im <a href="{{ route('calendar.index') }}">Kalender-Abo</a>. Dein Handy-Kalender löst dann den Alarm aus. Wie schnell neue Einträge dort erscheinen, bestimmt die Kalender-App, oft dauert es einige Stunden.</p>
    <form method="POST" action="{{ route('tag.mail') }}">
      @csrf
      <label><input type="checkbox" name="on" value="1" @checked($dayMail) onchange="this.form.submit()"> „Mein Tag“ morgens per E-Mail an meine Adresse schicken (nur wenn etwas ansteht)</label>
      <noscript><button class="sd-btn sec" type="submit">Speichern</button></noscript>
    </form>
  </div>
  <a class="mt-fab" href="#neu" aria-label="Neuer Eintrag">+</a>
@endunless
@endsection
