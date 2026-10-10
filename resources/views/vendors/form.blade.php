@extends('layouts.app')
@section('title', $vendor->exists ? 'Lieferant bearbeiten' : 'Lieferant anlegen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>{{ $vendor->exists ? 'Lieferant bearbeiten' : 'Lieferant anlegen' }}</h1>
</div>

<form method="POST" action="{{ $vendor->exists ? route('vendors.update', $vendor) : route('vendors.store') }}">
  @csrf
  @if ($vendor->exists) @method('PUT') @endif

  <div class="sd-card">
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'name', 'label' => 'Name', 'value' => $vendor->name, 'required' => true])
      @include('partials.sd-field', ['name' => 'street', 'label' => 'Straße und Hausnummer', 'value' => $vendor->street])
      @include('partials.sd-field', ['name' => 'zip', 'label' => 'PLZ', 'value' => $vendor->zip])
      @include('partials.sd-field', ['name' => 'city', 'label' => 'Ort', 'value' => $vendor->city])
      @include('partials.sd-field', ['name' => 'email', 'label' => 'E-Mail', 'value' => $vendor->email, 'type' => 'email'])
      @include('partials.sd-field', ['name' => 'phone', 'label' => 'Telefon', 'value' => $vendor->phone, 'type' => 'tel'])
      @include('partials.sd-field', ['name' => 'vat_id', 'label' => 'USt-ID', 'value' => $vendor->vat_id])
      <div class="sd-field">
        <label for="f-default_cost_category">Übliche Kostenart</label>
        <select id="f-default_cost_category" name="default_cost_category">
          <option value="">– keine –</option>
          @foreach (\App\Support\Kostenarten::groups($vendor->default_cost_category) as $group => $items)
            <optgroup label="{{ $group }}">
              @foreach ($items as $key => $label)
                <option value="{{ $key }}" @selected((string) old('default_cost_category', $vendor->default_cost_category) === (string) $key)>{{ $label }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
        <small class="sd-muted">Wird beim Erfassen eines Belegs vorgeschlagen.</small>
      </div>
    </div>
    @include('partials.sd-textarea', ['name' => 'note', 'label' => 'Notiz', 'value' => $vendor->note])
    <p class="sd-muted" style="margin:.2rem 0 0">Hier stehen bewusst keine Bankdaten.</p>
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ $vendor->exists ? route('vendors.show', $vendor) : route('vendors.index') }}">Abbrechen</a>
  </div>
</form>
@endsection
