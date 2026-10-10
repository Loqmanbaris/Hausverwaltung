@extends('layouts.app')
@section('title', $unit->exists ? 'Einheit bearbeiten' : 'Neue Einheit')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>{{ $unit->exists ? 'Einheit bearbeiten' : 'Neue Einheit' }}</h1>
</div>
<p class="sd-muted">Objekt: <a href="{{ route('properties.show', $property) }}">{{ $property->name }}</a></p>

<form method="POST" action="{{ $unit->exists ? route('units.update', $unit) : route('units.store', $property) }}">
  @csrf
  @if ($unit->exists) @method('PUT') @endif

  <div class="sd-card">
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'label', 'label' => 'Bezeichnung', 'value' => $unit->label, 'required' => true, 'hint' => 'z. B. Wohnung 1 oder Laden EG'])
      @include('partials.sd-field', ['name' => 'floor', 'label' => 'Etage', 'value' => $unit->floor, 'hint' => 'z. B. EG, 1. OG'])
      @include('partials.sd-select', ['name' => 'usage_type', 'label' => 'Nutzungsart', 'options' => \App\Support\Stammdaten::USAGE, 'value' => $unit->usage_type, 'required' => true, 'noBlank' => true])
      @include('partials.sd-field', ['name' => 'area_sqm', 'label' => 'Fläche in m²', 'value' => $unit->area_sqm !== null ? str_replace('.', ',', (string) $unit->area_sqm) : '', 'inputmode' => 'decimal', 'hint' => 'z. B. 55,5'])
      @include('partials.sd-field', ['name' => 'rooms', 'label' => 'Zimmer', 'value' => $unit->rooms !== null ? str_replace('.', ',', rtrim(rtrim((string) $unit->rooms, '0'), '.')) : '', 'inputmode' => 'decimal', 'hint' => 'z. B. 2,5'])
    </div>
    @include('partials.sd-textarea', ['name' => 'equipment', 'label' => 'Ausstattung', 'value' => $unit->equipment])
    @include('partials.sd-textarea', ['name' => 'notes', 'label' => 'Interne Notizen', 'value' => $unit->notes])
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ route('properties.show', $property) }}">Abbrechen</a>
  </div>
</form>
@if ($unit->exists)
  <div style="margin-top:1.2rem">@include('documents.panel', ['docType' => 'unit', 'docId' => $unit->id])</div>
@endif
@endsection
