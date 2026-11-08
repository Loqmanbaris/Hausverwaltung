@extends('layouts.app')
@section('title', 'Mein Tag')
@section('main_class', 'wide')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  // „Mein Tag“ im Aufbau des Telemaster-Portals (3 Spalten, Wunsch Inhaber 08.11.2026), Farbe der Hausverwaltung statt Gold.
  use App\Support\Tag\MeinTag;
  $wd = ['Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'];
  $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  $long = fn ($d) => $wd[(int) date('w', strtotime($d))].', '.(int) date('j', strtotime($d)).'. '.$months[(int) date('n', strtotime($d))];
  $longY = fn ($d) => $long($d).' '.date('Y', strtotime($d));
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
  $isToday = $date === $today;
  $tomorrow = date('Y-m-d', strtotime($today.' +1 day'));
  $yesterday = date('Y-m-d', strtotime($today.' -1 day'));
  $tick = '<svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8.5l3.2 3L13 4.5"/></svg>';
@endphp
<style>
  .mt{display:flex;flex-direction:column;gap:18px;--acc:var(--brand);--accbg:color-mix(in srgb,var(--brand) 12%,var(--surface));--soft:var(--surface-2)}
  .mt-hero{background:var(--hv-dark,#202c34);color:#fff;border-radius:18px;padding:22px 26px;display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap}
  .mt-eyebrow{color:color-mix(in srgb,var(--brand) 70%,#fff);font-size:12.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
  .mt-hero h1{font-size:32px;font-weight:800;margin:2px 0;color:#fff;line-height:1.15}
  .mt-date{color:rgba(255,255,255,.75);font-size:14px}
  .mt-hero-r{display:flex;flex-direction:column;align-items:flex-end;gap:10px}
  .mt-nav{display:flex;gap:6px}
  .mt-nb{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.18);border-radius:10px;min-width:38px;height:38px;padding:0 12px;font-weight:600;background:rgba(255,255,255,.08);color:#fff!important;text-decoration:none}
  .mt-nb:hover{background:rgba(255,255,255,.16)}
  .mt-chips{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
  .mt-chip{background:rgba(255,255,255,.1);border-radius:8px;padding:8px 14px;font-size:14px;color:#fff}
  .mt-banner{background:var(--accbg);border:1px solid color-mix(in srgb,var(--brand) 30%,transparent);border-radius:12px;padding:10px 14px;font-size:14px}

  .m3-grid{display:grid;grid-template-columns:minmax(0,340px) minmax(0,1fr) minmax(0,290px);gap:16px;align-items:start}
  .m3-side,.m3-main{display:flex;flex-direction:column;gap:14px;min-width:0}
  .m3-card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:14px 16px}
  .m3-h{display:flex;align-items:baseline;gap:8px;margin-bottom:10px;flex-wrap:wrap}
  .m3-h b{font-size:17px;font-weight:800}.m3-h span{color:var(--muted);font-size:13px}
  .m3-mini{margin-left:auto;font-size:12.5px;font-weight:700;color:var(--acc)!important;border:1px solid var(--line);border-radius:8px;padding:3px 9px;background:var(--surface);text-decoration:none}
  .m3-grp{display:flex;align-items:center;gap:8px;font-size:11.5px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--muted);margin:14px 0 6px}
  .m3-grp:after{content:"";flex:1;height:1px;background:var(--line)}.m3-grp.is-bad{color:var(--danger)}
  .m3-grp:first-child{margin-top:2px}

  /* Neues To-do */
  .mt-add{display:flex;gap:8px}
  .mt-add input{flex:1;min-width:0;border:1px solid var(--line-strong);border-radius:999px;padding:9px 14px;background:var(--surface);color:var(--ink);font:inherit;font-size:14px}
  .mt-btn{background:var(--acc);color:#fff;font-weight:700;border:0;border-radius:999px;padding:0 14px;font-size:13.5px;white-space:nowrap;cursor:pointer}
  .mt-btn:hover{filter:brightness(.95)}
  .mt-more{margin-top:6px}
  .mt-more summary{font-size:12.5px;color:var(--muted);cursor:pointer;list-style:none}
  .mt-more summary::-webkit-details-marker{display:none}
  .mt-more[open] summary{margin-bottom:6px}
  .mt-more .g{display:grid;grid-template-columns:1fr 1fr;gap:6px}
  .mt-more .w{grid-column:1/-1}
  .mt-more input,.mt-more select,.mt-more textarea{width:100%;box-sizing:border-box;border:1px solid var(--line-strong);border-radius:8px;padding:7px 9px;background:var(--surface);color:var(--ink);font:inherit;font-size:13.5px}
  .mt-more label{display:block;font-size:12px;color:var(--muted);margin:0 0 2px;font-weight:500}
  .mt-kind{display:inline-flex;border:1px solid var(--line-strong);border-radius:8px;overflow:hidden}
  .mt-kind label{margin:0;cursor:pointer}.mt-kind input{position:absolute;opacity:0;width:1px;height:1px}
  .mt-kind span{display:block;padding:6px 12px;font-weight:600;font-size:13px;color:var(--muted)}
  .mt-kind input:checked+span{background:var(--ink);color:var(--surface)}

  /* Zeitleiste */
  .m3-tl{position:relative;padding-left:16px;margin-top:10px}
  .m3-tl:before{content:"";position:absolute;left:5px;top:6px;bottom:6px;width:2px;background:var(--line)}
  .m3-ev{position:relative;display:block;width:100%;text-align:left;background:var(--surface);border:1px solid var(--line);border-radius:12px;padding:8px 10px;margin-bottom:8px;color:inherit;text-decoration:none;box-sizing:border-box}
  a.m3-ev:hover{border-color:var(--acc)}
  .m3-tl .m3-ev:before{content:"";position:absolute;left:-16px;top:12px;width:10px;height:10px;border-radius:50%;background:var(--c,var(--acc));box-shadow:0 0 0 3px var(--surface)}
  .m3-ev-h{display:flex;align-items:center;gap:6px;font-size:12px;font-weight:800;color:var(--c,var(--acc))}
  .m3-ev b{display:block;font-size:14px;color:var(--ink)}.m3-ev small{display:block;font-size:12px;color:var(--muted)}
  .m3-ev.is-appt{--c:#2563eb}
  .m3-ev.is-frist{--c:var(--danger)}
  .m3-ev.is-todo{--c:var(--acc);background:var(--accbg);border-color:color-mix(in srgb,var(--brand) 25%,transparent)}
  .m3-ev.is-done b{text-decoration:line-through;color:var(--muted)}
  .m3-ev.is-past{opacity:.6}
  .m3-ev form{margin:0;display:inline}
  .m3-ev .m3-act{margin-left:auto;display:flex;gap:4px;align-items:center}
  .m3-now{display:flex;align-items:center;gap:6px;font-size:11px;font-weight:800;color:#c8382d;margin:2px 0 8px -16px}
  .m3-now:after{content:"";flex:1;height:2px;background:#c8382d}
  .m3-ck{width:18px;height:18px;border-radius:5px;border:2px solid color-mix(in srgb,var(--brand) 55%,#999);background:var(--surface);display:inline-flex;align-items:center;justify-content:center;color:#fff;padding:0;cursor:pointer;flex:none}
  .m3-ck svg{width:12px;height:12px;opacity:0}.m3-ck.is-on{background:var(--ok);border-color:var(--ok)}.m3-ck.is-on svg{opacity:1}
  .m3-mini-todo{display:flex;align-items:center;gap:8px;background:var(--surface);border:1px solid var(--line);border-radius:10px;padding:7px 10px;margin-bottom:6px;font-size:13.5px;font-weight:600}
  .m3-mini-todo>span{flex:1;min-width:0;overflow-wrap:anywhere}.m3-mini-todo small{display:block;color:var(--muted);font-weight:500}
  .m3-mini-todo.is-done>span{text-decoration:line-through;color:var(--muted)}
  .m3-mini-todo form{margin:0;display:flex}
  .mt-x{color:var(--muted)!important;text-decoration:none;font-size:15px;padding:0 3px;background:none;border:0;cursor:pointer}
  .mt-x:hover{color:var(--ink)!important}
  .mt-skip{background:none;border:0;padding:0;color:var(--muted);text-decoration:underline;cursor:pointer;font:inherit;font-size:11.5px;font-weight:500}
  .m3-foot{display:flex;justify-content:space-between;gap:8px;margin-top:10px;font-size:13px}
  .mt-link{color:var(--acc)!important;font-weight:600;font-size:13px;text-decoration:none}
  .mt-link:hover{text-decoration:underline}
  .ag-empty{color:var(--muted);font-size:14px;padding:6px 2px}

  /* Heute zu tun */
  .ag-todo{display:flex;align-items:center;gap:12px;padding:10px 6px;border-bottom:1px solid var(--line)}
  .ag-todo:last-child{border-bottom:0}
  .ag-todo .ag-body{flex:1;display:flex;flex-direction:column;gap:4px;min-width:0}
  .ag-name{font-size:15.5px;font-weight:600;line-height:1.35;color:var(--ink);text-decoration:none;overflow-wrap:anywhere}
  a.ag-name:hover{text-decoration:underline}
  .ag-todo.is-done .ag-name{text-decoration:line-through;color:var(--muted)}
  .m3-late{border-left:4px solid #c8382d;padding-left:10px}
  .td-check{width:26px;height:26px;border-radius:50%;border:2px solid var(--line-strong);background:var(--surface);flex:none;display:grid;place-items:center;color:transparent;cursor:pointer;padding:0}
  .td-check svg{width:14px;height:14px}
  .td-check:hover{border-color:var(--acc);color:var(--acc)}
  .td-dot{width:26px;flex:none;display:flex;justify-content:center}.td-dot i{width:10px;height:10px;border-radius:50%;background:var(--c,var(--acc))}
  .td-tags{display:flex;gap:6px;flex-wrap:wrap}
  .td-tag{font-size:12px;font-weight:600;border-radius:999px;padding:2px 10px;background:var(--soft);color:var(--muted)}
  .td-late{background:var(--danger-bg);color:var(--danger)}
  .td-time{background:var(--accbg);color:var(--acc)}
  .m3-hint{display:flex;align-items:center;gap:12px;padding:10px 6px;border-bottom:1px solid var(--line);text-decoration:none;color:var(--ink)}
  .m3-hint:last-child{border-bottom:0}
  .m3-hint:hover{background:var(--soft)}
  .m3-hint b{min-width:2.2rem;text-align:center;font-size:18px;font-weight:800;color:var(--c,var(--acc))}
  .m3-hint span{flex:1;font-weight:600}
  .m3-day{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:12px 0 4px}
  .m3-day:first-child{margin-top:0}

  /* Rechts: Kacheln und Aufträge */
  .m3-tiles{display:grid;grid-template-columns:1fr 1fr;gap:8px}
  .m3-tile{display:flex;flex-direction:column;gap:2px;background:var(--surface);border:1px solid var(--line);border-left:4px solid var(--c,#1f2b33);border-radius:12px;padding:8px 10px;color:var(--ink);text-decoration:none}
  a.m3-tile:hover{border-color:var(--c)}
  .m3-tile b{font-size:22px;font-weight:800;line-height:1.1}.m3-tile span{font-size:12.5px;font-weight:700}.m3-tile small{display:block;font-size:11px;color:var(--muted);font-weight:500}
  .m3-tile.is-all{grid-column:1/-1;flex-direction:row;align-items:center;gap:12px;background:var(--hv-dark,#202c34);color:#fff;border-color:var(--hv-dark,#202c34)}
  .m3-tile.is-all b{color:color-mix(in srgb,var(--brand) 70%,#fff);font-size:26px}.m3-tile.is-all small{color:rgba(255,255,255,.75)}
  .m3-bar{display:block;height:6px;border-radius:3px;background:rgba(255,255,255,.2);margin-top:4px;width:120px;overflow:hidden}.m3-bar i{display:block;height:100%;background:#4cc27c}
  .m3-tile.zero{opacity:.55}
  .m3-ob{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:var(--surface);border:1px solid var(--line);border-left:4px solid var(--line-strong);border-radius:10px;padding:7px 9px;margin-bottom:5px;text-decoration:none;color:var(--ink)}
  .m3-ob:hover{border-color:var(--acc)}
  .m3-ob.is-late{border-left-color:#c8382d;background:var(--danger-bg)}
  .m3-ob.is-termin{border-left-color:#2563eb}
  .m3-ob-t{flex:1 1 150px;min-width:0}.m3-ob-t b{display:block;font-size:13px}.m3-ob-t small{display:block;font-size:11.5px;color:var(--muted)}
  .m3-obtn{display:inline-block;font-size:12px;font-weight:800;background:var(--acc);color:#fff!important;border-radius:7px;padding:6px 10px;text-decoration:none;margin-top:4px}
  .m3-extra summary{cursor:pointer;font-weight:700;font-size:14px}
  .m3-extra .tpl{padding:.5rem 0;border-top:1px solid var(--line);font-size:13.5px}
  .m3-extra .tpl form{margin:.35rem 0 0}

  @@media (min-width:1280px){.m3-side{position:sticky;top:76px}}
  @@media (max-width:1180px){.m3-grid{grid-template-columns:minmax(0,1fr) minmax(0,280px)}.m3-grid>aside:first-child{grid-column:1/-1;position:static}}
  @@media (max-width:820px){.m3-grid{grid-template-columns:minmax(0,1fr)}.m3-right{display:contents}.m3-right .m3-tiles{order:-1}.m3-right>section{order:5}.m3-side{position:static}.mt-hero{padding:18px}.mt-hero h1{font-size:26px}.mt-hero-r{align-items:flex-start}.mt-chips{justify-content:flex-start}}
  .mt-edit-card{max-width:760px}
  .mt-edit-card .g{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:10px}
  .mt-edit-card .w{grid-column:1/-1}
  .mt-edit-card label{display:block;font-size:13px;color:var(--muted);margin:0 0 3px;font-weight:500}
  .mt-edit-card input,.mt-edit-card select,.mt-edit-card textarea{width:100%;box-sizing:border-box;border:1px solid var(--line-strong);border-radius:10px;padding:9px 11px;background:var(--surface);color:var(--ink);font:inherit}
</style>

<div class="mt">
@if ($editing)
  {{-- Bearbeiten: eigene, einfache Seite --}}
  <div class="mt-hero">
    <div>
      <div class="mt-eyebrow">Mein Tag</div>
      <h1>Eintrag bearbeiten</h1>
      <div class="mt-date">{{ $longY($formDate) }}</div>
    </div>
    <div class="mt-hero-r"><div class="mt-nav"><a class="mt-nb" href="{{ route('tag.index', ['datum' => $formDate]) }}">‹ Zurück</a></div></div>
  </div>
  <form class="m3-card mt-edit-card" method="POST" action="{{ route('tag.update', $item->id) }}">
    @csrf @method('PUT')
    <div class="g">
      <div class="w"><span class="mt-kind" role="radiogroup" aria-label="Art">
        <label><input type="radio" name="kind" value="todo" @checked($val('kind', 'todo') === 'todo')><span>To-do</span></label>
        <label><input type="radio" name="kind" value="termin" @checked($val('kind', 'todo') === 'termin')><span>Termin</span></label>
      </span></div>
      <div class="w"><label for="e-title">Titel</label><input id="e-title" name="title" type="text" maxlength="190" required value="{{ $val('title') }}"></div>
      <div><label for="e-due">Tag</label><input id="e-due" name="due_on" type="date" required value="{{ $val('due_on', $formDate) }}"></div>
      <div><label for="e-start">Uhrzeit</label><input id="e-start" name="start_time" type="time" value="{{ $val('start_time') }}"></div>
      <div><label for="e-end">Bis</label><input id="e-end" name="end_time" type="time" value="{{ $val('end_time') }}"></div>
      <div><label for="e-remind">Erinnerung</label><select id="e-remind" name="remind">@foreach (MeinTag::REMINDERS as $k => $text)<option value="{{ $k }}" @selected((string) $val('remind', '') === (string) $k)>{{ $text }}</option>@endforeach</select></div>
      <div><label for="e-repeat">Wiederholen</label><select id="e-repeat" name="repeat_rule">@foreach (MeinTag::REPEATS as $k => $text)<option value="{{ $k }}" @selected((string) $val('repeat_rule', '') === (string) $k)>{{ $text }}</option>@endforeach</select></div>
      <div><label for="e-until">Wiederholen bis</label><input id="e-until" name="repeat_until" type="date" value="{{ $val('repeat_until') }}"></div>
      <div class="w"><label for="e-notes">Notiz</label><textarea id="e-notes" name="notes" rows="3" maxlength="2000">{{ $val('notes') }}</textarea></div>
    </div>
    @foreach (['title', 'due_on', 'start_time', 'end_time'] as $f)@error($f)<div style="color:var(--danger);font-size:.88rem;margin-top:.4rem">{{ $message }}</div>@enderror @endforeach
    @if ($item->repeat)<p class="sd-muted" style="margin:.6rem 0 0;font-size:.9rem">Änderungen gelten für die ganze Serie. Einzelne Tage lässt du in der Liste aus.</p>@endif
    <div class="sd-actions" style="margin-top:12px"><button class="sd-btn" type="submit">Speichern</button></div>
  </form>
  <form method="POST" action="{{ route('tag.destroy', $item->id) }}" onsubmit="return confirm('Eintrag löschen?')">@csrf @method('DELETE')<button class="sd-btn sec" type="submit">Löschen</button></form>
@else
  @php
    // Heute: mit Uhrzeit in die Zeitleiste, ohne Uhrzeit darunter bzw. in „Heute zu tun“
    $timed = array_values(array_filter($rows, fn ($r) => ! empty($r['time'])));
    usort($timed, fn ($a, $b) => strcmp($a['time'], $b['time']));
    $allDayAppts = array_values(array_filter($rows, fn ($r) => empty($r['time']) && $r['source'] === 'own' && $r['kind'] !== 'todo'));
    $untimedTodos = array_values(array_filter($rows, fn ($r) => empty($r['time']) && $r['source'] === 'own' && $r['kind'] === 'todo'));
    $fristen = array_values(array_filter($rows, fn ($r) => empty($r['time']) && $r['source'] === 'frist'));
    $nowAt = (new DateTimeImmutable('now', MeinTag::tz()))->format('H:i');
    $nowIdx = false;
    if ($isToday) {
        foreach ($timed as $i => $r) { if ($r['time'] > $nowAt) { $nowIdx = $i; break; } }
    }
    $todos = array_values(array_filter($rows, fn ($r) => $r['source'] === 'own' && $r['kind'] === 'todo'));
    $total = count($todos) + $overdue->count();
    $done = count(array_filter($todos, fn ($r) => $r['done']));
    $appts = count(array_filter($rows, fn ($r) => $r['source'] === 'own' && $r['kind'] !== 'todo'));
    $openTodos = $total - $done;
    $weekFristen = array_sum(array_map(fn ($d) => count(array_filter($d, fn ($r) => $r['source'] === 'frist')), $upcoming)) + count(array_filter($rows, fn ($r) => $r['source'] === 'frist'));
    $hintColor = ['Mahnungen vorgeschlagen' => '#ea580c', 'Neue Anfragen' => '#7c3aed', 'Kontoumsätze offen' => '#db2777'];
    $heading = $isToday ? 'Mein Tag' : ($date === $tomorrow ? 'Morgen' : ($date === $yesterday ? 'Gestern' : $wd[(int) date('w', strtotime($date))]));
    $firstName = explode(' ', trim(auth()->user()->name))[0];
  @endphp

  {{-- Kopf --}}
  <div class="mt-hero">
    <div>
      <div class="mt-eyebrow">{{ $isToday ? $greeting.', '.$firstName : 'KW '.date('W', strtotime($date)) }}</div>
      <h1>{{ $heading }}</h1>
      <div class="mt-date">{{ $longY($date) }} · KW {{ date('W', strtotime($date)) }}</div>
    </div>
    <div class="mt-hero-r">
      <nav class="mt-nav" aria-label="Tag wählen">
        <a class="mt-nb" href="{{ route('tag.index', ['datum' => $prev]) }}" aria-label="Vorheriger Tag">‹</a>
        @unless ($isToday)<a class="mt-nb" href="{{ route('tag.index') }}">Heute</a>@endunless
        <a class="mt-nb" href="{{ route('tag.index', ['datum' => $next]) }}" aria-label="Nächster Tag">›</a>
      </nav>
      <div class="mt-chips">
        <span class="mt-chip">{{ $appts === 0 ? 'Keine Termine' : $appts.' '.($appts === 1 ? 'Termin' : 'Termine') }}</span>
        <span class="mt-chip"><b>{{ $done }} von {{ $total }} erledigt</b> · {{ $ordersTotal }} {{ $ordersTotal === 1 ? 'Auftrag' : 'Aufträge' }}</span>
      </div>
    </div>
  </div>

  @if (count($reminders) > 0)
    <div class="mt-banner">🔔 <strong>Erinnerung:</strong>
      @foreach ($reminders as $r){{ $r->start_time ? $r->start_time.' Uhr' : 'Heute' }} – {{ $r->title }}@if (! $loop->last) · @endif @endforeach
    </div>
  @endif

  <div class="m3-grid">
    {{-- LINKS: Termine & To-dos --}}
    <aside class="m3-side">
      <section class="m3-card" id="neu">
        <div class="m3-h"><b>Termine &amp; To-dos</b><span>{{ $isToday ? 'heute' : $long($date) }}</span></div>
        <form method="POST" action="{{ route('tag.store') }}">
          @csrf
          <div class="mt-add">
            <input name="title" type="text" maxlength="190" required value="{{ old('title') }}" autocomplete="off" placeholder="{{ $isToday ? 'Neues To-do für heute …' : 'Neues To-do für diesen Tag …' }}" aria-label="Titel">
            <button type="submit" class="mt-btn">Hinzufügen</button>
          </div>
          <details class="mt-more" @if (old('start_time') || old('kind') === 'termin' || old('remind') || old('repeat_rule') || old('notes')) open @endif>
            <summary>Termin, Tag, Uhrzeit …</summary>
            <div class="g">
              <div class="w"><span class="mt-kind" role="radiogroup" aria-label="Art">
                <label><input type="radio" name="kind" value="todo" @checked(old('kind', 'todo') === 'todo')><span>To-do</span></label>
                <label><input type="radio" name="kind" value="termin" @checked(old('kind') === 'termin')><span>Termin</span></label>
              </span></div>
              <div><label for="n-due">Tag</label><input id="n-due" name="due_on" type="date" required value="{{ old('due_on', $date) }}"></div>
              <div><label for="n-start">Uhrzeit</label><input id="n-start" name="start_time" type="time" value="{{ old('start_time') }}"></div>
              <div><label for="n-end">Bis</label><input id="n-end" name="end_time" type="time" value="{{ old('end_time') }}"></div>
              <div><label for="n-remind">Erinnerung</label><select id="n-remind" name="remind">@foreach (MeinTag::REMINDERS as $k => $text)<option value="{{ $k }}" @selected((string) old('remind', '') === (string) $k)>{{ $text }}</option>@endforeach</select></div>
              <div><label for="n-repeat">Wiederholen</label><select id="n-repeat" name="repeat_rule">@foreach (MeinTag::REPEATS as $k => $text)<option value="{{ $k }}" @selected((string) old('repeat_rule', '') === (string) $k)>{{ $text }}</option>@endforeach</select></div>
              <div><label for="n-until">Bis Datum</label><input id="n-until" name="repeat_until" type="date" value="{{ old('repeat_until') }}"></div>
              <div class="w"><label for="n-notes">Notiz</label><textarea id="n-notes" name="notes" rows="2" maxlength="2000">{{ old('notes') }}</textarea></div>
            </div>
          </details>
          @foreach (['title', 'due_on', 'start_time', 'end_time'] as $f)@error($f)<div style="color:var(--danger);font-size:13px;margin-top:4px">{{ $message }}</div>@enderror @endforeach
        </form>

        @foreach ($allDayAppts as $r)
          <div class="m3-ev is-appt" style="margin-top:10px">
            <span class="m3-ev-h">ganztägig · Termin<span class="m3-act"><a class="mt-x" href="{{ route('tag.edit', $r['id']) }}" title="Bearbeiten" aria-label="Bearbeiten">✎</a></span></span>
            <b>{{ $r['title'] }}</b>@if ($r['detail'] !== '')<small>{{ $r['detail'] }}</small>@endif
          </div>
        @endforeach

        <div class="m3-tl">
          @foreach ($timed as $i => $r)
            @if ($nowIdx === $i)<div class="m3-now">JETZT {{ $nowAt }}</div>@endif
            @php
              $own = $r['source'] === 'own';
              $isTodo = $own && $r['kind'] === 'todo';
              $past = $isToday && ($r['end'] ?: $r['time']) < $nowAt;
            @endphp
            <div @class(['m3-ev', 'is-todo' => $isTodo, 'is-appt' => $own && ! $isTodo, 'is-frist' => ! $own, 'is-done' => $r['done'], 'is-past' => $past && ! $isTodo])>
              <span class="m3-ev-h">
                @if ($isTodo)<form method="POST" action="{{ route('tag.done', $r['id']) }}">@csrf<input type="hidden" name="datum" value="{{ $r['date'] }}"><button type="submit" @class(['m3-ck', 'is-on' => $r['done']]) aria-label="Erledigt">{!! $tick !!}</button></form>@endif
                {{ $r['time'] }}@if ($r['end'])–{{ $r['end'] }}@endif · {{ $isTodo ? 'To-do' : ($own ? 'Termin' : 'Frist') }}@if ($own && ! empty($r['recurring'])) · ↻@endif
                @if ($own)<span class="m3-act"><a class="mt-x" href="{{ route('tag.edit', $r['id']) }}" title="Bearbeiten" aria-label="Bearbeiten">✎</a></span>@endif
              </span>
              @if (! $own && ($r['url'] ?? '') !== '')<a href="{{ $r['url'] }}" style="text-decoration:none"><b>{{ $r['title'] }}</b></a>@else<b>{{ $r['title'] }}</b>@endif
              @if ($r['detail'] !== '')<small>{{ $r['detail'] }}</small>@endif
              @if ($own && ! empty($r['recurring']) && ! $r['done'])<form method="POST" action="{{ route('tag.skip', $r['id']) }}" onsubmit="return confirm('Nur diesen Tag auslassen?')">@csrf<input type="hidden" name="datum" value="{{ $r['date'] }}"><button type="submit" class="mt-skip">diesen Tag auslassen</button></form>@endif
            </div>
          @endforeach
          @if ($isToday && $nowIdx === false && $timed !== [])<div class="m3-now">JETZT {{ $nowAt }}</div>@endif
          @if ($timed === [] && $allDayAppts === [])<div class="ag-empty">Keine Termine{{ $untimedTodos === [] ? ' und To-dos' : ' mit Uhrzeit' }}.</div>@endif
        </div>

        @if ($untimedTodos !== [])
          <div class="m3-grp">To-dos ohne Uhrzeit · {{ count(array_filter($untimedTodos, fn ($r) => ! $r['done'])) }}</div>
          @foreach ($untimedTodos as $r)
            <div @class(['m3-mini-todo', 'is-done' => $r['done']])>
              <form method="POST" action="{{ route('tag.done', $r['id']) }}">@csrf<input type="hidden" name="datum" value="{{ $r['date'] }}"><button type="submit" @class(['m3-ck', 'is-on' => $r['done']]) aria-label="Erledigt">{!! $tick !!}</button></form>
              <span>{{ $r['title'] }}@if (! empty($r['recurring'])) <small>↻ wiederholt sich</small>@endif</span>
              @if (! empty($r['recurring']) && ! $r['done'])<form method="POST" action="{{ route('tag.skip', $r['id']) }}" onsubmit="return confirm('Nur diesen Tag auslassen?')">@csrf<input type="hidden" name="datum" value="{{ $r['date'] }}"><button type="submit" class="mt-x" title="Diesen Tag auslassen" aria-label="Diesen Tag auslassen">⤼</button></form>@endif
              <a class="mt-x" href="{{ route('tag.edit', $r['id']) }}" title="Bearbeiten" aria-label="Bearbeiten">✎</a>
            </div>
          @endforeach
        @endif
        <div class="m3-foot"><a class="mt-link" href="{{ route('tag.index', ['datum' => $next]) }}">{{ $isToday ? 'Morgen' : 'Nächsten Tag' }} ansehen ›</a><a class="mt-link" href="{{ route('calendar.index') }}">Kalender-Abo →</a></div>
      </section>
    </aside>

    {{-- MITTE: Heute zu tun und die nächsten 7 Tage --}}
    <main class="m3-main">
      <section class="m3-card">
        <div class="m3-h"><b style="font-size:20px">{{ $isToday ? 'Heute zu tun' : 'Zu tun' }}</b><span>Überfällig, Fristen, Hinweise</span></div>
        @if ($overdue->isNotEmpty())
          <div class="m3-grp is-bad" id="m3-late">Überfällig · {{ $overdue->count() }}</div>
          @foreach ($overdue as $o)
            <div class="ag-todo m3-late">
              <form method="POST" action="{{ route('tag.done', $o->id) }}" style="margin:0">@csrf<button type="submit" class="td-check" aria-label="Erledigt" title="Erledigt">{!! $tick !!}</button></form>
              <div class="ag-body">
                <span class="ag-name">{{ $o->title }}</span>
                <div class="td-tags"><span class="td-tag td-late">seit {{ $o->due_on->format('d.m.') }}</span></div>
              </div>
              <a class="mt-x" href="{{ route('tag.edit', $o->id) }}" title="Bearbeiten oder verschieben" aria-label="Bearbeiten">✎</a>
            </div>
          @endforeach
        @endif

        @if ($fristen !== [])
          <div class="m3-grp">Fristen · {{ count($fristen) }}</div>
          @foreach ($fristen as $r)
            <div class="ag-todo">
              <span class="td-dot" style="--c:var(--danger)"><i></i></span>
              <div class="ag-body">
                @if (($r['url'] ?? '') !== '')<a class="ag-name" href="{{ $r['url'] }}">{{ $r['title'] }}</a>@else<span class="ag-name">{{ $r['title'] }}</span>@endif
                <div class="td-tags"><span class="td-tag {{ ($r['tone'] ?? '') === 'warn' ? 'td-late' : '' }}">Frist {{ $isToday ? 'heute' : 'an diesem Tag' }}</span>@if ($r['detail'] !== '')<span class="td-tag">{{ $r['detail'] }}</span>@endif</div>
              </div>
            </div>
          @endforeach
        @endif

        @if ($alerts)
          <div class="m3-grp">Hinweise</div>
          @foreach ($alerts as $a)
            <a class="m3-hint" href="{{ $a['url'] }}" style="--c:{{ $hintColor[$a['label']] ?? 'var(--acc)' }}"><b>{{ $a['count'] }}</b><span>{{ $a['label'] }}</span><span class="mt-link" style="flex:none">öffnen ›</span></a>
          @endforeach
        @endif

        @if ($overdue->isEmpty() && $fristen === [] && ! $alerts)
          <div class="ag-empty">Nichts Überfälliges, keine Fristen. 🎉</div>
        @endif
      </section>

      <section class="m3-card" id="woche">
        <div class="m3-h"><b>Nächste 7 Tage</b><span>{{ date('d.m.', strtotime($first)) }} – {{ date('d.m.', strtotime($last)) }}</span><a class="m3-mini" href="{{ route('calendar.index') }}">Kalender-Abo</a></div>
        @forelse ($upcoming as $d => $dayRows)
          <div class="m3-day"><a href="{{ route('tag.index', ['datum' => $d]) }}" style="color:inherit;text-decoration:none">{{ $d === $tomorrow ? 'Morgen' : $long($d) }}</a></div>
          @foreach ($dayRows as $r)
            @php
              $own = $r['source'] === 'own';
              $isTodo = $own && $r['kind'] === 'todo';
            @endphp
            <div @class(['ag-todo', 'is-done' => $r['done']])>
              @if ($isTodo)
                <form method="POST" action="{{ route('tag.done', $r['id']) }}" style="margin:0">@csrf<input type="hidden" name="datum" value="{{ $r['date'] }}"><button type="submit" class="td-check" aria-label="Erledigt">{!! $tick !!}</button></form>
              @else
                <span class="td-dot" style="--c:{{ $own ? '#2563eb' : 'var(--danger)' }}"><i></i></span>
              @endif
              <div class="ag-body">
                @if (! $own && ($r['url'] ?? '') !== '')<a class="ag-name" href="{{ $r['url'] }}">{{ $r['title'] }}</a>@else<span class="ag-name">{{ $r['title'] }}</span>@endif
                <div class="td-tags">
                  @if ($r['time'])<span class="td-tag td-time">⏰ {{ $r['time'] }}@if ($r['end'])–{{ $r['end'] }}@endif</span>@endif
                  <span class="td-tag {{ ! $own && ($r['tone'] ?? '') === 'warn' ? 'td-late' : '' }}">{{ $isTodo ? 'To-do' : ($own ? 'Termin' : 'Frist') }}</span>
                  @if ($own && ! empty($r['recurring']))<span class="td-tag">↻</span>@endif
                  @if ($r['detail'] !== '')<span class="td-tag">{{ $r['detail'] }}</span>@endif
                </div>
              </div>
              @if ($own)<a class="mt-x" href="{{ route('tag.edit', $r['id']) }}" title="Bearbeiten" aria-label="Bearbeiten">✎</a>@endif
            </div>
          @endforeach
        @empty
          <div class="ag-empty">In den nächsten 7 Tagen steht nichts an.</div>
        @endforelse
      </section>
    </main>

    {{-- RECHTS: Kacheln, offene Aufträge, Vorlagen --}}
    <aside class="m3-side m3-right">
      <div class="m3-tiles">
        <div class="m3-tile is-all"><b>{{ $openTodos }}</b><span>Alles offen<small>{{ $done }} von {{ $total }} erledigt</small><i class="m3-bar"><i style="width:{{ $total ? round($done / $total * 100) : 0 }}%"></i></i></span></div>
        <a href="#m3-late" @class(['m3-tile', 'zero' => $overdue->isEmpty()]) style="--c:#c8382d"><b>{{ $overdue->count() }}</b><span>Überfällig</span></a>
        <a href="#neu" @class(['m3-tile', 'zero' => $openTodos === 0]) style="--c:var(--brand)"><b>{{ $openTodos }}</b><span>To-dos</span></a>
        <a href="#woche" @class(['m3-tile', 'zero' => $weekFristen === 0]) style="--c:#475569"><b>{{ $weekFristen }}</b><span>Fristen<small>7 Tage</small></span></a>
        <a href="{{ route('workorders.index') }}" @class(['m3-tile', 'zero' => $ordersTotal === 0]) style="--c:#0f766e"><b>{{ $ordersTotal }}</b><span>Aufträge<small>offen</small></span></a>
      </div>

      <section class="m3-card">
        <div class="m3-h"><b>Offene Aufträge</b><span>{{ $ordersTotal }}</span><a class="m3-mini" href="{{ route('workorders.index') }}">Alle</a></div>
        @forelse ($orders as $o)
          <a @class(['m3-ob', 'is-late' => $o->priority !== 'normal', 'is-termin' => $o->priority === 'normal' && $o->appointment_at]) href="{{ route('workorders.show', $o) }}">
            <span class="m3-ob-t">
              <b>{{ $o->title }}</b>
              <small>{{ collect([$o->priority === 'notfall' ? 'Notfall' : ($o->priority === 'dringend' ? 'dringend' : null), $o->statusLabel(), $o->property?->name, $o->unit?->label, $o->vendor?->name, $o->appointment_at ? 'Termin '.$o->appointment_at->timezone('Europe/Berlin')->format('d.m. H:i') : null])->filter()->implode(' · ') }}</small>
            </span>
          </a>
        @empty
          <div class="ag-empty">Keine offenen Aufträge.</div>
        @endforelse
        <a class="m3-obtn" href="{{ route('workorders.create') }}">+ Schaden / Auftrag erfassen</a>
      </section>

      <section class="m3-card m3-extra">
        <details>
          <summary>Vorlagen für deinen Plan</summary>
          <p class="sd-muted" style="font-size:13px;margin:.4rem 0">Fertige Listen mit einem Tipp übernehmen. Was es schon gibt, wird nicht doppelt angelegt.</p>
          @foreach ($templates as $tkey => $tpl)
            <div class="tpl">
              <strong>{{ $tpl['title'] }}</strong> <span class="sd-muted">({{ count($tpl['entries']) }}) – {{ $tpl['text'] }}</span>
              <form method="POST" action="{{ route('tag.template', $tkey) }}">@csrf<button class="sd-btn sec small" type="submit">Übernehmen</button></form>
            </div>
          @endforeach
        </details>
      </section>
    </aside>
  </div>
@endif
</div>
@endsection
