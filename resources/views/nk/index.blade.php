@extends('layouts.app')
@section('title', 'Nebenkostenabrechnung')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php use App\Support\Money; @endphp
<div class="sd-head"><h1>Nebenkostenabrechnung</h1></div>
<p class="sd-muted">Ablauf: Je Objekt zuerst <strong>Einstellungen</strong> (Heizkosten, Umlageschlüssel, Vorwegabzug Gewerbe) und <strong>Zählerstände</strong> prüfen, bei den Mietverträgen die <strong>Personenzahl</strong> eintragen, falls nach Personen verteilt wird. Dann hier die Abrechnung anlegen. Die Kosten kommen aus den Belegen mit umlagefähiger Kostenart. Die Abrechnung muss dem Mieter spätestens zwölf Monate nach Ende des Abrechnungszeitraums zugehen (für 2025 also bis 31.12.2026).</p>

<div class="sd-card">
  <h2>Neue Abrechnung</h2>
  <form method="POST" action="{{ route('nk.store') }}" class="rt-filter" style="margin:0">
    @csrf
    <select name="property_id" required aria-label="Objekt">
      <option value="">– Objekt wählen –</option>
      @foreach ($properties as $p)
        <option value="{{ $p->id }}" @selected((string) old('property_id') === (string) $p->id)>{{ $p->name }} ({{ $p->owner?->name }})</option>
      @endforeach
    </select>
    <label style="margin:0">vom <input type="date" name="period_from" value="{{ old('period_from', $defaultYear.'-01-01') }}" required></label>
    <label style="margin:0">bis <input type="date" name="period_to" value="{{ old('period_to', $defaultYear.'-12-31') }}" required></label>
    <button class="sd-btn" type="submit">Abrechnung anlegen</button>
  </form>
</div>

<div class="sd-card">
  <h2>Abrechnungen</h2>
  @if ($settlements->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine Abrechnung angelegt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Objekt</th><th>Zeitraum</th><th>Stand</th><th></th></tr></thead>
        <tbody>
          @foreach ($settlements as $s)
            <tr>
              <td><a href="{{ route('nk.show', $s) }}">{{ $s->property?->name }}</a><br><span class="sd-muted">{{ $s->owner?->name }}</span></td>
              <td data-l="Zeitraum">{{ $s->periodLabel() }}</td>
              <td data-l="Stand">@if ($s->isFinal())<span class="rt-ok">abgeschlossen am {{ $s->issued_on?->format('d.m.Y') }}</span>@else<span class="rt-part">Entwurf</span>@endif</td>
              <td><a class="sd-btn sec small" href="{{ route('nk.show', $s) }}">Öffnen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="sd-card">
  <h2>Vorbereitung je Objekt</h2>
  <div class="sd-table-wrap">
    <table class="sd rt-stack">
      <tbody>
        @foreach ($properties as $p)
          <tr>
            <td>{{ $p->name }}<br><span class="sd-muted">{{ $p->owner?->name }}</span></td>
            <td><a class="sd-btn sec small" href="{{ route('nk.settings', $p) }}">Einstellungen</a> <a class="sd-btn sec small" href="{{ route('nk.meters', $p) }}">Zähler</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>
@endsection
