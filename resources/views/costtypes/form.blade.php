@extends('layouts.app')
@section('title', $type->exists ? 'Kostenart bearbeiten' : 'Kostenart hinzufügen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>{{ $type->exists ? 'Kostenart bearbeiten' : 'Kostenart hinzufügen' }}</h1>
</div>

<form method="POST" action="{{ $type->exists ? route('cost-types.update', $type) : route('cost-types.store') }}">
  @csrf
  @if ($type->exists) @method('PUT') @endif

  <div class="sd-card">
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'label', 'label' => 'Bezeichnung', 'value' => $type->label, 'required' => true, 'hint' => 'z. B. Rauchmelderwartung'])
      <div class="sd-field">
        <label for="f-group_label">Gruppe *</label>
        <input id="f-group_label" name="group_label" type="text" list="ct-groups" required value="{{ old('group_label', $type->group_label) }}">
        <datalist id="ct-groups">
          @foreach ($groupNames as $name)
            <option value="{{ $name }}"></option>
          @endforeach
        </datalist>
        <small class="sd-muted">Eine vorhandene Gruppe wählen oder eine neue eintippen.</small>
      </div>
      @if ($type->exists)
        @include('partials.sd-field', ['name' => 'sort', 'label' => 'Reihenfolge', 'value' => $type->sort, 'inputmode' => 'numeric', 'hint' => 'Kleine Zahl = weiter oben'])
      @endif
    </div>
    <label class="sd-check" for="f-allocatable" style="font-weight:400">
      <input type="checkbox" id="f-allocatable" name="allocatable" value="1" @checked((bool) old('allocatable', $type->allocatable))>
      Für die Nebenkostenabrechnung umlagefähig (Vorschlag, wird später mit dem Steuerberater geklärt)
    </label>
    @if ($type->exists)
      <label class="sd-check" for="f-active" style="font-weight:400">
        <input type="checkbox" id="f-active" name="active" value="1" @checked((bool) old('active', $type->active))>
        Aktiv (erscheint in der Auswahl)
      </label>
    @endif
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ route('cost-types.index') }}">Abbrechen</a>
  </div>
</form>
@endsection
