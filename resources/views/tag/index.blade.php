@extends('layouts.app')
@section('title', 'Mein Tag')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Support\Tag\MeinTag;
  $wd = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
  $wdShort = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
  $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  $long = fn ($d) => $wd[(int) date('w', strtotime($d))].', '.(int) date('j', strtotime($d)).'. '.$months[(int) date('n', strtotime($d))];
  $short = fn ($d) => $wdShort[(int) date('w', strtotime($d))].' '.date('d.m.', strtotime($d));
  $editing = $item !== null;
  $val = function (string $key, $default = '') use ($item, $editing) {
      if (! $editing) {
          return old($key, $default);
      }
      $current = match ($key) {
          'due_on' => $item->due_on->format('Y-m-d'),
          'repeat_rule' => MeinTag::repeatKey($item),
          'repeat_until' => $item->repeat_until?->format('Y-m-d'),
          default => $item->{$key},
      };
      return old($key, $current ?? $default);
  };
  $formDate = $editing ? $item->due_on->format('Y-m-d') : $date;
  $hasMore = $editing ? ($item->end_time || $item->remind || $item->repeat || $item->notes) : (old('remind') || old('repeat_rule') || old('notes') || old('end_time'));
  $isToday = $date === $today;
  $tomorrow = date('Y-m-d', strtotime($today.' +1 day'));
@endphp
<style>
  .mt{max-width:860px}
  .mt-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:.6rem;margin:0 0 1rem}
  .mt-head h1{margin:0;font-size:1.7rem;line-height:1.15}
  .mt-hi{color:var(--muted);font-size:.95rem;margin-bottom:.1rem}
  .mt-nav{display:flex;gap:.35rem;align-items:center}
  .mt-nav a{display:inline-flex;align-items:center;justify-content:center;min-width:2.4rem;height:2.4rem;padding:0 .7rem;border:1px solid var(--line-strong);border-radius:10px;background:var(--surface);color:var(--ink);text-decoration:none;font-weight:600}
  .mt-nav a:hover{border-color:var(--ink)}
  .mt-alerts{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 1rem}
  .mt-alert{display:inline-flex;align-items:center;gap:.45rem;padding:.4rem .8rem;border-radius:999px;background:var(--brass-bg);border:1px solid var(--brass-line);color:var(--brass);text-decoration:none;font-size:.92rem;font-weight:600}
  .mt-alert b{font-size:1rem}
  .mt-card{background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:1rem 1.1rem;margin:0 0 1rem}
  .mt-card h2{display:flex;align-items:center;justify-content:space-between;gap:.6rem;margin:0 0 .4rem;font-size:1.05rem}
  .mt-card h2 a{font-size:.85rem;font-weight:500;color:var(--muted)}
  .mt-add{display:flex;gap:.5rem;flex-wrap:wrap;align-items:center}
  .mt-add .t{flex:1 1 14rem;min-width:0}
  .mt-add input,.mt-add select,.mt-more input,.mt-more select,.mt-more textarea{padding:.6rem .7rem;border:1px solid var(--line-strong);border-radius:10px;font:inherit;background:var(--surface);color:var(--ink);width:100%;box-sizing:border-box}
  .mt-add input[type=time]{width:7.2rem}
  .mt-add input[type=date]{width:10.5rem}
  .mt-seg{display:inline-flex;border:1px solid var(--line-strong);border-radius:10px;overflow:hidden;flex:none}
  .mt-seg label{margin:0;cursor:pointer}
  .mt-seg input{position:absolute;opacity:0;width:1px;height:1px}
  .mt-seg span{display:block;padding:.55rem .8rem;font-weight:600;font-size:.92rem;color:var(--muted)}
  .mt-seg input:checked+span{background:var(--ink);color:var(--surface)}
  .mt-seg input:focus-visible+span{outline:2px solid var(--focus);outline-offset:-2px}
  .mt-more{margin-top:.6rem}
  .mt-more summary{cursor:pointer;color:var(--muted);font-size:.9rem}
  .mt-more .g{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:.6rem;margin-top:.6rem}
  .mt-more .g .w{grid-column:1/-1}
  .mt-more label{display:block;font-size:.85rem;color:var(--muted);margin:0 0 .2rem;font-weight:500}
  .mt-row{display:flex;gap:.7rem;align-items:center;padding:.6rem 0;border-top:1px solid var(--line)}
  .mt-row:first-of-type{border-top:0}
  .mt-check{flex:none;width:2.2rem;height:2.2rem;border-radius:50%;border:2px solid var(--line-strong);background:var(--surface);color:var(--ok);font-size:1rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;padding:0}
  .mt-check:hover{border-color:var(--ok)}
  .mt-done .mt-check{background:var(--ok);border-color:var(--ok);color:#fff}
  .mt-dot{flex:none;width:2.2rem;display:flex;justify-content:center}
  .mt-dot i{width:.65rem;height:.65rem;border-radius:50%;background:var(--brand)}
  .mt-dot i.frist{background:var(--danger)}
  .mt-time:empty{display:none}
  .mt-time{flex:none;min-width:3.4rem;font-variant-numeric:tabular-nums;color:var(--muted);font-weight:600;font-size:.92rem}
  .mt-main{flex:1;min-width:0;overflow-wrap:anywhere}
  .mt-title{color:var(--ink);text-decoration:none}
  a.mt-title:hover{text-decoration:underline}
  .mt-done .mt-title{text-decoration:line-through;color:var(--muted)}
  .mt-sub{color:var(--muted);font-size:.85rem}
  .mt-edit{flex:none;color:var(--muted);text-decoration:none;font-size:1.05rem;padding:.2rem .35rem;border-radius:6px}
  .mt-edit:hover{background:var(--surface-2);color:var(--ink)}
  .mt-tag{display:inline-block;font-size:.72rem;font-weight:700;padding:.02rem .45rem;border-radius:1rem;background:var(--surface-2);color:var(--muted);margin-right:.25rem;vertical-align:1px}
  .mt-tag.warn{background:var(--danger-bg);color:var(--danger)}
  .mt-tag.ok{background:var(--ok-bg);color:var(--ok)}
  .mt-empty{color:var(--muted);margin:.2rem 0}
  .mt-daylabel{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:.9rem 0 .1rem}
  .mt-daylabel:first-of-type{margin-top:.2rem}
  .mt-skip{background:none;border:0;padding:0;color:var(--muted);text-decoration:underline;cursor:pointer;font:inherit;font-size:.82rem}
  .mt-banner{background:var(--brass-bg);border:1px solid var(--brass-line);color:var(--ink);border-radius:12px;padding:.7rem .9rem;margin-bottom:1rem}
  .mt-extra summary{cursor:pointer;font-weight:600;padding:.2rem 0}
  .mt-extra details{border-top:1px solid var(--line);padding:.6rem 0}
  .mt-extra details:first-of-type{border-top:0}
  @@media (max-width:560px){.mt-add input[type=date],.mt-add input[type=time]{flex:1 1 8rem;width:auto}.mt-head h1{font-size:1.45rem}}
</style>

<div class="mt">
@if ($editing)
  <div class="mt-head">
    <div><h1>Eintrag bearbeiten</h1></div>
    <div class="mt-nav"><a href="{{ route('tag.index', ['datum' => $formDate]) }}">Zurück</a></div>
  </div>
@else
  {{-- Kopf: Gruß, Datum, blättern --}}
  <div class="mt-head">
    <div>
      <div class="mt-hi">{{ $greeting }}, {{ auth()->user()->name }}</div>
      <h1>{{ $isToday ? 'Heute' : ($date === $tomorrow ? 'Morgen' : $long($date)) }}@if ($isToday || $date === $tomorrow) <span style="font-weight:400;color:var(--muted);font-size:1.1rem">· {{ $long($date) }}</span>@endif</h1>
    </div>
    <nav class="mt-nav" aria-label="Tag wählen">
      <a href="{{ route('tag.index', ['datum' => $prev]) }}" aria-label="Vorheriger Tag">‹</a>
      @unless ($isToday)<a href="{{ route('tag.index') }}">Heute</a>@endunless
      <a href="{{ route('tag.index', ['datum' => $next]) }}" aria-label="Nächster Tag">›</a>
    </nav>
  </div>

  @if ($alerts)
    <div class="mt-alerts">
      @foreach ($alerts as $a)<a class="mt-alert" href="{{ $a['url'] }}"><b>{{ $a['count'] }}</b> {{ $a['label'] }} ›</a>@endforeach
    </div>
  @endif

  @if (count($reminders) > 0)
    <div class="mt-banner"><strong>Erinnerung:</strong>
      @foreach ($reminders as $r){{ $r->start_time ? $r->start_time.' Uhr' : 'Heute' }} – {{ $r->title }}@if (! $loop->last) · @endif @endforeach
    </div>
  @endif
@endif

{{-- Neuer Eintrag (oben, schnell) / Bearbeiten --}}
<form class="mt-card" id="neu" method="POST" action="{{ $editing ? route('tag.update', $item->id) : route('tag.store') }}">
  @csrf
  @if ($editing) @method('PUT') @endif
  <div class="mt-add">
    <span class="mt-seg" role="radiogroup" aria-label="Art">
      <label><input type="radio" name="kind" value="todo" @checked($val('kind', 'todo') === 'todo')><span>To-do</span></label>
      <label><input type="radio" name="kind" value="termin" @checked($val('kind', 'todo') === 'termin')><span>Termin</span></label>
    </span>
    <input class="t" name="title" type="text" maxlength="190" required value="{{ $val('title') }}" placeholder="{{ $editing ? 'Titel' : 'Was steht an?' }}" aria-label="Titel">
    <input name="due_on" type="date" required value="{{ $val('due_on', $formDate) }}" aria-label="Tag">
    <input name="start_time" type="time" value="{{ $val('start_time') }}" aria-label="Uhrzeit (leer = ganztägig)" title="Uhrzeit (leer = ganztägig)">
    <button class="sd-btn" type="submit">{{ $editing ? 'Speichern' : 'Hinzufügen' }}</button>
  </div>
  @foreach (['title', 'due_on', 'start_time', 'end_time'] as $f)@error($f)<div style="color:var(--danger);font-size:.88rem;margin-top:.3rem">{{ $message }}</div>@enderror @endforeach
  <details class="mt-more" @if ($hasMore) open @endif>
    <summary>Mehr: Ende, Erinnerung, Wiederholen, Notiz</summary>
    <div class="g">
      <div><label for="mt-end">Bis (Uhrzeit)</label><input id="mt-end" name="end_time" type="time" value="{{ $val('end_time') }}"></div>
      <div><label for="mt-remind">Erinnerung</label>
        <select id="mt-remind" name="remind">@foreach (MeinTag::REMINDERS as $k => $text)<option value="{{ $k }}" @selected((string) $val('remind', '') === (string) $k)>{{ $text }}</option>@endforeach</select></div>
      <div><label for="mt-repeat">Wiederholen</label>
        <select id="mt-repeat" name="repeat_rule">@foreach (MeinTag::REPEATS as $k => $text)<option value="{{ $k }}" @selected((string) $val('repeat_rule', '') === (string) $k)>{{ $text }}</option>@endforeach</select></div>
      <div><label for="mt-until">Wiederholen bis</label><input id="mt-until" name="repeat_until" type="date" value="{{ $val('repeat_until') }}"></div>
      <div class="w"><label for="mt-notes">Notiz</label><textarea id="mt-notes" name="notes" rows="2" maxlength="2000">{{ $val('notes') }}</textarea></div>
    </div>
    @if ($editing && $item->repeat)<p class="mt-sub" style="margin:.5rem 0 0">Änderungen gelten für die ganze Serie. Einzelne Tage lässt du in der Liste aus.</p>@endif
  </details>
</form>
@if ($editing)
  <form method="POST" action="{{ route('tag.destroy', $item->id) }}" onsubmit="return confirm('Eintrag löschen?')">@csrf @method('DELETE')<button class="sd-btn sec" type="submit">Löschen</button></form>
@endif

@unless ($editing)
  {{-- Eine Zeile der Liste --}}
  @php
    $line = function (array $r, bool $showDay = false) use ($short) {
        return view('tag.row', ['r' => $r, 'showDay' => $showDay, 'short' => $short])->render();
    };
  @endphp

  {{-- Der Tag --}}
  <section class="mt-card" aria-label="{{ $isToday ? 'Heute' : $long($date) }}">
    <h2>{{ $isToday ? 'Heute' : $long($date) }}</h2>
    @foreach ($overdue as $o)
      <div class="mt-row">
        <form method="POST" action="{{ route('tag.done', $o->id) }}" style="margin:0">@csrf<button class="mt-check" type="submit" title="Erledigt" aria-label="Erledigt"></button></form>
        <div class="mt-time" style="color:var(--danger)">{{ $o->due_on->format('d.m.') }}</div>
        <div class="mt-main"><span class="mt-tag warn">überfällig</span><span class="mt-title">{{ $o->title }}</span></div>
        <a class="mt-edit" href="{{ route('tag.edit', $o->id) }}" title="Bearbeiten" aria-label="Bearbeiten">✎</a>
      </div>
    @endforeach
    @foreach ($rows as $r){!! $line($r) !!}@endforeach
    @if ($overdue->isEmpty() && $rows === [])
      <p class="mt-empty">{{ $isToday ? 'Heute steht nichts an.' : 'An diesem Tag steht nichts an.' }}</p>
    @endif
  </section>

  {{-- Die nächsten 7 Tage (früher „Kalender“) --}}
  <section class="mt-card" id="woche" aria-label="Nächste 7 Tage">
    <h2>Nächste 7 Tage <a href="{{ route('calendar.index') }}">Kalender-Abo ›</a></h2>
    @forelse ($upcoming as $d => $dayRows)
      <div class="mt-daylabel">{{ $d === $tomorrow ? 'Morgen' : $long($d) }}</div>
      @foreach ($dayRows as $r){!! $line($r) !!}@endforeach
    @empty
      <p class="mt-empty">In den nächsten 7 Tagen steht nichts an.</p>
    @endforelse
  </section>

  {{-- Offene Aufträge (früher eigener Reiter) --}}
  <section class="mt-card" aria-label="Offene Aufträge">
    <h2>Offene Aufträge @if ($ordersTotal > 0)<span class="mt-tag">{{ $ordersTotal }}</span>@endif <a href="{{ route('workorders.index') }}">Alle Aufträge ›</a></h2>
    @forelse ($orders as $o)
      <div class="mt-row">
        <div class="mt-dot"><i @if ($o->priority !== 'normal') class="frist" @endif></i></div>
        <div class="mt-main">
          @if ($o->priority === 'notfall')<span class="mt-tag warn">Notfall</span>@elseif ($o->priority === 'dringend')<span class="mt-tag warn">dringend</span>@endif
          <span class="mt-tag {{ $o->status === 'termin' ? 'ok' : '' }}">{{ $o->statusLabel() }}</span>
          <a class="mt-title" href="{{ route('workorders.show', $o) }}">{{ $o->title }}</a>
          <div class="mt-sub">{{ collect([$o->property?->name, $o->unit?->label, $o->vendor?->name, $o->appointment_at ? 'Termin '.$o->appointment_at->timezone('Europe/Berlin')->format('d.m. H:i') : null])->filter()->implode(' · ') }}</div>
        </div>
      </div>
    @empty
      <p class="mt-empty">Keine offenen Aufträge.</p>
    @endforelse
    <div style="margin-top:.6rem"><a class="sd-btn sec small" href="{{ route('workorders.create') }}">+ Auftrag / Schaden erfassen</a></div>
  </section>

  {{-- Selten gebraucht: zugeklappt --}}
  <section class="mt-card mt-extra">
    <details>
      <summary>Vorlagen für deinen Plan</summary>
      <p class="mt-sub">Fertige Listen, die du mit einem Tipp übernimmst. Was es schon gibt, wird nicht doppelt angelegt.</p>
      @foreach ($templates as $tkey => $tpl)
        <div style="padding:.5rem 0;border-top:1px solid var(--line)">
          <strong>{{ $tpl['title'] }}</strong> <span class="mt-sub">({{ count($tpl['entries']) }} Einträge) – {{ $tpl['text'] }}</span>
          <form method="POST" action="{{ route('tag.template', $tkey) }}" style="margin:.4rem 0 0">@csrf<button class="sd-btn sec small" type="submit">Übernehmen</button></form>
        </div>
      @endforeach
    </details>
    <details>
      <summary>Erinnerungen und E-Mail am Morgen</summary>
      <p class="mt-sub">Eigene Einträge stehen auch im <a href="{{ route('calendar.index') }}">Kalender-Abo</a>, dein Handy-Kalender löst dann den Alarm aus.</p>
      <form method="POST" action="{{ route('tag.mail') }}" style="margin:0">
        @csrf
        <label style="font-weight:400"><input type="checkbox" name="on" value="1" @checked($dayMail) onchange="this.form.submit()"> „Mein Tag“ morgens per E-Mail schicken (nur wenn etwas ansteht)</label>
        <noscript><button class="sd-btn sec small" type="submit">Speichern</button></noscript>
      </form>
    </details>
  </section>
@endunless
</div>
@endsection
