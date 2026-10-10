@extends('layouts.app')
@section('title', $loan->exists ? 'Darlehen bearbeiten' : 'Darlehen anlegen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('finance.styles')
@php
  $val = fn ($k, $fmt = null) => old($k, $fmt ? $fmt($loan->{$k}) : $loan->{$k});
  $money = fn ($v) => $v === null ? '' : \App\Support\Money::input($v);
  $pct = fn ($v) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, 3, ',', ''), '0'), ',');
  $date = fn ($v) => $v?->format('Y-m-d');
@endphp
<div class="sd-head">
  <h1>{{ $loan->exists ? 'Darlehen bearbeiten' : 'Darlehen anlegen' }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ $loan->exists ? route('loans.show', $loan) : route('finance.index') }}">Abbrechen</a></div>
</div>
<form method="POST" action="{{ $loan->exists ? route('loans.update', $loan) : route('loans.store') }}">
  @csrf
  @if ($loan->exists) @method('PUT') @endif
  <div class="sd-card">
    <h2>Grunddaten</h2>
    <div class="fi-form">
      <div><label for="l-name">Bezeichnung *</label><input id="l-name" name="name" required maxlength="120" value="{{ $val('name') }}" placeholder="z. B. Sparkasse Hauptstraße"></div>
      <div><label for="l-owner">Eigentümer (Darlehensnehmer) *</label>
        <select id="l-owner" name="owner_id" required>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected((int) $val('owner_id') === (int) $o->id)>{{ $o->name }}</option>@endforeach</select></div>
      <div><label for="l-prop">Objekt</label>
        <select id="l-prop" name="property_id"><option value="">– kein bestimmtes Objekt (gilt für den Eigentümer) –</option>@foreach ($properties as $p)<option value="{{ $p->id }}" data-owner="{{ $p->owner_id }}" @selected((int) $val('property_id') === (int) $p->id)>{{ $p->name }}</option>@endforeach</select>
        <small>Die Zinsen erscheinen in der Anlage V bei diesem Objekt.</small></div>
      <div><label for="l-kind">Art *</label>
        <select id="l-kind" name="kind" required>@foreach (\App\Models\Loan::KINDS as $k => $l)<option value="{{ $k }}" @selected($val('kind') === $k)>{{ $l }}</option>@endforeach</select></div>
      <div><label for="l-lender">Bank / Darlehensgeber</label><input id="l-lender" name="lender" maxlength="120" value="{{ $val('lender') }}"></div>
      <div><label for="l-acc">Darlehensnummer</label><input id="l-acc" name="account_no" maxlength="60" value="{{ $val('account_no') }}"><small>Hilft beim Erkennen der Raten im Kontoauszug.</small></div>
      <div><label for="l-iban">IBAN der Bank (Abbuchung)</label><input id="l-iban" name="lender_iban" maxlength="40" value="{{ old('lender_iban', $loan->lender_iban) }}" autocomplete="off"><small>Optional, steht im Kontoauszug bei der Abbuchung.</small></div>
    </div>
  </div>

  <div class="sd-card">
    <h2>Konditionen</h2>
    <div class="fi-form">
      <div><label for="l-principal">Darlehensbetrag *</label><input id="l-principal" name="principal" required inputmode="decimal" value="{{ $val('principal', $money) }}" placeholder="200.000,00"></div>
      <div><label for="l-paid">Ausgezahlt am</label><input id="l-paid" type="date" name="paid_out_on" value="{{ $val('paid_out_on', $date) }}"></div>
      <div><label for="l-rate">Sollzins in % pro Jahr *</label><input id="l-rate" name="interest_rate" required inputmode="decimal" value="{{ $val('interest_rate', $pct) }}" placeholder="3,25"></div>
      <div><label for="l-int">Zahlweise *</label><select id="l-int" name="interval_months">@foreach (\App\Models\Loan::INTERVALS as $m => $l)<option value="{{ $m }}" @selected((int) $val('interval_months') === (int) $m)>{{ $l }}</option>@endforeach</select></div>
      <div data-show="annuitaet tilgung privat"><label for="l-inst"><span data-label-annuitaet data-label-privat>Rate je Zahlung (Zins + Tilgung)</span><span data-label-tilgung class="fi-hide">Tilgungsbetrag je Zahlung</span></label><input id="l-inst" name="installment" inputmode="decimal" value="{{ $val('installment', $money) }}"><small data-show="annuitaet">Oder leer lassen und daneben die anfängliche Tilgung angeben.</small></div>
      <div data-show="annuitaet"><label for="l-rep">Anfängliche Tilgung in %</label><input id="l-rep" name="initial_repayment" inputmode="decimal" value="{{ $val('initial_repayment', $pct) }}" placeholder="2"></div>
      <div><label for="l-first">Erste Rate am *</label><input id="l-first" type="date" name="first_payment_on" required value="{{ $val('first_payment_on', $date) }}"></div>
      <div><label for="l-fixed">Zinsbindung bis</label><input id="l-fixed" type="date" name="fixed_until" value="{{ $val('fixed_until', $date) }}"></div>
      <div data-show="annuitaet tilgung endfaellig privat"><label for="l-free">Tilgungsfrei bis (z. B. KfW)</label><input id="l-free" type="date" name="free_until" value="{{ $val('free_until', $date) }}"><small>Bis dahin werden nur Zinsen gezahlt.</small></div>
      <div data-show="endfaellig privat"><label for="l-end">Rückzahlung am (endfällig)</label><input id="l-end" type="date" name="end_date" value="{{ $val('end_date', $date) }}"></div>
      <div><label for="l-sp">Sondertilgung erlaubt (% pro Jahr)</label><input id="l-sp" name="special_repayment_pct" inputmode="decimal" value="{{ $val('special_repayment_pct', $pct) }}" placeholder="5"></div>
      @if ($loan->exists)
        <div><label for="l-status">Status</label><select id="l-status" name="status"><option value="aktiv" @selected($loan->status === 'aktiv')>läuft</option><option value="getilgt" @selected($loan->status === 'getilgt')>vollständig getilgt / abgelöst</option></select></div>
      @endif
      <div style="grid-column:1/-1"><label for="l-note">Notiz</label><textarea id="l-note" name="note" rows="2" maxlength="2000">{{ $val('note') }}</textarea></div>
    </div>
  </div>
  <div class="sd-actions"><button class="sd-btn" type="submit">Speichern</button></div>
</form>
@if ($loan->exists)
  <form method="POST" action="{{ route('loans.destroy', $loan) }}" style="margin-top:1.2rem" onsubmit="return confirm('Darlehen mit allen Einträgen löschen? Zugeordnete Abbuchungen werden wieder offen.')">
    @csrf @method('DELETE')
    <button class="sd-btn danger" type="submit">Darlehen löschen</button>
  </form>
@endif
<script>
(function () {
  var kind = document.getElementById('l-kind'), owner = document.getElementById('l-owner'), prop = document.getElementById('l-prop');
  function upd() {
    var k = kind.value;
    document.querySelectorAll('[data-show]').forEach(function (el) { el.classList.toggle('fi-hide', el.getAttribute('data-show').split(' ').indexOf(k) < 0); });
    document.querySelectorAll('[data-label-tilgung]').forEach(function (el) { el.classList.toggle('fi-hide', k !== 'tilgung'); });
    document.querySelectorAll('[data-label-annuitaet]').forEach(function (el) { el.classList.toggle('fi-hide', k === 'tilgung'); });
  }
  function props() {
    Array.prototype.forEach.call(prop.options, function (o) { if (o.value) { o.hidden = o.getAttribute('data-owner') !== owner.value; } });
    if (prop.selectedOptions[0] && prop.selectedOptions[0].hidden) prop.value = '';
  }
  kind.addEventListener('change', upd); owner.addEventListener('change', props); upd(); props();
})();
</script>
@endsection
