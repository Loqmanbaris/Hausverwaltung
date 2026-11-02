@extends('layouts.app')
@section('title', 'Übersicht')
@section('content')
@include('partials.sd-styles')
<style>
.db-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(10rem,1fr));gap:.9rem;margin:1rem 0}
.db-stat{background:var(--surface);border:1px solid var(--line);border-radius:.6rem;padding:.9rem 1rem}
.db-stat strong{display:block;font-size:1.7rem;line-height:1.1;color:var(--ink)}
.db-stat span{color:var(--muted);font-size:.92rem}
.db-filter{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:.5rem 0 0}
.db-filter select{padding:.5rem;border:1px solid var(--line-strong);border-radius:.45rem;font:inherit;background:var(--surface)}
.db-list{margin:.4rem 0 0;padding-left:1.2rem}
.db-list li{margin:.15rem 0}
.db-num{text-align:right;white-space:nowrap}
.ck-head{display:flex;flex-wrap:wrap;align-items:flex-end;justify-content:space-between;gap:.6rem 1rem;margin-bottom:1rem}
.ck-head h1{margin:0}
.ck-head p{margin:.15rem 0 0}
.ck-head .db-filter{margin:0;flex-wrap:nowrap}
.ck-head .db-filter select{max-width:16rem}
.ck-money{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.9rem;margin:0 0 1rem}
.ck-tile{background:var(--surface);border:1px solid var(--line);border-radius:var(--radius);padding:1rem 1.1rem;text-decoration:none;color:inherit;display:block;box-shadow:0 1px 2px rgba(17,24,39,.04)}
a.ck-tile:hover{border-color:var(--line-strong)}
.ck-tile .k{font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:600}
.ck-tile .v{display:block;font-size:1.65rem;font-weight:750;line-height:1.15;margin:.3rem 0 .2rem;color:var(--ink)}
.ck-tile .s{font-size:.88rem;color:var(--muted)}
.ck-bar{height:6px;background:var(--surface-2);border-radius:6px;overflow:hidden;margin-top:.55rem}
.ck-bar i{display:block;height:100%;background:var(--brand);border-radius:6px}
.ck-red{color:var(--danger) !important}
.ck-green{color:var(--ok) !important}
.ck-grid{display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:1rem;align-items:stretch}
.ck-block{min-width:0}
.ck-block.wide{grid-column:1/-1}
.ck-block.has-color > .sd-card{border-top:4px solid var(--tile);background:linear-gradient(180deg,var(--tile-soft) 0,var(--surface) 6rem)}
.ck-block.has-color > .sd-card > h2,.ck-block.has-color > .sd-card > summary{display:flex;align-items:center;gap:.5rem}
.ck-block.has-color > .sd-card > h2::before,.ck-block.has-color > .sd-card > summary::before{content:"";width:.6rem;height:.6rem;border-radius:3px;background:var(--tile);flex:none}
.ck-block.has-color .ck-num:not(.warn){background:var(--tile-soft);color:var(--tile)}
.ck-block.has-color .ck-day:not(.warn){border-color:color-mix(in srgb,var(--tile) 30%,var(--surface));background:var(--tile-soft)}
.ck-block.has-color .ck-day:not(.warn) b{color:var(--tile)}
.ck-tile[style]{border-left:4px solid var(--tile);background:linear-gradient(135deg,var(--tile-soft) 0,var(--surface) 75%)}
.ck-tile[style] .k{color:var(--tile)}
.ck-tile[style] .ck-bar i{background:var(--tile)}
.ck-block.has-color .ck-qbtn{border-color:color-mix(in srgb,var(--tile) 35%,var(--surface));background:var(--tile-soft)}
.ck-block > .sd-card,.ck-block > details.sd-card{margin:0;height:100%}
.ck-block > .ck-money{margin:0}
.ck-tools{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
.ck-quick{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.ck-qbtn{display:inline-flex;align-items:center;gap:.4rem;background:var(--surface);border:1px solid var(--line-strong);border-radius:999px;padding:.45rem .95rem;text-decoration:none;color:var(--ink);font-weight:600;font-size:.92rem}
.ck-qbtn::before{content:"+";color:var(--brand);font-weight:800}
.ck-qbtn:hover{border-color:var(--brand);color:var(--ink)}
.ck-qedit{font-size:.85rem;color:var(--muted);margin-left:.3rem}
.ck-mini{display:grid;grid-template-columns:repeat(12,1fr);gap:.3rem;align-items:end;height:90px;margin:.6rem 0 .4rem}
.ck-mcol{display:flex;flex-direction:column;align-items:center;height:100%;gap:.2rem}
.ck-mcol span{font-size:.72rem;color:var(--muted)}
.ck-mbar{flex:1;width:100%;display:flex;align-items:flex-end;background:var(--surface-2);border-radius:4px}
.ck-mbar i{display:block;width:100%;background:var(--brand);border-radius:4px}
.ck-cats{list-style:none;margin:.6rem 0 0;padding:0}
.ck-cats li{padding:.35rem 0}
.ck-cats .t{display:flex;justify-content:space-between;gap:.6rem;font-size:.92rem}
.ck-todo{list-style:none;margin:0;padding:0}
.ck-todo li{border-top:1px solid var(--line)}
.ck-todo li:first-child{border-top:0}
.ck-todo a{display:flex;align-items:center;gap:.7rem;padding:.6rem .1rem;text-decoration:none;color:var(--ink)}
.ck-todo a:hover{background:var(--surface-2)}
.ck-num{min-width:2rem;height:1.6rem;padding:0 .45rem;border-radius:1rem;display:inline-flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;background:var(--surface-2);color:var(--ink);flex:none}
.ck-num.warn{background:var(--danger-bg);color:var(--danger)}
.ck-todo .go{margin-left:auto;color:var(--muted)}
.ck-done{display:flex;align-items:center;gap:.6rem;color:var(--ok);font-weight:600;margin:.2rem 0}
.ck-dates{list-style:none;margin:0;padding:0}
.ck-dates li{display:grid;grid-template-columns:3.4rem minmax(0,1fr);gap:.8rem;padding:.55rem 0;border-top:1px solid var(--line)}
.ck-dates li:first-child{border-top:0}
.ck-day{text-align:center;border:1px solid var(--line);border-radius:8px;padding:.2rem 0;line-height:1.1;background:var(--surface-2)}
.ck-day b{display:block;font-size:1.1rem}
.ck-day span{font-size:.7rem;text-transform:uppercase;color:var(--muted);letter-spacing:.04em}
.ck-day.warn{border-color:#f5c2be;background:var(--danger-bg)}
.ck-dates a{color:var(--ink);font-weight:600;text-decoration:none}
.ck-dates a:hover{text-decoration:underline}
.ck-dates .d{font-size:.88rem;color:var(--muted)}
.ck-chart{width:100%;height:auto;display:block}
.ck-legend{display:flex;flex-wrap:wrap;gap:1rem;font-size:.82rem;color:var(--muted);margin-top:.3rem}
.ck-legend i{display:inline-block;width:.7rem;height:.7rem;border-radius:2px;margin-right:.35rem;vertical-align:-1px}
.ck-quote{display:flex;flex-wrap:wrap;align-items:baseline;gap:.2rem .6rem;margin:.1rem 0 .3rem}
.ck-quote strong{white-space:nowrap}
.ck-quote strong{font-size:1.6rem}
.ck-vac{list-style:none;margin:.6rem 0 0;padding:0}
.ck-vac li{display:flex;justify-content:space-between;gap:.6rem;padding:.45rem 0;border-top:1px solid var(--line);font-size:.93rem}
.ck-tag{font-size:.75rem;font-weight:700;border-radius:1rem;padding:.08rem .5rem;white-space:nowrap;align-self:center}
.ck-tag.on{background:var(--ok-bg);color:var(--ok)}
.ck-tag.off{background:var(--surface-2);color:var(--muted)}
.ck-tag.next{background:var(--info-bg);color:var(--info)}
.ck-sub{font-size:.8rem;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:700;margin:1rem 0 .2rem}
details.ck-more > summary{cursor:pointer;font-weight:700;font-size:1.05rem;list-style:none}
details.ck-more > summary::after{content:" ▾";color:var(--muted)}
details.ck-more[open] > summary::after{content:" ▴"}
@media (max-width:1100px){.ck-money{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media (max-width:900px){.ck-grid{grid-template-columns:minmax(0,1fr)}}
@media (max-width:560px){.ck-tile{padding:.8rem .85rem}.ck-tile .v{font-size:1.2rem}.ck-tile .k{font-size:.7rem}.ck-tile .s{font-size:.8rem}.ck-money{gap:.6rem}}
</style>

@php($ck = $cockpit)
@php($M = fn ($v) => \App\Support\Money::format($v))
@php($monthNames = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'])
@php($dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'])
<div class="ck-head">
  <div>
    <h1>Guten Tag, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
    <p class="sd-muted">{{ $dayNames[(int) date('w')] }}, {{ date('j') }}. {{ $monthNames[(int) date('n')] }} {{ date('Y') }} · {{ auth()->user()->roleLabel() }}</p>
  </div>
  <div class="ck-tools">
  <a class="sd-btn sec small" href="{{ route('dashboard.layout') }}">Übersicht anpassen</a>
  @if ($hasOwners && $owners->count() > 1)
    <form class="db-filter" method="GET" action="{{ route('dashboard') }}">
      <label for="db-owner" class="sd-muted" style="margin:0;font-weight:600;display:inline">Anzeigen für</label>
      <select id="db-owner" name="eigentuemer" onchange="this.form.submit()">
        <option value="">Alle Eigentümer</option>
        @foreach ($owners as $owner)
          <option value="{{ $owner->id }}" @selected($selected && (int) $selected->id === (int) $owner->id)>{{ $owner->name }}</option>
        @endforeach
      </select>
      <noscript><button class="sd-btn sec small" type="submit">Anzeigen</button></noscript>
    </form>
  @endif
  </div>
</div>

@if (auth()->user()->isAdmin() && class_exists(\App\Support\Setup\Einrichtung::class))
  @php($setup = \App\Support\Setup\Einrichtung::summary())
  @if ($setup['open'] > 0)
    <div class="sd-card" style="display:flex;flex-wrap:wrap;gap:.6rem 1rem;align-items:center;justify-content:space-between;border-left:4px solid var(--brand)">
      <div><strong>Einrichtung: {{ $setup['done'] }} von {{ $setup['total'] }} erledigt.</strong> <span class="sd-muted">Noch {{ $setup['open'] }} {{ $setup['open'] === 1 ? 'Punkt' : 'Punkte' }} offen.</span></div>
      <a class="sd-btn small" href="{{ route('setup.index') }}">Einrichtung prüfen</a>
    </div>
  @endif
@endif

@if ($backupAttention)
  <div class="sd-err" style="background:var(--brass-bg);border-color:var(--brass-line);color:var(--brass)">
    <strong>Sicherung:</strong> {{ $backupAttention }}
    <a href="{{ route('backups.index') }}">Zu den Sicherungen</a>
  </div>
@endif

@if ($imprintIncomplete)
  <div class="sd-err" style="background:var(--brass-bg);border-color:var(--brass-line);color:var(--brass)">
    <strong>Impressum und Startseite sind noch nicht ausgefüllt.</strong>
    Ohne diese Angaben darf die Webseite nicht öffentlich erreichbar sein.
    <a href="{{ route('settings.edit') }}">Jetzt unter „Einstellungen“ eintragen</a>
  </div>
@endif

@if (! $hasOwners)
  <div class="sd-card">
    @if (auth()->user()->isAdmin())
      <p style="margin:0">Es ist noch kein Eigentümer angelegt. Lege zuerst die Eigentümer an, danach Objekte, Einheiten und Mieter.</p>
    @else
      <p style="margin:0">Dir wurde noch kein Eigentümer freigegeben. Der Administrator stellt das beim jeweiligen Eigentümer unter „Freigaben“ ein.</p>
    @endif
  </div>
@endif

<div class="ck-grid">
  @foreach ($blocks as $b)
    @php($k = $b['key'])
    @php($show = in_array($k, ['quick', 'note'], true) || ($k === 'recent' ? $recent->isNotEmpty() : ($hasOwners && $ck && ($k === 'checks' ? count($checks) > 0 : ($k === 'money' ? (bool) $ck['money'] : true)))))
    @if ($show)
      <section @class(['ck-block', 'wide' => $b['wide'], 'has-color' => $b['style'] !== '']) data-block="{{ $k }}" data-color="{{ $b['color'] }}" @if ($b['style'] !== '') style="{{ $b['style'] }}" @endif>
        @include('dashboard-blocks.'.$k)
      </section>
    @endif
  @endforeach
</div>
@endsection
