@extends('layouts.app')
@section('title', 'Mietverträge')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Mietverträge</h1>
  <div class="sd-actions">
    <a class="sd-btn" href="{{ route('leases.create') }}">Mietvertrag anlegen</a>
    <a class="sd-btn sec" href="{{ route('rent.month') }}">Mieteingang</a>
    <a class="sd-btn sec" href="{{ route('rent.open') }}">Offene Posten</a>
    @if (auth()->user()->isAdmin())<a class="sd-btn sec" href="{{ route('contract-templates.index') }}">Vertragsvorlagen</a>@endif
  </div>
</div>

<form class="rt-filter" method="GET" action="{{ route('leases.index') }}">
  @if ($owners->count() > 1)
    <select name="eigentuemer" aria-label="Eigentümer">
      <option value="">Alle Eigentümer</option>
      @foreach ($owners as $o)
        <option value="{{ $o->id }}" @selected($ownerId === (int) $o->id)>{{ $o->name }}</option>
      @endforeach
    </select>
  @endif
  <select name="stand" aria-label="Stand">
    <option value="aktuell" @selected($state === 'aktuell')>Laufende und künftige</option>
    <option value="beendet" @selected($state === 'beendet')>Beendete</option>
    <option value="alle" @selected($state === 'alle')>Alle</option>
  </select>
  <button class="sd-btn sec small" type="submit">Anzeigen</button>
</form>

<div class="sd-card">
  <h2>Verträge ({{ $leases->count() }})</h2>
  @if ($leases->isEmpty())
    <p class="sd-muted" style="margin:0">Keine Verträge gefunden.@if ($state === 'aktuell') Lege den ersten Vertrag über „Mietvertrag anlegen“ an.@endif</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Einheit</th><th>Mieter</th><th>Art</th><th>Laufzeit</th><th class="rt-num">Miete aktuell</th><th>Stand</th></tr></thead>
        <tbody>
          @foreach ($leases as $lease)
            @php($rent = $lease->currentRent())
            <tr>
              <td><a href="{{ route('leases.show', $lease) }}">{{ $lease->unit?->label }}</a><br><span class="sd-muted">{{ $lease->unit?->property?->name }} · {{ $lease->owner?->name }}</span></td>
              <td>{{ $lease->tenant?->display_name }}</td>
              <td>{{ $lease->typeLabel() }}@if ((float) $lease->vat_rate > 0)<br><span class="sd-muted">zzgl. {{ \App\Support\Rent\Mieten::vatLabel($lease->vat_rate) }} USt</span>@endif</td>
              <td style="white-space:nowrap">ab {{ $lease->start_date->format('d.m.Y') }}@if ($lease->end_date)<br>bis {{ $lease->end_date->format('d.m.Y') }}@else<br><span class="sd-muted">unbefristet</span>@endif</td>
              <td class="rt-num">{{ $rent ? \App\Support\Money::format($rent->netTotal()) : '–' }}@if ((float) $lease->vat_rate > 0)<br><span class="sd-muted">netto</span>@endif</td>
              <td>{{ $lease->stateLabel() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="sd-card">
  <h2>Leerstand heute ({{ $vacant->count() }})</h2>
  @if ($vacant->isEmpty())
    <p class="sd-muted" style="margin:0">Alle Einheiten sind vermietet.</p>
  @else
    <p class="sd-muted" style="margin-top:0">Einheiten ohne laufenden Mietvertrag.</p>
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Einheit</th><th>Objekt</th><th>Nutzungsart</th><th class="rt-num">Fläche</th><th></th></tr></thead>
        <tbody>
          @foreach ($vacant as $unit)
            <tr>
              <td>{{ $unit->label }}</td>
              <td>{{ $unit->property?->name }}<br><span class="sd-muted">{{ $unit->property?->owner?->name }}</span></td>
              <td>{{ $unit->usageLabel() }}</td>
              <td class="rt-num">{{ $unit->area_sqm !== null ? number_format((float) $unit->area_sqm, 2, ',', '.').' m²' : '–' }}</td>
              <td><a class="sd-btn small" href="{{ route('leases.create', ['einheit' => $unit->id]) }}">Vertrag anlegen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
