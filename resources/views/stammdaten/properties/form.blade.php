@extends('layouts.app')
@section('title', $property->exists ? 'Objekt bearbeiten' : 'Neues Objekt')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>{{ $property->exists ? 'Objekt bearbeiten' : 'Neues Objekt' }}</h1>
</div>

<form method="POST" action="{{ $property->exists ? route('properties.update', $property) : route('properties.store') }}">
  @csrf
  @if ($property->exists) @method('PUT') @endif

  <div class="sd-card">
    <h2>Objekt</h2>
    <div class="sd-grid">
      @if ($property->exists)
        <div class="sd-field">
          <label>Eigentümer</label>
          <div>{{ $property->owner->name }}</div>
          <small class="sd-muted">Der Eigentümer kann nachträglich nicht geändert werden.</small>
        </div>
      @else
        @include('partials.sd-select', ['name' => 'owner_id', 'label' => 'Eigentümer', 'options' => $owners->pluck('name', 'id'), 'value' => $property->owner_id, 'required' => true])
      @endif
      @include('partials.sd-field', ['name' => 'name', 'label' => 'Bezeichnung', 'value' => $property->name, 'required' => true, 'hint' => 'z. B. Hauptstraße 12'])
      @include('partials.sd-field', ['name' => 'street', 'label' => 'Straße und Hausnummer', 'value' => $property->street])
      @include('partials.sd-field', ['name' => 'zip', 'label' => 'PLZ', 'value' => $property->zip, 'inputmode' => 'numeric'])
      @include('partials.sd-field', ['name' => 'city', 'label' => 'Ort', 'value' => $property->city])
    </div>
  </div>

  <div class="sd-card">
    <h2>Heizung</h2>
    <div class="sd-grid">
      @include('partials.sd-select', ['name' => 'heating_type', 'label' => 'Heizart', 'options' => \App\Support\Stammdaten::HEATING, 'value' => $property->heating_type])
      @include('partials.sd-select', ['name' => 'hot_water_type', 'label' => 'Warmwasser', 'options' => \App\Support\Stammdaten::HOT_WATER, 'value' => $property->hot_water_type])
    </div>
  </div>

  <div class="sd-card">
    <h2>Notizen</h2>
    @include('partials.sd-textarea', ['name' => 'notes', 'label' => 'Interne Notizen', 'value' => $property->notes])
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ $property->exists ? route('properties.show', $property) : route('properties.index') }}">Abbrechen</a>
  </div>
</form>
@endsection
