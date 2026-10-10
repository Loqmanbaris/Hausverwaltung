@extends('layouts.app')
@section('title', 'Objekte')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Objekte</h1>
  <a class="sd-btn" href="{{ route('properties.create') }}">Neues Objekt</a>
</div>

@if ($properties->isEmpty())
  <p class="sd-muted">Noch kein Objekt vorhanden. Für ein Objekt muss zuerst ein Eigentümer angelegt bzw. freigegeben sein.</p>
@else
  @foreach ($properties as $ownerName => $items)
    <div class="sd-card">
      <h2>{{ $ownerName }}</h2>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Objekt</th><th>Anschrift</th><th>Heizart</th><th>Einheiten</th></tr></thead>
          <tbody>
          @foreach ($items as $property)
            <tr>
              <td><a href="{{ route('properties.show', $property) }}">{{ $property->name }}</a></td>
              <td>{{ $property->addressLine() ?: '–' }}</td>
              <td>{{ $property->heatingLabel() }}</td>
              <td>{{ $property->units_count }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endforeach
@endif
@endsection
