@extends('layouts.app')
@section('title', $expense->exists ? 'Wiederkehrende Ausgabe bearbeiten' : 'Wiederkehrende Ausgabe anlegen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  $isNew = ! $expense->exists;
  $ownerValue = (int) old('owner_id', $expense->owner_id);
  $propertyValue = (int) old('property_id', $expense->property_id);
  $unitValue = (int) old('unit_id', $expense->unit_id);
  $ownerLevel = (bool) old('owner_level', $expense->owner_level);
  $frequency = old('frequency', $expense->frequency ?: 'yearly');
  $month = (int) old('due_month', $expense->due_month ?: 1);
  $day = (int) old('due_day', $expense->due_day ?: 1);
  $start = old('start_date', $expense->start_date?->format('Y-m-d') ?? date('Y-m-d'));
  $end = old('end_date', $expense->end_date?->format('Y-m-d') ?? '');
  $amountValue = old('amount_gross', \App\Support\Money::input($expense->amount_gross));
  $showProperties = $isNew ? $properties : $properties->where('owner_id', $expense->owner_id);
@endphp
<div class="sd-head">
  <h1>{{ $isNew ? 'Wiederkehrende Ausgabe anlegen' : 'Wiederkehrende Ausgabe bearbeiten' }}</h1>
</div>

@if ($isNew && $owners->isEmpty())
  <div class="sd-card"><p style="margin:0">Dir ist noch kein Eigentümer freigegeben. Der Administrator stellt das beim jeweiligen Eigentümer unter „Freigaben“ ein.</p></div>
@else
<form id="rx-form" method="POST" action="{{ $isNew ? route('recurring.store') : route('recurring.update', $expense) }}" @if (! $isNew) data-owner="{{ $expense->owner_id }}" @endif>
  @csrf
  @if (! $isNew) @method('PUT') @endif

  <div class="sd-card">
    <h2>Wofür</h2>
    <div class="sd-grid">
      @if ($isNew)
        <div class="sd-field">
          <label for="f-owner_id">Eigentümer *</label>
          <select id="f-owner_id" name="owner_id" required>
            @if ($owners->count() !== 1)<option value="">– bitte wählen –</option>@endif
            @foreach ($owners as $owner)
              <option value="{{ $owner->id }}" @selected($ownerValue === (int) $owner->id)>{{ $owner->name }}</option>
            @endforeach
          </select>
        </div>
      @else
        <div class="sd-field">
          <label>Eigentümer</label>
          <div>{{ $expense->owner->name }}</div>
          <small class="sd-muted">Der Eigentümer kann nicht geändert werden.</small>
        </div>
      @endif
      <div class="sd-field">
        <label for="f-property_id">Objekt</label>
        <select id="f-property_id" name="property_id">
          <option value="">– bitte wählen –</option>
          @foreach ($showProperties as $property)
            <option value="{{ $property->id }}" data-owner="{{ $property->owner_id }}" @selected($propertyValue === (int) $property->id)>{{ $property->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="f-unit_id">Einheit (falls nur eine Einheit betroffen ist)</label>
        <select id="f-unit_id" name="unit_id">
          <option value="">– keine bestimmte Einheit –</option>
          @foreach ($showProperties as $property)
            @foreach ($property->units as $unit)
              <option value="{{ $unit->id }}" data-property="{{ $property->id }}" @selected($unitValue === (int) $unit->id)>{{ $property->name }}: {{ $unit->label }}</option>
            @endforeach
          @endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="f-cost_category">Kostenart *</label>
        <select id="f-cost_category" name="cost_category" required>
          <option value="">– bitte wählen –</option>
          @foreach (\App\Support\Kostenarten::groups($expense->cost_category) as $group => $items)
            <optgroup label="{{ $group }}">
              @foreach ($items as $key => $label)
                <option value="{{ $key }}" @selected((string) old('cost_category', $expense->cost_category) === (string) $key)>{{ $label }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="f-vendor_name">Lieferant oder Rechnungsteller</label>
        <input id="f-vendor_name" name="vendor_name" type="text" list="rx-vendors" autocomplete="off" maxlength="190" value="{{ old('vendor_name', $expense->vendor_name) }}">
        <datalist id="rx-vendors">
          @foreach ($vendors as $vendor)
            <option value="{{ $vendor->name }}"></option>
          @endforeach
        </datalist>
      </div>
    </div>
    <input type="hidden" name="owner_level" value="0">
    <label class="sd-check" for="f-owner_level" style="font-weight:400">
      <input type="checkbox" id="f-owner_level" name="owner_level" value="1" @checked($ownerLevel)>
      Gilt für den ganzen Eigentümer, nicht für ein bestimmtes Objekt (z. B. Steuerberater)
    </label>
  </div>

  <div class="sd-card">
    <h2>Wann</h2>
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-frequency">Rhythmus *</label>
        <select id="f-frequency" name="frequency" required>
          @foreach (\App\Support\Receipts\RecurrenceSchedule::FREQUENCIES as $key => $label)
            <option value="{{ $key }}" @selected($frequency === $key)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="sd-field" id="rx-month-field">
        <label for="f-due_month" id="rx-month-label">Monat *</label>
        <select id="f-due_month" name="due_month">
          @foreach (\App\Support\Receipts\RecurrenceSchedule::MONTHS as $num => $name)
            <option value="{{ $num }}" @selected($month === $num)>{{ $name }}</option>
          @endforeach
        </select>
        <small class="sd-muted" id="rx-month-hint"></small>
      </div>
      <div class="sd-field">
        <label for="f-due_day">Tag im Monat *</label>
        <input id="f-due_day" name="due_day" type="number" min="1" max="31" required value="{{ $day }}">
        <small class="sd-muted">Gibt es den Tag im Monat nicht (z. B. der 31. im Februar), gilt der letzte Tag.</small>
      </div>
      <div class="sd-field">
        <label for="f-start_date">Beginn *</label>
        <input id="f-start_date" name="start_date" type="date" required value="{{ $start }}">
        <small class="sd-muted">Davor werden keine Termine angezeigt.</small>
      </div>
      <div class="sd-field">
        <label for="f-end_date">Ende (falls bekannt)</label>
        <input id="f-end_date" name="end_date" type="date" value="{{ $end }}">
      </div>
      <div class="sd-field">
        <label for="f-amount_gross">Erwarteter Betrag in Euro</label>
        <input id="f-amount_gross" name="amount_gross" type="text" inputmode="decimal" autocomplete="off" value="{{ $amountValue }}" placeholder="z. B. 123,45">
        <small class="sd-muted">Nur zur Orientierung, wird beim Erfassen vorgeschlagen.</small>
      </div>
    </div>
    @if (! $isNew)
      <label class="sd-check" for="f-active" style="font-weight:400">
        <input type="checkbox" id="f-active" name="active" value="1" @checked((bool) old('active', $expense->active))>
        Aktiv (es werden weiter Termine angezeigt). Zum Stilllegen, z. B. bei Vertragsende, abhaken.
      </label>
    @endif
    @include('partials.sd-textarea', ['name' => 'note', 'label' => 'Notiz', 'value' => $expense->note])
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ route('recurring.index') }}">Abbrechen</a>
  </div>
</form>
@endif

<script>
(function () {
  var form = document.getElementById('rx-form');
  if (!form) { return; }

  var ownerSel = document.getElementById('f-owner_id');
  var propSel = document.getElementById('f-property_id');
  var unitSel = document.getElementById('f-unit_id');
  var levelBox = document.getElementById('f-owner_level');
  var freq = document.getElementById('f-frequency');
  var monthField = document.getElementById('rx-month-field');
  var monthLabel = document.getElementById('rx-month-label');
  var monthHint = document.getElementById('rx-month-hint');

  function filterOptions(sel, attr, value) {
    Array.prototype.forEach.call(sel.options, function (o) {
      if (!o.value) { o.hidden = false; o.disabled = false; return; }
      var show = o.getAttribute(attr) === String(value);
      o.hidden = !show;
      o.disabled = !show;
      if (!show && o.selected) { sel.value = ''; }
    });
  }

  function refresh() {
    var ownerId = ownerSel ? ownerSel.value : form.getAttribute('data-owner');
    filterOptions(propSel, 'data-owner', ownerId || '-1');
    filterOptions(unitSel, 'data-property', propSel.value || '-1');
    propSel.disabled = levelBox.checked;
    unitSel.disabled = levelBox.checked;

    var f = freq.value;
    monthField.style.display = f === 'monthly' ? 'none' : '';
    monthLabel.textContent = f === 'yearly' ? 'Monat *' : 'Erster Monat *';
    monthHint.textContent = f === 'quarterly' ? 'Fällig in diesem und jedem dritten Monat danach.'
      : (f === 'half_yearly' ? 'Fällig in diesem und dem Monat sechs Monate später.' : '');
  }

  if (ownerSel) { ownerSel.addEventListener('change', refresh); }
  propSel.addEventListener('change', refresh);
  levelBox.addEventListener('change', refresh);
  freq.addEventListener('change', refresh);
  refresh();
})();
</script>
@endsection
