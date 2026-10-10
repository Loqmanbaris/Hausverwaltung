@extends('layouts.app')
@section('title', $owner->exists ? 'Eigentümer bearbeiten' : 'Neuer Eigentümer')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>{{ $owner->exists ? 'Eigentümer bearbeiten' : 'Neuer Eigentümer' }}</h1>
</div>

<form method="POST" action="{{ $owner->exists ? route('owners.update', $owner) : route('owners.store') }}">
  @csrf
  @if ($owner->exists) @method('PUT') @endif

  <div class="sd-card">
    <h2>Name und Anschrift</h2>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'name', 'label' => 'Name', 'value' => $owner->name, 'required' => true, 'autocomplete' => 'off'])
      @include('partials.sd-field', ['name' => 'street', 'label' => 'Straße und Hausnummer', 'value' => $owner->street])
      @include('partials.sd-field', ['name' => 'zip', 'label' => 'PLZ', 'value' => $owner->zip, 'inputmode' => 'numeric'])
      @include('partials.sd-field', ['name' => 'city', 'label' => 'Ort', 'value' => $owner->city])
      @include('partials.sd-field', ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'value' => $owner->email])
      @include('partials.sd-field', ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'value' => $owner->phone])
    </div>
  </div>

  <div class="sd-card">
    <h2>Steuer</h2>
    <div class="sd-grid">
      @include('partials.sd-select', ['name' => 'tax_status', 'label' => 'Steuerstatus', 'options' => \App\Support\Stammdaten::TAX_STATUS, 'value' => $owner->tax_status, 'required' => true, 'noBlank' => true, 'hint' => 'Als Kleinunternehmer wird keine Umsatzsteuer ausgewiesen.'])
      @include('partials.sd-field', ['name' => 'tax_number', 'label' => 'Steuernummer', 'value' => $owner->tax_number, 'autocomplete' => 'off'])
      @include('partials.sd-field', ['name' => 'vat_id', 'label' => 'USt-ID', 'value' => $owner->vat_id, 'hint' => 'z. B. DE123456789, falls vorhanden.', 'autocomplete' => 'off'])
      @include('partials.sd-field', ['name' => 'creditor_id', 'label' => 'Gläubiger-ID (für Lastschriften)', 'value' => $owner->creditor_id, 'hint' => 'Nur nötig, wenn Lastschriften eingezogen werden.', 'autocomplete' => 'off'])
    </div>
  </div>

  <div class="sd-card">
    <h2>Notizen</h2>
    @include('partials.sd-textarea', ['name' => 'notes', 'label' => 'Interne Notizen', 'value' => $owner->notes])
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ $owner->exists ? route('owners.show', $owner) : route('owners.index') }}">Abbrechen</a>
  </div>
  @unless ($owner->exists)
    <p class="sd-muted">Die Bankverbindung trägst du nach dem Speichern auf der nächsten Seite ein.</p>
  @endunless
</form>
@endsection
