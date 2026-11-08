@extends('layouts.app')
@section('title', 'Wochenüberblick KW '.$o['kw'])
@section('main_class', 'wide')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Support\Tag\Woche;
  $wd = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
  $rentOpen = $o['rent']['open'] ?? 0;
  $label = fn ($r) => $r['source'] === 'auftrag' ? 'Auftrag' : ($r['source'] === 'frist' ? 'Frist' : ($r['kind'] === 'todo' ? 'To-do' : 'Termin'));
  $open = array_values(array_filter([
      ['n' => $o['overdue'], 'label' => 'überfällige To-dos', 'href' => route('tag.index').'#m3-late', 'c' => '#c8382d'],
      ['n' => $rentOpen, 'label' => 'Mieten offen'.($o['rent'] ? ' ('.Woche::money($o['rent']['open_sum']).')' : ''), 'href' => route('rent.month'), 'c' => 'var(--brand)'],
      ['n' => $o['bank_open'], 'label' => 'Kontoumsätze zuordnen', 'href' => route('bank.transactions'), 'c' => '#db2777'],
      ['n' => $o['orders_open'], 'label' => 'offene Aufträge', 'href' => route('workorders.index'), 'c' => '#0f766e'],
      ['n' => $o['mail_unread'], 'label' => 'ungelesene E-Mails', 'href' => route('inbox.index'), 'c' => '#2563eb'],
  ], fn ($x) => $x['n'] > 0));
@endphp
<style>
  .wk{display:flex;flex-direction:column;gap:16px;--soft:var(--surface-2)}
  .wk-hero{background:var(--hv-dark,#202c34);color:#fff;border-radius:18px;padding:22px 26px;display:flex;justify-content:space-between;align-items:flex-end;gap:14px;flex-wrap:wrap}
  .wk-hero small{color:color-mix(in srgb,var(--brand) 70%,#fff);font-size:12.5px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
  .wk-hero h1{color:#fff;font-size:30px;margin:2px 0}
  .wk-hero p{color:rgba(255,255,255,.75);margin:0}
  .wk-nav{display:flex;gap:6px;flex-wrap:wrap}
  .wk-nav a,.wk-nav button{display:inline-flex;align-items:center;justify-content:center;border:1px solid rgba(255,255,255,.18);border-radius:10px;height:38px;padding:0 12px;background:rgba(255,255,255,.08);color:#fff!important;text-decoration:none;font:inherit;font-weight:600;cursor:pointer}
  .wk-nav form{margin:0}
  html[data-theme=dark] .wk-hero{background:#24313c;border:1px solid var(--line)}
  .wk-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:16px;align-items:start}
  .wk-card{background:var(--surface);border:1px solid var(--line);border-radius:16px;padding:16px 18px}
  .wk-card h2{font-size:17px;margin:0 0 12px}
  .wk-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
  .wk-stats div{background:var(--soft);border-radius:12px;padding:10px 12px}
  .wk-stats b{display:block;font-size:28px;font-weight:800;line-height:1.1}
  .wk-stats span{font-size:13px;color:var(--muted);font-weight:600}
  .wk-open a{display:flex;align-items:center;gap:12px;padding:10px 6px;border-bottom:1px solid var(--line);text-decoration:none;color:var(--ink)}
  .wk-open a:last-child{border-bottom:0}
  .wk-open b{min-width:2.4rem;text-align:center;font-size:20px;font-weight:800;color:var(--c)}
  .wk-open span{flex:1;font-weight:600}
  .wk-day{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);margin:14px 0 4px}
  .wk-day:first-of-type{margin-top:0}
  .wk-row{display:flex;gap:10px;align-items:baseline;padding:6px 2px;border-bottom:1px solid var(--line);font-size:14.5px}
  .wk-row time{min-width:3rem;color:var(--muted);font-variant-numeric:tabular-nums}
  .wk-row span{flex:1;min-width:0;overflow-wrap:anywhere}
  .wk-row em{font-style:normal;font-size:12px;font-weight:600;color:var(--muted);background:var(--soft);border-radius:999px;padding:1px 8px}
  .wk-row a{color:inherit}
  @@media (max-width:900px){.wk-grid{grid-template-columns:minmax(0,1fr)}.wk-stats{grid-template-columns:1fr 1fr 1fr}.wk-hero{padding:18px}.wk-hero h1{font-size:24px}}
</style>
<div class="wk">
  <div class="wk-hero">
    <div>
      <small>Wochenüberblick</small>
      <h1>KW {{ $o['kw'] }}{{ $thisWeek ? ' – diese Woche' : '' }}</h1>
      <p>{{ date('d.m.', strtotime($monday)) }} – {{ date('d.m.Y', strtotime($monday.' +6 days')) }} · kommt montags um 07:30 Uhr{{ $weekMail ? ' per E-Mail' : '' }}</p>
    </div>
    <div class="wk-nav">
      <a href="{{ route('tag.week', ['datum' => $prev]) }}" aria-label="Woche davor">‹</a>
      @unless ($thisWeek)<a href="{{ route('tag.week') }}">Diese Woche</a>@endunless
      <a href="{{ route('tag.week', ['datum' => $next]) }}" aria-label="Woche danach">›</a>
      <form method="POST" action="{{ route('tag.weekmail') }}">@csrf<input type="hidden" name="on" value="{{ $weekMail ? '0' : '1' }}"><button type="submit">{{ $weekMail ? '✉️ E-Mail an' : '✉️ E-Mail aus' }}</button></form>
      <a href="#" data-push>🔔 Mitteilungen</a>
      <a href="{{ route('tag.index') }}">Mein Tag</a>
    </div>
  </div>

  <div class="wk-grid">
    <div style="display:flex;flex-direction:column;gap:16px">
      <section class="wk-card">
        <h2>Letzte Woche · {{ date('d.m.', strtotime($o['from'])) }} – {{ date('d.m.', strtotime($o['to'])) }}</h2>
        <div class="wk-stats">
          <div><b>{{ $o['todos_done'] }}</b><span>To-dos erledigt</span></div>
          <div><b>{{ $o['payments'] }}</b><span>Zahlungen · {{ Woche::money($o['payments_sum']) }}</span></div>
          <div><b>{{ $o['orders_done'] }}</b><span>Aufträge erledigt · {{ $o['orders_new'] }} neu</span></div>
        </div>
      </section>
      <section class="wk-card wk-open">
        <h2>Noch offen</h2>
        @forelse ($open as $x)
          <a href="{{ $x['href'] }}" style="--c:{{ $x['c'] }}"><b>{{ $x['n'] }}</b><span>{{ $x['label'] }}</span><span class="sd-muted" style="flex:none">öffnen ›</span></a>
        @empty
          <p class="sd-muted" style="margin:0">Nichts offen – alles erledigt. 🎉</p>
        @endforelse
      </section>
    </div>

    <section class="wk-card">
      <h2>Diese Woche · {{ $o['week_count'] }} {{ $o['week_count'] === 1 ? 'Eintrag' : 'Einträge' }}</h2>
      @forelse ($o['week'] as $d => $rows)
        <div class="wk-day"><a href="{{ route('tag.index', ['datum' => $d]) }}" style="color:inherit;text-decoration:none">{{ $wd[(int) date('N', strtotime($d))] }}, {{ date('d.m.', strtotime($d)) }}</a></div>
        @foreach ($rows as $r)
          <div class="wk-row">
            <time>{{ $r['time'] ?? '' }}</time>
            <span>@if (($r['url'] ?? '') !== '')<a href="{{ $r['url'] }}">{{ $r['title'] }}</a>@else{{ $r['title'] }}@endif</span>
            <em>{{ $label($r) }}</em>
          </div>
        @endforeach
      @empty
        <p class="sd-muted" style="margin:0">Keine Termine, To-dos oder Fristen.</p>
      @endforelse
    </section>
  </div>
</div>
@endsection
