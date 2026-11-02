@extends('layouts.app')
@section('title', 'Einrichtung prüfen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.su-bar{height:10px;background:var(--surface-2);border-radius:10px;overflow:hidden;margin:.5rem 0 .3rem}
.su-bar i{display:block;height:100%;background:#067647;border-radius:10px}
.su-list{list-style:none;margin:0;padding:0}
.su-list li{display:grid;grid-template-columns:2rem minmax(0,1fr) auto;gap:.7rem;align-items:start;padding:.7rem .2rem;border-top:1px solid var(--line)}
.su-list li:first-child{border-top:0}
.su-ico{width:1.7rem;height:1.7rem;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-weight:800;font-size:.9rem}
.su-ico.ok{background:var(--ok-bg);color:var(--ok)}
.su-ico.offen{background:var(--danger-bg);color:var(--danger)}
.su-ico.optional{background:var(--surface-2);color:var(--muted)}
.su-t{font-weight:650}
.su-d{font-size:.88rem;color:var(--muted)}
</style>
<div class="sd-head">
  <h1>Einrichtung prüfen</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('status') }}">Status</a></div>
</div>
<div class="sd-card">
  <strong style="font-size:1.2rem">{{ $summary['done'] }} von {{ $summary['total'] }} Pflichtpunkten erledigt</strong>
  <div class="su-bar"><i style="width:{{ $summary['total'] > 0 ? round($summary['done'] / $summary['total'] * 100) : 0 }}%"></i></div>
  <span class="sd-muted">{{ $summary['open'] === 0 ? 'Alles Wichtige ist eingerichtet. Die grauen Punkte sind Empfehlungen.' : 'Rote Punkte bitte erledigen, graue sind Empfehlungen. Die Seite prüft alles bei jedem Aufruf neu.' }}</span>
</div>
@foreach (collect($checks)->groupBy('group') as $group => $items)
  <div class="sd-card">
    <h2>{{ $group }}</h2>
    <ul class="su-list">
      @foreach ($items as $c)
        <li>
          <span class="su-ico {{ $c['state'] }}">{{ $c['state'] === 'ok' ? '✓' : ($c['state'] === 'offen' ? '!' : '–') }}</span>
          <div><div class="su-t">{{ $c['label'] }}@if (! $c['required'] && $c['state'] !== 'ok') <span class="sd-muted" style="font-weight:400;font-size:.85rem">(empfohlen)</span>@endif</div><div class="su-d">{{ $c['text'] }}</div></div>
          <div>@if ($c['url'] && $c['state'] !== 'ok' && $c['link'] !== 'abhaken unten')<a class="sd-btn {{ $c['state'] === 'offen' ? '' : 'sec' }} small" href="{{ $c['url'] }}">{{ $c['link'] }}</a>@endif</div>
        </li>
      @endforeach
    </ul>
    @if ($group === 'Finanzen')
      <form method="POST" action="{{ route('setup.datev') }}" style="margin-top:.6rem">@csrf
        <label style="display:flex;gap:.5rem;align-items:center;font-weight:500"><input type="checkbox" name="confirmed" value="1" style="width:auto" onchange="this.form.submit()" @checked(\App\Support\Settings::get('datev_accounts_confirmed') === '1')> DATEV-Konten sind mit dem Steuerberater abgestimmt</label>
        <noscript><button class="sd-btn sec small" type="submit">Speichern</button></noscript>
      </form>
    @endif
  </div>
@endforeach
@endsection
