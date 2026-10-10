@extends('layouts.app')
@section('title', $lease->exists ? 'Mietvertrag bearbeiten' : 'Mietvertrag anlegen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Rent\Mieten;
  $isNew = ! $lease->exists;
  $dueMode = old('due_mode', $lease->payment_term_days !== null ? 'tage' : 'tag');
  $vatValue = (string) old('vat_rate', (string) (int) round((float) $lease->vat_rate));
@endphp
<div class="sd-head">
  <h1>{{ $isNew ? 'Mietvertrag anlegen' : 'Mietvertrag bearbeiten' }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ $isNew ? route('leases.index') : route('leases.show', $lease) }}">Abbrechen</a>
  </div>
</div>

<form method="POST" action="{{ $isNew ? route('leases.store') : route('leases.update', $lease) }}" id="lease-form">
  @csrf
  @unless ($isNew) @method('PUT') @endunless

  <div class="sd-card">
    <h2>Einheit und Mieter</h2>
    @if ($isNew)
      <div class="sd-grid">
        <div class="sd-field">
          <label for="f-unit_id">Einheit *</label>
          <select id="f-unit_id" name="unit_id" required>
            <option value="">– bitte wählen –</option>
            @foreach ($properties as $p)
              <optgroup label="{{ $p->name }} ({{ $p->owner?->name }})">
                @foreach ($p->units as $u)
                  <option value="{{ $u->id }}" @selected((string) old('unit_id', $lease->unit_id) === (string) $u->id)>{{ $u->label }} – {{ $u->usageLabel() }}</option>
                @endforeach
              </optgroup>
            @endforeach
          </select>
        </div>
        <div class="sd-field">
          <label for="f-tenant_id">Mieter *</label>
          <select id="f-tenant_id" name="tenant_id" required>
            <option value="">– bitte wählen –</option>
            @foreach ($tenants as $t)
              <option value="{{ $t->id }}" data-owner="{{ $t->owner_id }}" @selected((string) old('tenant_id', $lease->tenant_id) === (string) $t->id)>{{ $t->display_name }} ({{ $t->owner?->name }})</option>
            @endforeach
          </select>
          <small class="sd-muted">Es erscheinen nur Mieter des Eigentümers der Einheit. Fehlt der Mieter? <a href="{{ route('tenants.create') }}" target="_blank" rel="noopener">Neuen Mieter anlegen</a> (öffnet sich in einem neuen Fenster, danach diese Seite neu laden).</small>
        </div>
      </div>
    @else
      <dl class="sd-dl">
        <dt>Einheit</dt><dd>{{ $lease->unit?->label }}, {{ $lease->unit?->property?->name }}</dd>
        <dt>Mieter</dt><dd>{{ $lease->tenant?->display_name }}</dd>
      </dl>
      <p class="sd-muted" style="margin-bottom:0">Einheit und Mieter lassen sich nicht ändern. Bei einem Mieterwechsel trägst du hier ein Vertragsende ein und legst einen neuen Vertrag an.</p>
    @endif
  </div>

  <div class="sd-card">
    <h2>Vertrag</h2>
    <div class="sd-grid">
      @include('partials.sd-select', ['name' => 'lease_type', 'label' => 'Vertragsart', 'required' => true, 'noBlank' => true, 'options' => Mieten::LEASE_TYPES, 'value' => $lease->lease_type])
      <div class="sd-field">
        <label for="f-start_date">Beginn *</label>
        <input id="f-start_date" name="start_date" type="date" required value="{{ old('start_date', $lease->start_date?->format('Y-m-d')) }}">
      </div>
      <div class="sd-field">
        <label for="f-end_date">Ende</label>
        <input id="f-end_date" name="end_date" type="date" value="{{ old('end_date', $lease->end_date?->format('Y-m-d')) }}">
        <small class="sd-muted">Leer lassen bei unbefristeten Verträgen. Bei Montage-Wohnungen das Abreisedatum.</small>
      </div>
      <div class="sd-field">
        <label for="f-vat_rate">Umsatzsteuer *</label>
        <select id="f-vat_rate" name="vat_rate" required>
          @foreach (Mieten::VAT_RATES as $k => $label)
            <option value="{{ $k }}" @selected($vatValue === (string) $k)>{{ $label }}</option>
          @endforeach
        </select>
        <small class="sd-muted">Wird aus der Nutzungsart der Einheit vorgeschlagen. Bitte mit dem Steuerberater abstimmen.</small>
      </div>
    </div>

    <div class="sd-field" style="margin-bottom:.9rem">
      <label>Fälligkeit der Miete *</label>
      <label class="sd-check" style="font-weight:400"><input type="radio" name="due_mode" value="tag" @checked($dueMode === 'tag')> monatlich zum
        <input type="number" name="due_day" min="1" max="28" style="width:4.5rem;padding:.35rem" value="{{ old('due_day', $lease->due_day ?? 3) }}"> . des Monats (üblich: 3.)</label>
      <label class="sd-check" style="font-weight:400"><input type="radio" name="due_mode" value="tage" @checked($dueMode === 'tage')> im Voraus mit Zahlungsziel
        <input type="number" name="payment_term_days" min="0" max="60" style="width:4.5rem;padding:.35rem" value="{{ old('payment_term_days', $lease->payment_term_days ?? 7) }}"> Tage ab Beginn des Monats bzw. Einzug (Montage-Wohnungen)</label>
    </div>
  </div>

  @if ($isNew)
    <div class="sd-card">
      <h2>Miete bei Vertragsbeginn (monatlich)</h2>
      <p class="sd-muted" style="margin-top:0" id="net-hint">Beträge ohne Umsatzsteuer eintragen. Leere Felder zählen als 0. Beginnt oder endet der Vertrag mitten im Monat, rechnet das System den Monat anteilig nach Tagen.</p>
      <div class="sd-grid">
        @foreach (Mieten::COMPONENTS as $col => $label)
          @include('partials.sd-field', ['name' => $col, 'label' => $label, 'inputmode' => 'decimal', 'value' => $rent ? Money::input($rent->{$col}) : '', 'required' => $col === 'rent_cold'])
        @endforeach
      </div>
      <p style="margin:0"><strong>Summe: <span id="rent-sum">0,00 €</span></strong> <span class="sd-muted" id="rent-vat"></span></p>
    </div>
  @endif

  <div class="sd-card">
    <h2>Mietanpassung</h2>
    <div class="sd-grid">
      @include('partials.sd-select', ['name' => 'adjustment_type', 'label' => 'Art', 'required' => true, 'noBlank' => true, 'options' => Mieten::ADJUSTMENT, 'value' => $lease->adjustment_type, 'hint' => 'Staffel- und Indexmiete schließen sich gegenseitig aus.'])
    </div>
    <div id="index-fields" class="sd-grid">
      @include('partials.sd-field', ['name' => 'index_base_value', 'label' => 'Indexwert bei Vertragsbeginn bzw. letzter Anpassung', 'inputmode' => 'decimal', 'value' => $lease->index_base_value !== null ? number_format((float) $lease->index_base_value, 1, ',', '') : '', 'hint' => 'Verbraucherpreisindex für Deutschland (2020 = 100), z. B. 121,9'])
      <div class="sd-field">
        <label for="f-index_base_month">Monat dieses Indexwerts</label>
        <input id="f-index_base_month" name="index_base_month" type="month" value="{{ old('index_base_month', $lease->index_base_month?->format('Y-m')) }}">
      </div>
    </div>
    <p class="sd-muted" style="margin-bottom:0">Staffeln und Erhöhungen trägst du nach dem Speichern im Vertrag unter „Mietänderung erfassen“ ein, auch für die Zukunft.</p>
  </div>

  <div class="sd-card">
    <h2>Kaution und Notiz</h2>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'deposit_amount', 'label' => 'Kaution', 'inputmode' => 'decimal', 'value' => Money::input($lease->deposit_amount)])
      <div class="sd-field">
        <label for="f-deposit_received_on">Kaution erhalten am</label>
        <input id="f-deposit_received_on" name="deposit_received_on" type="date" value="{{ old('deposit_received_on', $lease->deposit_received_on?->format('Y-m-d')) }}">
      </div>
    </div>
    @include('partials.sd-textarea', ['name' => 'note', 'label' => 'Notiz', 'value' => $lease->note])
  </div>

  <button class="sd-btn" type="submit">{{ $isNew ? 'Mietvertrag speichern' : 'Änderungen speichern' }}</button>
</form>

<script>
(function () {
  var suggest = @json($suggest);
  var unit = document.getElementById('f-unit_id');
  var tenant = document.getElementById('f-tenant_id');
  var type = document.getElementById('f-lease_type');
  var vat = document.getElementById('f-vat_rate');
  var adj = document.getElementById('f-adjustment_type');
  var idx = document.getElementById('index-fields');

  function toggleIndex() { idx.style.display = adj.value === 'index' ? '' : 'none'; }
  adj.addEventListener('change', toggleIndex);
  toggleIndex();

  function filterTenants() {
    if (!unit || !tenant) return;
    var s = suggest[unit.value];
    var owner = s ? String(s.owner) : null;
    Array.prototype.forEach.call(tenant.options, function (o) {
      if (!o.value) return;
      var ok = !owner || o.getAttribute('data-owner') === owner;
      o.hidden = !ok; o.disabled = !ok;
      if (!ok && o.selected) tenant.value = '';
    });
  }
  if (unit) {
    unit.addEventListener('change', function () {
      var s = suggest[unit.value];
      if (s) {
        type.value = s.type;
        vat.value = s.vat;
        var mode = document.querySelector('input[name=due_mode][value=' + (s.type === 'montage' ? 'tage' : 'tag') + ']');
        if (mode) mode.checked = true;
      }
      filterTenants();
      sum();
    });
    filterTenants();
  }

  function parse(v) {
    v = (v || '').replace(/\s|€/g, '');
    if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.');
    var n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  }
  function fmt(n) { return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.') + ' €'; }
  var cols = @json(array_keys(Mieten::COMPONENTS));
  function sum() {
    var out = document.getElementById('rent-sum');
    if (!out) return;
    var s = 0;
    cols.forEach(function (c) { var el = document.getElementById('f-' + c); if (el) s += parse(el.value); });
    out.textContent = fmt(s);
    var rate = parseFloat(vat.value || '0');
    document.getElementById('rent-vat').textContent = rate > 0 ? '(netto) + ' + rate + ' % USt ' + fmt(Math.round(s * rate) / 100) + ' = ' + fmt(s + Math.round(s * rate) / 100) + ' brutto' : '';
  }
  cols.forEach(function (c) { var el = document.getElementById('f-' + c); if (el) el.addEventListener('input', sum); });
  vat.addEventListener('change', sum);
  sum();
})();
</script>
@endsection
