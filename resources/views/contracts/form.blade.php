@extends('layouts.app')
@section('title', $contract ? 'Vertrag bearbeiten' : 'Mietvertrag erstellen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Support\Contracts\Vorlagen;
  $unit = $lease->unit;
  $val = fn ($k) => old('x.'.$k, $extras[$k] ?? '');
@endphp
<style>
.cf-sum{display:grid;grid-template-columns:max-content 1fr;gap:.3rem 1rem;margin:0}
.cf-sum dt{color:#475467}.cf-sum dd{margin:0;white-space:pre-line}
.cf-warn{background:#fffaeb;border:1px solid #fedf89;border-radius:.6rem;padding:.6rem .9rem;margin:0 0 1rem;color:#7a4b00}
.cf-warn ul{margin:.3rem 0 0;padding-left:1.1rem}
</style>
<div class="sd-head">
  <h1>{{ $contract ? 'Vertrag bearbeiten' : Vorlagen::TITLES[$kind].' erstellen' }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('leases.show', $lease) }}">Zurück zum Mietvertrag</a>
    @if (auth()->user()->isAdmin())<a class="sd-btn sec" href="{{ route('contract-templates.edit', $kind) }}">Vorlage anpassen</a>@endif
  </div>
</div>
<p class="sd-muted">Der Vertragstext entsteht aus der Vorlage „{{ Vorlagen::KINDS[$kind] }}“ und den Daten im CRM. Hier ergänzt du nur, was nicht im CRM steht. Die Vorlage ist ein Muster ohne Gewähr; vor dem ersten Einsatz bitte rechtlich prüfen lassen.</p>

@if ($warnings)
  <div class="cf-warn"><strong>Bitte beachten:</strong><ul>@foreach ($warnings as $w)<li>{{ $w }}</li>@endforeach</ul></div>
@endif

<div class="sd-card">
  <h2>Aus dem CRM</h2>
  <dl class="cf-sum">
    <dt>Vermieter</dt><dd>{{ $values['vermieter_name'] }}{{ $values['vermieter_anschrift'] ? ', '.$values['vermieter_anschrift'] : '' }}</dd>
    <dt>Mieter</dt><dd>{{ $values['mieter_name'] }}{{ $values['mieter_anschrift'] ? ', '.$values['mieter_anschrift'] : '' }}</dd>
    <dt>Objekt</dt><dd>{{ $values['einheit'] }}, {{ $values['objekt_anschrift'] }}{{ $values['flaeche'] ? ', '.$values['flaeche'].' m²' : '' }}{{ $values['zimmer'] ? ', '.$values['zimmer'].' Zimmer' : '' }}</dd>
    <dt>Beginn</dt><dd>{{ $values['beginn'] }}{{ $values['ende'] ? ' bis '.$values['ende'] : ', unbefristet' }}</dd>
    <dt>Miete</dt><dd>{{ str_replace('- ', '', $values['miete_aufstellung']) }}</dd>
    @if ($values['kaution'])<dt>Kaution</dt><dd>{{ $values['kaution'] }}</dd>@endif
    <dt>Konto</dt><dd>{{ $values['vermieter_iban'] ?: 'fehlt' }}</dd>
  </dl>
  <p class="sd-muted" style="margin:.6rem 0 0">Stimmt etwas nicht? Dann bitte zuerst beim Mietvertrag, Mieter, Eigentümer oder der Einheit ändern.</p>
</div>

<form method="POST" action="{{ $contract ? route('contracts.update', $contract) : route('contracts.store', $lease) }}">
  @csrf
  @if ($contract) @method('PUT') @endif
  <div class="sd-card">
    <h2>Ergänzungen</h2>
    <div class="sd-grid">
      @foreach ($fields as $key => [$label, $type, $hint])
        @continue($type === 'textarea' || $type === 'check')
        <div class="sd-field">
          <label for="x-{{ $key }}">{{ $label }}</label>
          <input id="x-{{ $key }}" name="x[{{ $key }}]" type="text" value="{{ $val($key) }}" @if ($type === 'number') inputmode="numeric" @elseif ($type === 'money') inputmode="decimal" @endif>
          @if ($hint)<small class="sd-muted">{{ $hint }}</small>@endif
        </div>
      @endforeach
    </div>
    @foreach ($fields as $key => [$label, $type, $hint])
      @if ($type === 'textarea')
        <div class="sd-field">
          <label for="x-{{ $key }}">{{ $label }}</label>
          <textarea id="x-{{ $key }}" name="x[{{ $key }}]" rows="3">{{ $val($key) }}</textarea>
          @if ($hint)<small class="sd-muted">{{ $hint }}</small>@endif
        </div>
      @endif
    @endforeach
    @foreach ($fields as $key => [$label, $type, $hint])
      @if ($type === 'check')
        <label class="sd-check"><input type="hidden" name="x[{{ $key }}]" value="0"><input type="checkbox" name="x[{{ $key }}]" value="1" @checked((bool) $val($key))> {{ $label }}@if ($hint) <span class="sd-muted">({{ $hint }})</span>@endif</label>
      @endif
    @endforeach
  </div>
  <div class="sd-actions">
    <button class="sd-btn" type="submit">{{ $contract ? 'Vertrag neu erstellen' : 'Vertrag erstellen' }}</button>
    <a class="sd-btn sec" href="{{ $contract ? route('contracts.show', $contract) : route('leases.show', $lease) }}">Abbrechen</a>
  </div>
</form>
@endsection
