@extends('layouts.app')
@section('title', 'E-Rechnung hochladen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.ei-list{list-style:none;margin:0;padding:0}
.ei-list li{border:1px solid #e3e6ea;border-radius:.5rem;padding:.6rem .8rem;margin-bottom:.5rem;background:#fff}
.ei-ok{border-left:4px solid #067647!important}
.ei-bad{border-left:4px solid #b42318!important}
.ei-warn{color:#7a4b00;margin:.3rem 0 0}
.ei-name{font-weight:600;word-break:break-all}
.ei-files{margin:.3rem 0 0;padding-left:1.1rem;font-size:.9rem}
</style>

@php
  $ownerValue = (int) old('owner_id', request('eigentuemer'));
  $propertyValue = (int) old('property_id');
  $ownerLevel = (bool) old('owner_level', false);
@endphp

<div class="sd-head">
  <h1>E-Rechnung hochladen</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('receipts.index') }}">Alle Belege</a>
  </div>
</div>

@if (count($results) > 0)
  <div class="sd-card">
    <h2>Ergebnis</h2>
    <ul class="ei-list">
      @foreach ($results as $r)
        <li class="{{ $r['ok'] ? 'ei-ok' : 'ei-bad' }}">
          <span class="ei-name">{{ $r['name'] }}</span><br>
          @if ($r['ok'])
            Gespeichert als <a href="{{ route('receipts.show', $r['receipt_id']) }}">{{ $r['title'] }}</a>.
            @if ($r['incomplete'] !== [])
              <span class="sd-muted">Noch zu ergänzen: {{ implode(', ', $r['incomplete']) }}.</span>
            @endif
            @foreach ($r['warnings'] as $w)
              <div class="ei-warn">Bitte prüfen: {{ $w }}</div>
            @endforeach
          @else
            <span style="color:#b42318">Nicht gespeichert.</span> {{ $r['message'] }}
          @endif
        </li>
      @endforeach
    </ul>
    <p class="sd-muted" style="margin-bottom:0">Fehlende Angaben, zum Beispiel Objekt und Kostenart, trägst du am Beleg nach. Unvollständige Belege findest du in der Belegliste unter „Unvollständig“.</p>
  </div>
@endif

@if ($owners->isEmpty())
  <div class="sd-card"><p style="margin:0">Dir ist noch kein Eigentümer freigegeben. Der Administrator stellt das beim jeweiligen Eigentümer unter „Freigaben“ ein.</p></div>
@else
<form id="ei-form" method="POST" action="{{ route('einvoice.store') }}" enctype="multipart/form-data">
  @csrf

  <div class="sd-card">
    <h2>Rechnungsdateien</h2>
    <div class="sd-field">
      <label for="ei-files">XRechnung (XML) oder ZUGFeRD / Factur-X (PDF)</label>
      <input type="file" id="ei-files" name="files[]" multiple required accept=".xml,.pdf,application/xml,text/xml,application/pdf">
      <small class="sd-muted">Bis zu {{ $maxFiles }} Dateien auf einmal, höchstens 10 MB je Datei. Aus jeder Datei wird ein eigener Beleg. Datum, Betrag, Rechnungsnummer und Lieferant werden aus der Rechnung gelesen. Die Datei bleibt unverändert und verschlüsselt gespeichert.</small>
      <small class="sd-muted" id="ei-count" aria-live="polite"></small>
    </div>
    <p class="sd-muted" style="margin:.4rem 0 0">Eine normale PDF-Rechnung ohne eingebettete Daten oder ein Foto kommt weiterhin unter „Beleg erfassen“.</p>
  </div>

  <div class="sd-card">
    <h2>Zuordnung</h2>
    <p class="sd-muted" style="margin-top:0">Die Zuordnung gilt für alle Dateien dieses Uploads. Bei gemischten Rechnungen lädst du sie in getrennten Durchgängen hoch.</p>
    <div class="sd-grid">
      <div class="sd-field">
        <label for="ei-owner">Eigentümer *</label>
        <select id="ei-owner" name="owner_id" required>
          @if ($owners->count() !== 1)<option value="">– bitte wählen –</option>@endif
          @foreach ($owners as $owner)
            <option value="{{ $owner->id }}" @selected($ownerValue === (int) $owner->id || $owners->count() === 1)>{{ $owner->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="ei-property">Objekt (kann später ergänzt werden)</label>
        <select id="ei-property" name="property_id">
          <option value="">– noch nicht festlegen –</option>
          @foreach ($properties as $property)
            <option value="{{ $property->id }}" data-owner="{{ $property->owner_id }}" @selected($propertyValue === (int) $property->id)>{{ $property->name }}</option>
          @endforeach
        </select>
      </div>
    </div>
    <input type="hidden" name="owner_level" value="0">
    <label class="sd-check" for="ei-level" style="font-weight:400">
      <input type="checkbox" id="ei-level" name="owner_level" value="1" @checked($ownerLevel)>
      Gilt für den ganzen Eigentümer, nicht für ein bestimmtes Objekt (z. B. Steuerberater)
    </label>
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit" id="ei-submit">Rechnungen einlesen</button>
    <a class="sd-btn sec" href="{{ route('receipts.index') }}">Abbrechen</a>
  </div>
</form>
@endif

<script>
(function () {
  var form = document.getElementById('ei-form');
  if (!form) { return; }
  var owner = document.getElementById('ei-owner');
  var prop = document.getElementById('ei-property');
  var level = document.getElementById('ei-level');
  var files = document.getElementById('ei-files');
  var count = document.getElementById('ei-count');

  function refresh() {
    var id = owner.value || '-1';
    Array.prototype.forEach.call(prop.options, function (o) {
      if (!o.value) { o.hidden = false; o.disabled = false; return; }
      var show = o.getAttribute('data-owner') === String(id);
      o.hidden = !show;
      o.disabled = !show;
      if (!show && o.selected) { prop.value = ''; }
    });
    prop.disabled = level.checked;
  }
  owner.addEventListener('change', refresh);
  level.addEventListener('change', refresh);
  refresh();

  files.addEventListener('change', function () {
    var n = files.files.length;
    count.textContent = n === 0 ? '' : ' ' + n + (n === 1 ? ' Datei gewählt.' : ' Dateien gewählt.');
  });

  var sending = false;
  form.addEventListener('submit', function (ev) {
    if (sending) { ev.preventDefault(); return; }
    sending = true;
    window.setTimeout(function () {
      var b = document.getElementById('ei-submit');
      b.disabled = true;
      b.textContent = 'Wird eingelesen …';
    }, 0);
  });
})();
</script>
@endsection
