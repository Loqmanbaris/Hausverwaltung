@extends('layouts.app')
@section('title', 'Kostenarten')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Kostenarten</h1>
  <div class="sd-actions">
    <a class="sd-btn" href="{{ route('cost-types.create') }}">Kostenart hinzufügen</a>
    <a class="sd-btn sec" href="{{ route('receipts.index') }}">Zu den Belegen</a>
  </div>
</div>
<p class="sd-muted">Mit Kostenarten ordnest du Belege ein. Gelöscht wird nichts: Eine Kostenart, die du nicht mehr brauchst, legst du still. Sie erscheint dann nicht mehr in der Auswahl, alte Belege behalten sie. „Umlagefähig“ ist nur ein Vorschlag für die spätere Nebenkostenabrechnung. Die endgültige Einstufung legen wir mit dem Steuerberater fest.</p>

@foreach ($groups as $group => $types)
  <div class="sd-card">
    <h2>{{ $group }}</h2>
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Bezeichnung</th><th>Umlagefähig (Vorschlag)</th><th>Belege</th><th>Stand</th><th></th></tr></thead>
        <tbody>
          @foreach ($types as $type)
            <tr>
              <td>{{ $type->label }}</td>
              <td>{{ $type->allocatable ? 'ja' : 'nein' }}</td>
              <td>{{ $usage[$type->key] ?? 0 }}</td>
              <td>@if ($type->active) aktiv @else <span class="sd-muted">stillgelegt</span> @endif</td>
              <td><a class="sd-btn sec small" href="{{ route('cost-types.edit', $type) }}">Bearbeiten</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endforeach
@endsection
