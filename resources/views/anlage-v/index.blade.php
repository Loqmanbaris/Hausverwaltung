@extends('layouts.app')
@section('title', 'Anlage V')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Tax\AnlageV;
  $eur = fn ($c) => \App\Support\Money::format($c / 100);
@endphp
<div class="sd-head">
  <h1>Anlage V {{ $year }}</h1>
  @if ($owner && $data)
    <div class="sd-actions">
      <a class="sd-btn" href="{{ route('anlage-v.print', ['eigentuemer' => $owner->id, 'jahr' => $year]) }}" target="_blank" rel="noopener">Drucken / PDF</a>
      <a class="sd-btn sec" href="{{ route('anlage-v.csv', ['eigentuemer' => $owner->id, 'jahr' => $year]) }}">Excel (CSV)</a>
    </div>
  @endif
</div>
<p class="sd-muted">Arbeitshilfe für die Steuererklärung: Einnahmen nach Zahlungseingang im Jahr, aufgeteilt nach Miete, Garagen und Umlagen; Werbungskosten aus den Belegen nach Belegdatum. Abschreibung (AfA) und Fahrtkosten sind nicht enthalten. Bitte vom Steuerberater prüfen lassen.</p>

<form class="rt-filter" method="GET" action="{{ route('anlage-v.index') }}">
  <select name="eigentuemer" aria-label="Eigentümer" required>
    <option value="">– Eigentümer wählen –</option>
    @foreach ($owners as $o)
      <option value="{{ $o->id }}" @selected($owner && (int) $owner->id === (int) $o->id)>{{ $o->name }}</option>
    @endforeach
  </select>
  <select name="jahr" aria-label="Jahr">
    @for ($y = (int) date('Y'); $y >= (int) date('Y') - 6; $y--)
      <option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>
    @endfor
  </select>
  <button class="sd-btn sec small" type="submit">Anzeigen</button>
</form>

@if ($owner && $data)
  <div class="rt-stats">
    <div class="rt-stat"><strong>{{ $eur($data['totals']['income']) }}</strong><span>Einnahmen</span></div>
    <div class="rt-stat"><strong>{{ $eur($data['totals']['costs']) }}</strong><span>Werbungskosten (ohne AfA)</span></div>
    <div class="rt-stat"><strong @class(['rt-late' => $data['totals']['result'] < 0])>{{ $eur($data['totals']['result']) }}</strong><span>Überschuss</span></div>
  </div>

  @if (count($data['properties']) === 0 && ($data['unassigned']['receipts'] + ($data['unassigned']['loans'] ?? 0)) === 0)
    <div class="sd-card"><p class="sd-muted" style="margin:0">Für {{ $owner->name }} gibt es {{ $year }} keine Zahlungen und keine Belege im System.</p></div>
  @endif

  @foreach ($data['properties'] as $o)
    <div class="sd-card">
      <h2>{{ $o['property']->name }} <span class="sd-muted" style="font-weight:400;font-size:.95rem">{{ $o['property']->addressLine() }}</span></h2>
      <div class="sd-table-wrap">
        <table class="sd">
          <tbody>
            <tr><td colspan="2"><strong>Einnahmen</strong> <span class="sd-muted">({{ $o['payments'] }} Zahlungen)</span></td></tr>
            @foreach (AnlageV::INCOME as $k => $label)
              @if ($o['income'][$k] !== 0)
                <tr><td>{{ $label }}</td><td class="rt-num">{{ $eur($o['income'][$k]) }}</td></tr>
              @endif
            @endforeach
            <tr class="rt-sum"><td>Summe Einnahmen</td><td class="rt-num">{{ $eur($o['income_total']) }}</td></tr>
            <tr><td colspan="2" style="padding-top:1rem"><strong>Werbungskosten</strong> <span class="sd-muted">({{ $o['receipts'] }} Belege)</span></td></tr>
            @foreach (AnlageV::COSTS as $k => $label)
              @if ($o['costs'][$k] !== 0)
                <tr><td>{{ $label }}<br><span class="sd-muted">@foreach ($o['cost_details'][$k] ?? [] as $l => $c){{ $l }} {{ $eur($c) }}{{ $loop->last ? '' : ' · ' }}@endforeach</span></td><td class="rt-num">{{ $eur($o['costs'][$k]) }}</td></tr>
              @endif
            @endforeach
            <tr class="rt-sum"><td>Summe Werbungskosten</td><td class="rt-num">{{ $eur($o['cost_total']) }}</td></tr>
            <tr class="rt-sum"><td>Überschuss (ohne AfA)</td><td class="rt-num">{{ $eur($o['result']) }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  @endforeach

  @if (($data['unassigned']['receipts'] + ($data['unassigned']['loans'] ?? 0)) > 0)
    <div class="sd-card">
      <h2>Belege ohne Objekt</h2>
      <p class="sd-muted" style="margin-top:0">Diese Kosten gelten für den ganzen Eigentümer und müssen auf die Objekte aufgeteilt werden, zum Beispiel nach Fläche oder Mieteinnahmen.</p>
      <table class="sd">
        <tbody>
          @foreach (AnlageV::COSTS as $k => $label)
            @if ($data['unassigned']['costs'][$k] !== 0)
              <tr><td>{{ $label }}</td><td class="rt-num">{{ $eur($data['unassigned']['costs'][$k]) }}</td></tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
@endif
@endsection
