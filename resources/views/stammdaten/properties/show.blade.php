@extends('layouts.app')
@section('title', $property->name)
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php($isAdmin = auth()->user()->isAdmin())
<div class="sd-head">
  <h1>{{ $property->name }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('properties.edit', $property) }}">Bearbeiten</a>
    <a class="sd-btn sec" href="{{ route('mail.compose', ['art' => 'rundschreiben', 'ids' => $property->id]) }}">Rundschreiben</a>
    <a class="sd-btn sec" href="{{ route('nk.settings', $property) }}">Nebenkosten</a>
    <a class="sd-btn sec" href="{{ route('nk.meters', $property) }}">Zähler</a>
    <a class="sd-btn sec" href="{{ route('properties.index') }}">Alle Objekte</a>
  </div>
</div>

<div class="sd-card">
  <h2>Objektdaten</h2>
  <dl class="sd-dl">
    <dt>Eigentümer</dt><dd><a href="{{ route('owners.show', $property->owner) }}">{{ $property->owner->name }}</a></dd>
    <dt>Anschrift</dt><dd>{{ $property->addressLine() ?: '–' }}</dd>
    <dt>Heizart</dt><dd>{{ $property->heatingLabel() }}</dd>
    <dt>Warmwasser</dt><dd>{{ $property->hotWaterLabel() }}</dd>
    @if ($property->notes)
      <dt>Notizen</dt><dd style="white-space:pre-line">{{ $property->notes }}</dd>
    @endif
  </dl>
</div>

<div class="sd-card">
  <h2>Einheiten</h2>
  @if ($property->units->isEmpty())
    <p class="sd-muted">Noch keine Einheit angelegt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Einheit</th><th>Etage</th><th>Nutzungsart</th><th>Fläche</th><th>Zimmer</th><th>Mieter</th><th></th></tr></thead>
        <tbody>
        @foreach ($property->units as $unit)
          <tr>
            <td><a href="{{ route('units.edit', $unit) }}">{{ $unit->label }}</a></td>
            <td>{{ $unit->floor ?: '–' }}</td>
            <td>{{ $unit->usageLabel() }}</td>
            <td style="white-space:nowrap">{{ $unit->area_sqm !== null ? number_format((float) $unit->area_sqm, 2, ',', '.').' m²' : '–' }}</td>
            <td>{{ $unit->rooms !== null ? rtrim(rtrim(number_format((float) $unit->rooms, 1, ',', '.'), '0'), ',') : '–' }}</td>
            <td>
              @php($unitLease = \App\Models\Lease::query()->where('unit_id', $unit->id)->activeOn(date('Y-m-d'))->with('tenant')->first())
              @if ($unitLease)
                <a href="{{ route('leases.show', $unitLease) }}">{{ $unitLease->tenant?->display_name }}</a>
              @else
                <span class="sd-muted">leer</span> · <a href="{{ route('leases.create', ['einheit' => $unit->id]) }}">Vertrag anlegen</a>
                · <a href="{{ route('listings.create', ['einheit' => $unit->id]) }}">Inserieren</a>
              @endif
              @if ($unit->usage_type === 'montage')
                · <a href="{{ route('bookings.index', $unit) }}">Belegung</a>
              @endif
            </td>
            <td>
              @if ($isAdmin)
                <form class="sd-inline" method="POST" action="{{ route('units.destroy', $unit) }}" onsubmit="return confirm('Diese Einheit wirklich löschen?')">
                  @csrf @method('DELETE')
                  <button class="sd-btn danger small" type="submit">Löschen</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
    <p class="sd-muted">Gesamtfläche: {{ number_format((float) $property->units->sum('area_sqm'), 2, ',', '.') }} m²</p>
  @endif
  <p><a class="sd-btn small" href="{{ route('units.create', $property) }}">Einheit anlegen</a></p>
</div>

@include('finance.property-card', ['property' => $property])

@if (\Illuminate\Support\Facades\Schema::hasTable('work_orders'))
  @php($woOpen = \App\Models\WorkOrder::query()->where('property_id', $property->id)->open()->orderByDesc('id')->get())
  @php($mtDue = \App\Models\MaintenanceTask::query()->where('property_id', $property->id)->where('active', true)->orderBy('next_due')->get())
  <div class="sd-card" id="instandhaltung">
    <h2>Schäden und Wartung</h2>
    <p style="margin:0 0 .5rem">{{ $woOpen->count() }} offene{{ $woOpen->count() === 1 ? 'r Schadensfall' : ' Schadensfälle' }} · {{ $mtDue->count() }} Wartung{{ $mtDue->count() === 1 ? '' : 'en' }} geplant{{ $mtDue->first() ? ', nächste: '.$mtDue->first()->title.' am '.$mtDue->first()->next_due->format('d.m.Y') : '' }}</p>
    @foreach ($woOpen->take(5) as $wo)
      <div><a href="{{ route('workorders.show', $wo) }}">{{ $wo->title }}</a> <span class="sd-muted">· {{ $wo->statusLabel() }}</span></div>
    @endforeach
    <div class="sd-actions" style="margin-top:.6rem">
      <a class="sd-btn sec small" href="{{ route('workorders.create', ['objekt' => $property->id]) }}">Schaden erfassen</a>
      <a class="sd-btn sec small" href="{{ route('maintenance.index', ['objekt' => $property->id]) }}">Wartungsplan</a>
    </div>
  </div>
@endif

@include('documents.panel', ['docType' => 'property', 'docId' => $property->id])

@if ($isAdmin)
  <div class="sd-card">
    <h2>Objekt löschen</h2>
    <p class="sd-muted">Nur möglich, wenn keine Einheiten mehr vorhanden sind.</p>
    <form method="POST" action="{{ route('properties.destroy', $property) }}" onsubmit="return confirm('Dieses Objekt wirklich löschen?')">
      @csrf @method('DELETE')
      <button class="sd-btn danger" type="submit">Objekt löschen</button>
    </form>
  </div>
@endif
@endsection
