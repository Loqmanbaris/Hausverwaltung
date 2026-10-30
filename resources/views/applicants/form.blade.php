@extends('layouts.app')
@section('title', $a->exists ? 'Interessent bearbeiten' : 'Interessent anlegen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Models\Applicant;
  $box = 'padding:.45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;width:100%';
  $v = fn ($k) => old($k, $a->{$k} instanceof \Carbon\CarbonInterface ? $a->{$k}->format('Y-m-d') : $a->{$k});
@endphp
<div class="sd-head">
  <h1>{{ $a->exists ? 'Interessent bearbeiten' : 'Interessent anlegen' }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ $a->exists ? route('applicants.show', $a) : route('applicants.index') }}">Abbrechen</a></div>
</div>
<form method="POST" action="{{ $a->exists ? route('applicants.update', $a) : route('applicants.store') }}" class="sd-card">
  @csrf @if ($a->exists) @method('PUT') @endif
  @if ($a->inquiry_id && ! $a->exists)<input type="hidden" name="inquiry_id" value="{{ $a->inquiry_id }}"><input type="hidden" name="listing_id" value="{{ $a->listing_id }}">@endif
  <div class="sd-grid">
    @include('partials.sd-field', ['name' => 'first_name', 'label' => 'Vorname', 'value' => $v('first_name')])
    @include('partials.sd-field', ['name' => 'last_name', 'label' => 'Nachname', 'value' => $v('last_name'), 'required' => true])
  </div>
  <div class="sd-grid">
    @include('partials.sd-field', ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email', 'value' => $v('email')])
    @include('partials.sd-field', ['name' => 'phone', 'label' => 'Telefon', 'value' => $v('phone')])
  </div>
  <div class="sd-grid">
    <div class="sd-field">
      <label for="f-unit_id">Wohnung</label>
      <select id="f-unit_id" name="unit_id" style="{{ $box }}">
        <option value="">– keine bestimmte –</option>
        @foreach ($units as $u)<option value="{{ $u->id }}" @selected((int) $v('unit_id') === $u->id)>{{ $u->property?->name }}: {{ $u->label }}</option>@endforeach
      </select>
    </div>
    <div class="sd-field">
      <label for="f-owner_id">Eigentümer (nur wenn keine Wohnung gewählt)</label>
      <select id="f-owner_id" name="owner_id" style="{{ $box }}">
        <option value="">–</option>
        @foreach ($owners as $o)<option value="{{ $o->id }}" @selected((int) $v('owner_id') === $o->id || $owners->count() === 1)>{{ $o->name }}</option>@endforeach
      </select>
    </div>
  </div>
  <div class="sd-grid">
    @include('partials.sd-field', ['name' => 'persons', 'label' => 'Anzahl Personen', 'type' => 'number', 'value' => $v('persons')])
    @include('partials.sd-field', ['name' => 'move_in', 'label' => 'Gewünschter Einzug', 'type' => 'date', 'value' => $v('move_in')])
    <div class="sd-field"><label for="f-source">Woher *</label><select id="f-source" name="source" style="{{ $box }}">@foreach (Applicant::SOURCES as $k => $label)<option value="{{ $k }}" @selected($v('source') === $k)>{{ $label }}</option>@endforeach</select></div>
  </div>
  <div class="sd-field"><label for="f-message">Nachricht des Interessenten</label><textarea id="f-message" name="message" rows="3" maxlength="3000" style="{{ $box }}">{{ $v('message') }}</textarea></div>
  <div class="sd-field"><label for="f-note">Interne Notiz</label><textarea id="f-note" name="note" rows="2" maxlength="3000" style="{{ $box }}">{{ $v('note') }}</textarea><small class="sd-muted">Nur sachliche Eindrücke. Merkmale wie Herkunft, Religion, Geschlecht, Alter oder Familienstand gehören hier nicht hin.</small></div>
  <button class="sd-btn" type="submit">Speichern</button>
</form>
@endsection
