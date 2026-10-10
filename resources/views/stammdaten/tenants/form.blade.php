@extends('layouts.app')
@section('title', $tenant->exists ? 'Mieter bearbeiten' : 'Neuer Mieter')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>{{ $tenant->exists ? 'Mieter bearbeiten' : 'Neuer Mieter' }}</h1>
</div>

<form method="POST" action="{{ $tenant->exists ? route('tenants.update', $tenant) : route('tenants.store') }}">
  @csrf
  @if ($tenant->exists) @method('PUT') @endif

  <div class="sd-card">
    <h2>Mieter</h2>
    <div class="sd-grid">
      @if ($tenant->exists)
        <div class="sd-field">
          <label>Eigentümer</label>
          <div>{{ $tenant->owner->name }}</div>
          <small class="sd-muted">Der Eigentümer kann nachträglich nicht geändert werden.</small>
        </div>
      @else
        @include('partials.sd-select', ['name' => 'owner_id', 'label' => 'Eigentümer', 'options' => $owners->pluck('name', 'id'), 'value' => $tenant->owner_id, 'required' => true])
      @endif
      @include('partials.sd-select', ['name' => 'type', 'label' => 'Art', 'options' => \App\Support\Stammdaten::TENANT_TYPE, 'value' => $tenant->type, 'required' => true, 'noBlank' => true])
    </div>

    <div class="sd-grid" data-for="firma">
      @include('partials.sd-field', ['name' => 'company_name', 'label' => 'Firmenname (Pflichtfeld)', 'value' => $tenant->company_name])
      @include('partials.sd-field', ['name' => 'vat_id', 'label' => 'USt-ID der Firma', 'value' => $tenant->vat_id, 'hint' => 'z. B. DE123456789, falls bekannt.'])
    </div>
    <div class="sd-grid" data-for="person">
      @include('partials.sd-field', ['name' => 'first_name', 'label' => 'Vorname', 'value' => $tenant->first_name])
      @include('partials.sd-field', ['name' => 'last_name', 'label' => 'Nachname (Pflichtfeld)', 'value' => $tenant->last_name])
    </div>
  </div>

  <div class="sd-card">
    <h2>Anschrift und Kontakt</h2>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'street', 'label' => 'Straße und Hausnummer', 'value' => $tenant->street])
      @include('partials.sd-field', ['name' => 'zip', 'label' => 'PLZ', 'value' => $tenant->zip, 'inputmode' => 'numeric'])
      @include('partials.sd-field', ['name' => 'city', 'label' => 'Ort', 'value' => $tenant->city])
      @include('partials.sd-field', ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'value' => $tenant->email])
      @include('partials.sd-field', ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel', 'value' => $tenant->phone])
    </div>
    @include('partials.sd-textarea', ['name' => 'notes', 'label' => 'Interne Notizen', 'value' => $tenant->notes])
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ $tenant->exists ? route('tenants.show', $tenant) : route('tenants.index') }}">Abbrechen</a>
  </div>
</form>

<script>
(function () {
  var type = document.getElementById('f-type');
  if (!type) { return; }
  function sync() {
    var firma = type.value === 'firma';
    document.querySelectorAll('[data-for="firma"]').forEach(function (el) { el.style.display = firma ? '' : 'none'; });
    document.querySelectorAll('[data-for="person"]').forEach(function (el) { el.style.display = firma ? 'none' : ''; });
  }
  type.addEventListener('change', sync);
  sync();
})();
</script>
@endsection
