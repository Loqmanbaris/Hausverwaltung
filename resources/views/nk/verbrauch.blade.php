@extends('layouts.app')
@section('title', 'Verbrauchsinformation')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Nk\Verbrauchsinfo as V;
  $ym = str_replace('-', '', $month);
  $leaseRows = array_filter($info['rows'], fn ($r) => $r['lease'] !== null);
@endphp
<div class="sd-head">
  <h1>Verbrauchsinformation: {{ $property->name }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('nk.meters', $property) }}">Zähler und Stände</a>
    <a class="sd-btn sec" href="{{ route('consumption.index') }}">Übersicht</a>
  </div>
</div>

<form method="GET" action="{{ route('consumption.show', $property) }}" class="rt-filter">
  <label style="margin:0">Monat <input type="month" name="monat" value="{{ $month }}" max="{{ date('Y-m') }}" onchange="this.form.submit()"></label>
</form>

@if ($done)
  <div class="sd-ok">{{ $info['label'] }}: {{ $done->method === 'email' ? $done->count.' E-Mail(s) versendet' : 'als erledigt markiert' }} am {{ $done->created_at->timezone('Europe/Berlin')->format('d.m.Y') }}.</div>
@endif
@if (! $info['has_data'])
  <div class="rt-warn">Für {{ $info['label'] }} fehlen Zählerstände. Gebraucht wird je Zähler ein Stand zum Ende des Vormonats und zum Ende von {{ $info['label'] }} (bis zu 5 Tage daneben). <a href="{{ route('nk.meters', ['property' => $property, 'stichtag' => \Carbon\Carbon::parse($month.'-01')->endOfMonth()->format('Y-m-d')]) }}">Stände eintragen oder importieren</a>.</div>
@endif

<div class="sd-card">
  <h2>{{ $info['label'] }}</h2>
  @if ($info['rows'] === [])
    <p class="sd-muted" style="margin:0">Keine Zähler für Heizung oder Warmwasser in diesem Objekt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Einheit / Mieter</th><th class="rt-num">Heizung</th><th class="rt-num">Vormonat</th><th class="rt-num">Vorjahresmonat</th><th class="rt-num">Vergleich</th><th class="rt-num">Warmwasser</th><th></th></tr></thead>
        <tbody>
          @foreach ($info['rows'] as $r)
            <tr>
              <td>{{ $r['unit']->label }}<br><span class="sd-muted">{{ $r['lease']?->tenant?->display_name ?? 'Leerstand' }}</span>@unless ($r['remote'])<br><span class="sd-badge rt-late">nicht alle Zähler fernablesbar</span>@endunless</td>
              <td class="rt-num" data-l="Heizung">{{ $r['heat'] ? V::fmt($r['heat']['value']).' '.$r['heat_unit'] : '–' }}</td>
              <td class="rt-num" data-l="Vormonat">{{ $r['heat'] ? V::fmt($r['heat']['prev']) : '–' }}@if ($r['heat'] && V::change($r['heat']['value'], $r['heat']['prev']))<br><span class="sd-muted">{{ V::change($r['heat']['value'], $r['heat']['prev']) }}</span>@endif</td>
              <td class="rt-num" data-l="Vorjahresmonat">{{ $r['heat'] ? V::fmt($r['heat']['last_year']) : '–' }}@if ($r['heat'] && V::change($r['heat']['value'], $r['heat']['last_year']))<br><span class="sd-muted">{{ V::change($r['heat']['value'], $r['heat']['last_year']) }}</span>@endif</td>
              <td class="rt-num" data-l="Vergleich">@if ($r['heat'])Haus: {{ V::fmt($r['heat']['avg'] ?? null) }}@if (($r['heat']['bench'] ?? null) !== null)<br>Vergleichsnutzer: {{ V::fmt($r['heat']['bench']) }}@endif @else – @endif</td>
              <td class="rt-num" data-l="Warmwasser">{{ $r['water'] ? V::fmt($r['water']['value'], 2).' m³' : '–' }}@if ($r['water'] && $r['water']['prev'] !== null)<br><span class="sd-muted">Vormonat {{ V::fmt($r['water']['prev'], 2) }}</span>@endif</td>
              <td style="white-space:nowrap">@if ($r['lease'])<a class="sd-btn sec small" href="{{ route('consumption.print', ['property' => $property, 'monat' => $month, 'vertrag' => $r['lease']->id]) }}" target="_blank" rel="noopener">Ansehen</a>@endif</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if ($info['benchmark'] === null)<p class="sd-muted">Tipp: Einen Vergleichswert für einen durchschnittlichen Nutzer (kWh je m² und Jahr) kannst du in den <a href="{{ route('nk.settings', $property) }}#co2">Nebenkosten-Einstellungen</a> eintragen.</p>@endif
  @endif
</div>

@if ($leaseRows !== [])
  <div class="sd-card">
    <h2>An die Mieter senden</h2>
    <div class="sd-actions" style="justify-content:flex-start">
      <a class="sd-btn" href="{{ route('mail.compose', ['art' => 'verbrauch', 'ids' => $property->id.','.$ym]) }}">Allen per E-Mail senden</a>
      <a class="sd-btn sec" href="{{ route('consumption.print', ['property' => $property, 'monat' => $month]) }}" target="_blank" rel="noopener">Alle drucken</a>
      <form class="sd-inline" method="POST" action="{{ route('consumption.done', $property) }}">@csrf<input type="hidden" name="monat" value="{{ $month }}"><button class="sd-btn sec" type="submit">Als erledigt markieren</button></form>
    </div>
    <p class="sd-muted" style="margin-bottom:0">„Als erledigt markieren“, wenn du die Information auf anderem Weg gegeben hast (Ausdruck, Portal des Messdienstes).</p>
  </div>
@endif
@endsection
