@extends('layouts.app')
@section('title', $c->exists ? 'Vertrag bearbeiten' : 'Vertrag anlegen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Models\ServiceContract;
  $box = 'padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;width:100%';
  $v = fn ($k) => old($k, $c->{$k} instanceof \Carbon\CarbonInterface ? $c->{$k}->format('Y-m-d') : $c->{$k});
@endphp
<div class="sd-head">
  <h1>{{ $c->exists ? 'Vertrag bearbeiten' : 'Vertrag anlegen' }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ $c->exists ? route('svc.show', $c) : route('svc.index') }}">Abbrechen</a></div>
</div>
<form method="POST" action="{{ $c->exists ? route('svc.update', $c) : route('svc.store') }}" class="sd-card">
  @csrf @if ($c->exists) @method('PUT') @endif
  <div class="sd-grid">
    <div class="sd-field"><label for="f-kind">Art *</label><select id="f-kind" name="kind" style="{{ $box }}">@foreach (ServiceContract::KINDS as $k => $label)<option value="{{ $k }}" @selected($v('kind') === $k)>{{ $label }}</option>@endforeach</select></div>
    @include('partials.sd-field', ['name' => 'title', 'label' => 'Bezeichnung', 'value' => $v('title'), 'required' => true, 'hint' => 'z. B. „Allgemeinstrom Haus A“'])
    @include('partials.sd-field', ['name' => 'provider', 'label' => 'Anbieter', 'value' => $v('provider')])
  </div>
  <div class="sd-grid">
    <div class="sd-field">
      <label for="f-property_id">Objekt</label>
      <select id="f-property_id" name="property_id" style="{{ $box }}"><option value="">– kein bestimmtes –</option>@foreach ($properties as $p)<option value="{{ $p->id }}" @selected((int) $v('property_id') === $p->id)>{{ $p->name }}</option>@endforeach</select>
    </div>
    <div class="sd-field">
      <label for="f-unit_id">Einheit (optional)</label>
      <select id="f-unit_id" name="unit_id" style="{{ $box }}"><option value="">–</option>@foreach ($properties as $p)@foreach ($p->units as $u)<option value="{{ $u->id }}" @selected((int) $v('unit_id') === $u->id)>{{ $p->name }}: {{ $u->label }}</option>@endforeach @endforeach</select>
    </div>
    <div class="sd-field">
      <label for="f-owner_id">Eigentümer (nur ohne Objekt)</label>
      <select id="f-owner_id" name="owner_id" style="{{ $box }}"><option value="">–</option>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected((int) $v('owner_id') === $o->id || $owners->count() === 1)>{{ $o->name }}</option>@endforeach</select>
    </div>
  </div>
  <div class="sd-grid">
    @include('partials.sd-field', ['name' => 'contract_no', 'label' => 'Vertragsnummer', 'value' => $v('contract_no')])
    @include('partials.sd-field', ['name' => 'customer_no', 'label' => 'Kundennummer', 'value' => $v('customer_no')])
    @include('partials.sd-field', ['name' => 'meter_no', 'label' => 'Zählernummer', 'value' => $v('meter_no')])
  </div>

  <h2 style="margin-top:1rem">Laufzeit und Kündigung</h2>
  <div class="sd-grid">
    <div class="sd-field"><label for="f-start_date">Vertragsbeginn</label><input id="f-start_date" type="date" name="start_date" value="{{ $v('start_date') }}"></div>
    <div class="sd-field"><label for="f-term_end">Laufzeitende</label><input id="f-term_end" type="date" name="term_end" value="{{ $v('term_end') }}"><small class="sd-muted">Der Tag, zu dem der Vertrag endet oder erstmals gekündigt werden kann. Leer = unbefristet, jederzeit kündbar.</small></div>
  </div>
  <div class="sd-grid">
    <div class="sd-field"><label for="f-notice_value">Kündigungsfrist *</label><div style="display:flex;gap:.4rem"><input id="f-notice_value" name="notice_value" type="number" min="0" max="60" required value="{{ $v('notice_value') }}" style="{{ $box }};width:6rem"><select name="notice_unit" aria-label="Einheit" style="{{ $box }}">@foreach (ServiceContract::UNITS as $k => $label)<option value="{{ $k }}" @selected($v('notice_unit') === $k)>{{ $label }}</option>@endforeach</select></div><small class="sd-muted">Vor dem Laufzeitende. Das CRM rechnet vorsichtig, einen Tag früher.</small></div>
    <div class="sd-field"><label for="f-remind_days">Erinnern vor dem Termin (Tage) *</label><input id="f-remind_days" name="remind_days" type="number" min="1" max="365" required value="{{ $v('remind_days') }}"><small class="sd-muted">Zusätzlich gibt es eine zweite Erinnerung sieben Tage vorher.</small></div>
  </div>
  <label style="display:block;margin:.6rem 0"><input type="checkbox" name="auto_renew" value="1" @checked($v('auto_renew'))> Der Vertrag verlängert sich automatisch, wenn nicht gekündigt wird</label>
  <div class="sd-field" style="max-width:16rem"><label for="f-renew_months">Verlängerung um (Monate)</label><input id="f-renew_months" name="renew_months" type="number" min="1" max="120" value="{{ $v('renew_months') }}"></div>

  <h2 style="margin-top:1rem">Kosten</h2>
  <div class="sd-grid">
    @include('partials.sd-field', ['name' => 'amount', 'label' => 'Betrag (€, brutto)', 'inputmode' => 'decimal', 'value' => $c->exists && $c->amount !== null ? number_format((float) old('amount', $c->amount), 2, ',', '') : old('amount')])
    <div class="sd-field"><label for="f-interval">Zahlungsweise</label><select id="f-interval" name="interval" style="{{ $box }}">@foreach (ServiceContract::INTERVALS as $k => [$label])<option value="{{ $k }}" @selected($v('interval') === $k)>{{ ucfirst($label) }}</option>@endforeach</select></div>
    <div class="sd-field"><label for="f-cost_type_id">Kostenart (für die Nebenkosten)</label><select id="f-cost_type_id" name="cost_type_id" style="{{ $box }}"><option value="">–</option>@foreach ($costTypes as $t)<option value="{{ $t->id }}" @selected((int) $v('cost_type_id') === $t->id)>{{ $t->label }}{{ $t->allocatable ? ' (umlagefähig)' : '' }}</option>@endforeach</select></div>
  </div>
  <div class="sd-field"><label for="f-contact">Ansprechpartner und Telefon</label><input id="f-contact" name="contact" maxlength="190" value="{{ $v('contact') }}"></div>
  <div class="sd-field"><label for="f-note">Notiz</label><textarea id="f-note" name="note" rows="2" maxlength="3000" style="{{ $box }}">{{ $v('note') }}</textarea></div>
  <button class="sd-btn" type="submit">Speichern</button>
</form>
@endsection
