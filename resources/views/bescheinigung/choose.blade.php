@extends('layouts.app')
@section('title', 'Schreiben an Mieter')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
<div class="sd-head"><h1>Schreiben an Mieter</h1></div>
<p class="sd-muted">Wohnungsgeberbestätigung, Mietschuldenfreiheitsbescheinigung, Abmahnung und Kündigung. Bitte den Mietvertrag wählen.</p>

<form class="rt-filter" method="GET" action="{{ route('bescheinigung.choose') }}">
  <input type="text" name="q" value="{{ $q }}" maxlength="80" placeholder="Mieter, Objekt oder Wohnung suchen" aria-label="Suche">
  <button class="sd-btn sec small" type="submit">Suchen</button>
</form>

<div class="sd-card">
  @if ($leases->isEmpty())
    <p class="sd-muted" style="margin:0">Kein Mietvertrag gefunden.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Mieter</th><th>Wohnung</th><th>Zeitraum</th><th></th></tr></thead>
        <tbody>
          @foreach ($leases as $l)
            <tr>
              <td>{{ $l->tenant?->display_name ?: '–' }}</td>
              <td data-l="Wohnung">{{ $l->unit?->property?->name }}, {{ $l->unit?->label }}</td>
              <td data-l="Zeitraum">ab {{ $l->start_date->format('d.m.Y') }}@if ($l->end_date) bis {{ $l->end_date->format('d.m.Y') }}@endif
                @if ($l->start_date->format('Y-m-d') > $today)<br><span class="rt-part">Einzug steht bevor</span>@elseif ($l->end_date && $l->end_date->format('Y-m-d') < $today)<br><span class="sd-muted">beendet</span>@elseif ($l->end_date)<br><span class="rt-part">Auszug steht bevor</span>@endif
              </td>
              <td><a class="sd-btn small" href="{{ route('bescheinigung.index', $l) }}">Auswählen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
