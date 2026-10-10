@extends('layouts.app')
@section('title', 'Zähler und Verbrauch')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Nk\Verbrauchsinfo;
@endphp
<div class="sd-head">
  <h1>Zähler und Verbrauch</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('co2.index') }}">CO2-Erstattung</a>
    <a class="sd-btn sec" href="{{ route('nk.index') }}">Nebenkosten</a>
  </div>
</div>

<div class="sd-card">
  <h2>Fernablesbare Zähler (Frist 31.12.2026)</h2>
  @if ($heatTotal === 0)
    <p class="sd-muted" style="margin:0">Noch keine Zähler für Heizung oder Warmwasser angelegt. Zähler legst du beim Objekt unter „Zähler“ an.</p>
  @elseif ($notRemote->isEmpty())
    <p class="rt-ok" style="margin:0">Alle {{ $heatTotal }} Zähler für Heizung und Warmwasser sind fernablesbar.</p>
  @else
    <p style="margin-top:0"><strong class="rt-late">{{ $notRemote->count() }} von {{ $heatTotal }}</strong> Zählern für Heizung und Warmwasser sind noch nicht fernablesbar. Ab 2027 dürfen Mieter sonst ihren Anteil an den Heizkosten um 3 % kürzen (§ 12 Heizkostenverordnung), und die monatliche Verbrauchsinformation ist für alle Pflicht.</p>
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Objekt</th><th>Einheit</th><th>Zähler</th><th></th></tr></thead>
        <tbody>
          @foreach ($notRemote as $m)
            <tr><td>{{ $m->property?->name }}</td><td data-l="Einheit">{{ $m->unit?->label }}</td><td data-l="Zähler">{{ $m->title() }}</td><td><a class="sd-btn sec small" href="{{ route('nk.meters', $m->property_id) }}#zaehler-{{ $m->id }}">Bearbeiten</a></td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

@if ($expired->isNotEmpty())
  <div class="sd-card">
    <h2>Eichfrist</h2>
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Objekt</th><th>Einheit</th><th>Zähler</th><th>geeicht bis</th></tr></thead>
        <tbody>
          @foreach ($expired as $m)
            <tr><td>{{ $m->property?->name }}</td><td data-l="Einheit">{{ $m->unit?->label }}</td><td data-l="Zähler"><a href="{{ route('nk.meters', $m->property_id) }}#zaehler-{{ $m->id }}">{{ $m->title() }}</a></td><td data-l="geeicht bis" class="{{ $m->calibrationExpired() ? 'rt-late' : '' }}">31.12.{{ $m->calibrated_until }}{{ $m->calibrationExpired() ? ' – abgelaufen' : '' }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

<div class="sd-card">
  <h2>Monatliche Verbrauchsinformation</h2>
  <p class="sd-muted" style="margin-top:0">Pflicht für Häuser mit fernablesbaren Zählern (§ 6a Heizkostenverordnung): Jeder Mieter bekommt monatlich seinen Verbrauch des Vormonats mit Vergleichen. Dafür braucht jeder Zähler einen Stand zum Monatsende (von Hand oder per CSV-Import).</p>
  @if ($props->isEmpty())
    <p class="sd-muted" style="margin:0">Noch kein Objekt mit fernablesbaren Zählern.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Objekt</th><th>{{ Verbrauchsinfo::monthLabel($month) }}</th><th>Zuletzt</th><th></th></tr></thead>
        <tbody>
          @foreach ($props as $p)
            @php($list = $notices->get($p->id, collect()))
            @php($thisMonth = $list->first(fn ($n) => $n->month->format('Y-m') === $month))
            <tr>
              <td>{{ $p->name }}</td>
              <td data-l="{{ Verbrauchsinfo::monthLabel($month) }}">@if ($thisMonth)<span class="rt-ok">{{ $thisMonth->method === 'email' ? 'per E-Mail versendet' : 'erledigt' }}</span>@else<span class="rt-late">offen</span>@endif</td>
              <td data-l="Zuletzt">{{ $list->first() ? Verbrauchsinfo::monthLabel($list->first()->month->format('Y-m')) : '–' }}</td>
              <td><a class="sd-btn sec small" href="{{ route('consumption.show', ['property' => $p, 'monat' => $month]) }}">Öffnen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
