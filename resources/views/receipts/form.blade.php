@extends('layouts.app')
@section('title', $receipt->exists ? 'Beleg bearbeiten' : 'Beleg erfassen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.rc-warn{background:#fffaf0;border:1px solid #f0d9a8;color:#7a4b00;padding:.7rem 1rem;border-radius:.5rem;margin-bottom:1rem}
.rc-take{display:flex;flex-wrap:wrap;gap:.6rem;margin:.2rem 0 .8rem}
.rc-take .sd-btn{font-size:1.05rem;padding:.8rem 1.2rem}
.rc-list{display:flex;flex-wrap:wrap;gap:.7rem;margin:.4rem 0}
.rc-item{width:8.5rem;border:1px solid #e3e6ea;border-radius:.5rem;padding:.4rem;background:#fff;text-align:center;font-size:.85rem}
.rc-item img{display:block;width:100%;height:7rem;object-fit:cover;border-radius:.3rem;background:#f4f6f8}
.rc-item .rc-pdf{display:flex;align-items:center;justify-content:center;height:7rem;background:#f4f6f8;border-radius:.3rem;font-weight:700;color:#4b5563}
.rc-item button{margin-top:.35rem}
.rc-name{display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.rc-hide{display:none}
.rc-tools{margin:.5rem 0}
.rc-check{display:flex;gap:.5rem;align-items:center;margin:.2rem 0}
.rc-ocr-status{font-size:.9rem;color:#374151;margin-top:.3rem}
.rc-ocr{background:#ecfdf5 !important;border-color:#34d399 !important}
</style>

@php
  $isNew = ! $receipt->exists;
  $ownerValue = (int) old('owner_id', $receipt->owner_id);
  $propertyValue = (int) old('property_id', $receipt->property_id);
  $unitValue = (int) old('unit_id', $receipt->unit_id);
  $ownerLevel = (bool) old('owner_level', $receipt->owner_level);
  $dateValue = old('receipt_date', $receipt->receipt_date?->format('Y-m-d') ?? '');
  $amountValue = old('amount_gross', \App\Support\Money::input($receipt->amount_gross));
  $showProperties = $isNew ? $properties : $properties->where('owner_id', $receipt->owner_id);
@endphp

<div class="sd-head">
  <h1>{{ $isNew ? 'Beleg erfassen' : 'Beleg bearbeiten' }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ $isNew ? route('receipts.index') : route('receipts.show', $receipt) }}">Abbrechen</a>
  </div>
</div>

@if (session('warning'))
  <div class="rc-warn">{{ session('warning') }}</div>
@endif

@if ($isNew && $owners->isEmpty())
  <div class="sd-card"><p style="margin:0">Dir ist noch kein Eigentümer freigegeben. Der Administrator stellt das beim jeweiligen Eigentümer unter „Freigaben“ ein.</p></div>
@else
@if (! empty($bankTx))
  <div class="sd-ok">Beleg zur Abbuchung vom {{ $bankTx->booking_date->format('d.m.Y') }} über {{ \App\Support\Money::format($bankTx->amount) }} ({{ $bankTx->counterparty_name }}). Datum, Betrag und Lieferant sind vorbelegt. Nach dem Speichern wird der Beleg mit der Abbuchung verknüpft.</div>
@endif
<form id="rc-form" method="POST" enctype="multipart/form-data"
      action="{{ $isNew ? route('receipts.store') : route('receipts.update', $receipt) }}"
      @if (! $isNew) data-owner="{{ $receipt->owner_id }}" @endif>
  @csrf
  @if (! $isNew) @method('PUT') @endif

  @if ($isNew)
  <div class="sd-card">
    <h2>Foto oder PDF</h2>
    <div id="rc-capture">
      <div class="rc-take rc-hide" id="rc-buttons">
        <button type="button" class="sd-btn" id="rc-take">Foto aufnehmen</button>
        <button type="button" class="sd-btn sec" id="rc-pick">Datei auswählen</button>
      </div>
      <input type="file" id="rc-camera" accept="image/*" capture="environment" class="rc-hide">
      <input type="file" id="rc-gallery" accept="image/*,application/pdf" multiple class="rc-hide">
      <input type="file" id="rc-real" name="files[]" accept="image/*,application/pdf" multiple>
      <div class="rc-list" id="rc-list" aria-live="polite"></div>
      <div class="rc-tools rc-hide" id="rc-tools">
        <label class="rc-check"><input type="checkbox" id="rc-auto" checked> Nach dem Zuschneiden automatisch Text erkennen</label>
        <label class="rc-check"><input type="checkbox" id="rc-pdf" checked> Seiten beim Speichern zu einer PDF zusammenfassen</label>
        <div class="rc-take" style="margin:.2rem 0">
          <button type="button" class="sd-btn sec" id="rc-ocr">Text erkennen (OCR)</button>
          <button type="button" class="sd-btn sec" id="rc-merge">Jetzt als PDF zusammenfassen</button>
        </div>
        <div class="rc-ocr-status" id="rc-ocr-status" aria-live="polite"></div>
      </div>
      <small class="sd-muted" id="rc-hint">Mehrere Seiten sind möglich (bis zu {{ \App\Support\Documents\UploadInspector::MAX_FILES }}). Erlaubt sind Fotos und PDF, höchstens 10 MB je Datei.</small>
    </div>
  </div>
  @endif

  <div class="sd-card">
    <h2>Zuordnung</h2>
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
          <div>{{ $receipt->owner->name }}</div>
          <small class="sd-muted">Der Eigentümer eines Belegs kann nicht geändert werden.</small>
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
        <label for="f-unit_id">Einheit (falls der Beleg nur eine Einheit betrifft)</label>
        <select id="f-unit_id" name="unit_id">
          <option value="">– keine bestimmte Einheit –</option>
          @foreach ($showProperties as $property)
            @foreach ($property->units as $unit)
              <option value="{{ $unit->id }}" data-property="{{ $property->id }}" @selected($unitValue === (int) $unit->id)>{{ $property->name }}: {{ $unit->label }}</option>
            @endforeach
          @endforeach
        </select>
      </div>
    </div>
    <input type="hidden" name="owner_level" value="0">
    @if (! empty($bankTx))
      <input type="hidden" name="bank_tx" value="{{ $bankTx->id }}">
    @endif
    <label class="sd-check" for="f-owner_level" style="font-weight:400">
      <input type="checkbox" id="f-owner_level" name="owner_level" value="1" @checked($ownerLevel)>
      Gilt für den ganzen Eigentümer, nicht für ein bestimmtes Objekt (z. B. Steuerberater)
    </label>
  </div>

  <div class="sd-card">
    <h2>Angaben zum Beleg</h2>
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-receipt_date">Datum auf dem Beleg</label>
        <input id="f-receipt_date" name="receipt_date" type="date" value="{{ $dateValue }}">
      </div>
      <div class="sd-field">
        <label for="f-amount_gross">Betrag brutto in Euro</label>
        <input id="f-amount_gross" name="amount_gross" type="text" inputmode="decimal" autocomplete="off" value="{{ $amountValue }}" placeholder="z. B. 123,45">
        <small class="sd-muted">Gutschriften mit Minus, z. B. -20,00</small>
      </div>
      <div class="sd-field">
        <label for="f-cost_category">Kostenart</label>
        <select id="f-cost_category" name="cost_category">
          <option value="">– bitte wählen –</option>
          @foreach (\App\Support\Kostenarten::groups($receipt->cost_category) as $group => $items)
            <optgroup label="{{ $group }}">
              @foreach ($items as $key => $label)
                <option value="{{ $key }}" @selected((string) old('cost_category', $receipt->cost_category) === (string) $key)>{{ $label }}</option>
              @endforeach
            </optgroup>
          @endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="f-vendor_name">Lieferant oder Rechnungsteller</label>
        <input id="f-vendor_name" name="vendor_name" type="text" list="rc-vendors" autocomplete="off" maxlength="190" value="{{ old('vendor_name', $receipt->vendor_name) }}">
        <datalist id="rc-vendors">
          @foreach ($vendors as $vendor)
            <option value="{{ $vendor->name }}" data-category="{{ $vendor->default_cost_category }}"></option>
          @endforeach
        </datalist>
        <small class="sd-muted">Vorhandene Namen erscheinen zur Auswahl. Ein neuer Name legt den Lieferanten automatisch an.</small>
      </div>
      <div class="sd-field">
        <label for="f-invoice_number">Rechnungsnummer (freiwillig)</label>
        <input id="f-invoice_number" name="invoice_number" type="text" autocomplete="off" maxlength="80" value="{{ old('invoice_number', $receipt->invoice_number) }}">
        <small class="sd-muted">Steht im DATEV-Export als Belegnummer.</small>
      </div>
    </div>
    @include('partials.sd-textarea', ['name' => 'note', 'label' => 'Notiz', 'value' => $receipt->note])
    <p class="sd-muted" style="margin:.2rem 0 0">Fehlende Angaben kannst du später am Computer nachtragen. Der Beleg erscheint dann in der Liste „unvollständig“.</p>
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    @if ($isNew)
      <button class="sd-btn sec" type="submit" name="again" value="1">Speichern und nächsten Beleg erfassen</button>
    @endif
    <a class="sd-btn sec" href="{{ $isNew ? route('receipts.index') : route('receipts.show', $receipt) }}">Abbrechen</a>
  </div>
</form>
@endif

<script src="{{ route('scan.asset', 'scanner.js', false) }}?v={{ \App\Http\Controllers\ScanAssetController::stamp('scanner.js') }}"></script>
<script>
(function () {
  var form = document.getElementById('rc-form');
  if (!form) { return; }

  /* Objekt und Einheit passend zum Eigentümer anzeigen */
  var ownerSel = document.getElementById('f-owner_id');
  var propSel = document.getElementById('f-property_id');
  var unitSel = document.getElementById('f-unit_id');
  var levelBox = document.getElementById('f-owner_level');

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
  }

  if (ownerSel) { ownerSel.addEventListener('change', refresh); }
  propSel.addEventListener('change', refresh);
  levelBox.addEventListener('change', refresh);
  refresh();

  /* Bei Auswahl eines Objekts mit genau einer Einheit nichts vorbelegen: Die Einheit bleibt Absicht des Anwenders. */

  /* Übliche Kostenart des Lieferanten vorschlagen, wenn noch keine gewählt ist */
  var vendorInput = document.getElementById('f-vendor_name');
  var catSel = document.getElementById('f-cost_category');
  function suggestCategory() {
    if (!vendorInput || !catSel || catSel.value !== '') { return; }
    var opts = document.querySelectorAll('#rc-vendors option');
    for (var i = 0; i < opts.length; i++) {
      if (opts[i].value.toLowerCase() === vendorInput.value.trim().toLowerCase()) {
        var c = opts[i].getAttribute('data-category');
        if (c && catSel.querySelector('option[value="' + c + '"]')) { catSel.value = c; }
        return;
      }
    }
  }
  if (vendorInput) {
    vendorInput.addEventListener('change', suggestCategory);
    vendorInput.addEventListener('blur', suggestCategory);
  }

  /* Abschicken: nur einmal, und Betrag vorab prüfen (Fotos gehen bei einem Fehler sonst verloren) */
  var submitting = false;
  form.addEventListener('submit', function (ev) {
    if (submitting) { ev.preventDefault(); return; }
    var amount = document.getElementById('f-amount_gross');
    if (amount && amount.value.trim() !== '' && !/^[-\u2212]?[0-9.,\s\u20ac]+$/.test(amount.value.trim())) {
      ev.preventDefault();
      alert('Der Betrag ist ungültig. Bitte nur Ziffern und ein Komma eingeben, z. B. 123,45.');
      amount.focus();
      return;
    }
    submitting = true;
    var s = ev.submitter;
    if (s && s.name) {
      var h = document.createElement('input');
      h.type = 'hidden'; h.name = s.name; h.value = s.value;
      form.appendChild(h);
    }
    window.setTimeout(function () {
      Array.prototype.forEach.call(form.querySelectorAll('button[type=submit]'), function (b) { b.disabled = true; });
      if (s) { s.textContent = 'Wird gespeichert …'; }
    }, 0);
  });

  /* Foto aufnehmen: Browser verkleinert die Bilder, mehrere Seiten möglich */
  var real = document.getElementById('rc-real');
  if (!real) { return; }

  var canDT = false;
  try { canDT = !!(window.DataTransfer && window.File && new DataTransfer()); } catch (e) { canDT = false; }
  if (!canDT) { return; }

  var MAX = {{ \App\Support\Documents\UploadInspector::MAX_FILES }};
  var MAXSIDE = 2200;
  var cam = document.getElementById('rc-camera');
  var gallery = document.getElementById('rc-gallery');
  var list = document.getElementById('rc-list');
  var buttons = document.getElementById('rc-buttons');
  var hint = document.getElementById('rc-hint');
  var items = [];
  var pending = 0;

  real.classList.add('rc-hide');
  buttons.classList.remove('rc-hide');

  document.getElementById('rc-take').addEventListener('click', function () { cam.click(); });
  document.getElementById('rc-pick').addEventListener('click', function () { gallery.click(); });
  var canvasOf = typeof WeakMap === 'function' ? new WeakMap() : null;
  var ocrPromise = null;
  var tools = document.getElementById('rc-tools');
  var ocrBtn = document.getElementById('rc-ocr');
  var mergeBtn = document.getElementById('rc-merge');
  var ocrStatus = document.getElementById('rc-ocr-status');
  var autoBox = document.getElementById('rc-auto');
  var pdfBox = document.getElementById('rc-pdf');

  /* Fotos zuerst zuschneiden (Beleg-Scanner). Ohne Scanner oder bei Abbruch geht es wie bisher. */
  function prepare(fileList) {
    var arr = Array.prototype.slice.call(fileList);
    if (!window.BelegScanner || !canvasOf) { return Promise.resolve(arr); }
    var out = [];
    var chain = Promise.resolve();
    arr.forEach(function (f) {
      chain = chain.then(function () {
        if (f.type.indexOf('image/') !== 0) { out.push(f); return null; }
        return window.BelegScanner.edit(f).then(function (r) {
          if (r) { canvasOf.set(r.file, r.canvas); out.push(r.file); }
        }, function () { out.push(f); });
      });
    });
    return chain.then(function () { return out; });
  }
  function takeIn(input) {
    var copy = Array.prototype.slice.call(input.files);
    input.value = '';
    prepare(copy).then(function (r) { return add(r); }).then(function () {
      if (autoBox && autoBox.checked && items.some(function (i) { return i.canvas && !i.ocr; })) { runOcr(); }
    });
  }
  cam.addEventListener('change', function () { takeIn(cam); });
  gallery.addEventListener('change', function () { takeIn(gallery); });

  function shrink(file) {
    if (file.type.indexOf('image/') !== 0) { return Promise.resolve(file); }
    return new Promise(function (resolve) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        try {
          var w = img.naturalWidth, h = img.naturalHeight;
          var s = Math.min(1, MAXSIDE / Math.max(w, h));
          var c = document.createElement('canvas');
          c.width = Math.max(1, Math.round(w * s));
          c.height = Math.max(1, Math.round(h * s));
          var ctx = c.getContext('2d');
          ctx.fillStyle = '#fff';
          ctx.fillRect(0, 0, c.width, c.height);
          ctx.drawImage(img, 0, 0, c.width, c.height);
          c.toBlob(function (b) {
            URL.revokeObjectURL(url);
            if (!b || (s >= 1 && b.size >= file.size)) { resolve(file); return; }
            var name = (file.name || 'foto').replace(/\.[^.]+$/, '') + '.jpg';
            resolve(new File([b], name, { type: 'image/jpeg' }));
          }, 'image/jpeg', 0.82);
        } catch (e) { URL.revokeObjectURL(url); resolve(file); }
      };
      img.onerror = function () { URL.revokeObjectURL(url); resolve(file); };
      img.src = url;
    });
  }

  function setBusy(on) {
    Array.prototype.forEach.call(form.querySelectorAll('button[type=submit]'), function (b) { b.disabled = on; });
  }

  function add(files) {
    var arr = Array.prototype.slice.call(files);
    if (!arr.length) { return; }
    pending += 1;
    setBusy(true);
    var chain = Promise.resolve();
    arr.forEach(function (f) {
      chain = chain.then(function () { return canvasOf && canvasOf.has(f) ? f : shrink(f); }).then(function (g) {
        if (items.length >= MAX) { hint.textContent = 'Es sind höchstens ' + MAX + ' Dateien pro Beleg möglich.'; return; }
        items.push({ file: g, url: g.type.indexOf('image/') === 0 ? URL.createObjectURL(g) : null, canvas: canvasOf && canvasOf.get(g) || null, ocr: null, pages: 0 });
      });
    });
    return chain.then(function () {
      pending -= 1;
      sync();
      render();
      if (pending === 0) { setBusy(false); }
    });
  }

  function sync() {
    var dt = new DataTransfer();
    items.forEach(function (i) { dt.items.add(i.file); });
    real.files = dt.files;
  }

  function render() {
    list.textContent = '';
    items.forEach(function (it, idx) {
      var box = document.createElement('div');
      box.className = 'rc-item';
      if (it.url) {
        var im = document.createElement('img');
        im.src = it.url; im.alt = 'Seite ' + (idx + 1);
        box.appendChild(im);
      } else {
        var p = document.createElement('div');
        p.className = 'rc-pdf'; p.textContent = 'PDF';
        box.appendChild(p);
      }
      var label = document.createElement('span');
      label.className = 'rc-name';
      label.textContent = (it.pages ? 'PDF mit ' + it.pages + (it.pages === 1 ? ' Seite' : ' Seiten') : 'Seite ' + (idx + 1)) + ' · ' + Math.max(1, Math.round(it.file.size / 1024)) + ' KB';
      box.appendChild(label);
      var rm = document.createElement('button');
      rm.type = 'button'; rm.className = 'sd-btn danger small'; rm.textContent = 'Entfernen';
      rm.addEventListener('click', function () {
        if (it.url) { URL.revokeObjectURL(it.url); }
        items.splice(idx, 1);
        sync();
        render();
      });
      box.appendChild(rm);
      list.appendChild(box);
    });
    document.getElementById('rc-take').textContent = items.length ? 'Weitere Seite aufnehmen' : 'Foto aufnehmen';
    var scanned = items.some(function (i) { return i.canvas; });
    if (tools) { tools.classList.toggle('rc-hide', !(window.BelegScanner && scanned)); }
  }

  /* Texterkennung: füllt leere Felder, überschreibt nichts */
  function setField(id, value) {
    var f = document.getElementById(id);
    if (!f || !value || f.value.trim() !== '') { return false; }
    f.value = value;
    f.classList.add('rc-ocr');
    f.dispatchEvent(new Event('change', { bubbles: true }));
    f.addEventListener('input', function () { f.classList.remove('rc-ocr'); }, { once: true });
    return true;
  }

  function fillFromOcr() {
    var done = items.filter(function (i) { return i.ocr; });
    if (!done.length) { return; }
    var text = done.map(function (i) { return i.ocr.text; }).join('\n');
    if (!text.trim()) { ocrStatus.textContent = 'Es wurde kein Text erkannt. Bitte neu fotografieren oder die Angaben von Hand eintragen.'; return; }
    var vendors = Array.prototype.map.call(document.querySelectorAll('#rc-vendors option'), function (o) { return o.value; });
    var r = window.BelegScanner.parseReceipt(text, vendors, new Date());
    var got = [];
    if (setField('f-vendor_name', r.vendor)) { got.push('Lieferant ' + r.vendor); }
    if (setField('f-receipt_date', r.date)) { got.push('Datum ' + r.date.split('-').reverse().join('.')); }
    if (setField('f-amount_gross', r.amount)) { got.push('Betrag ' + r.amount + ' €' + (r.amountSure ? '' : ' (unsicher)')); }
    if (setField('f-invoice_number', r.invoice)) { got.push('Rechnungsnummer ' + r.invoice); }
    ocrStatus.textContent = got.length ? 'Erkannt und eingetragen (grün markiert, bitte prüfen): ' + got.join(', ') + '.' : 'Es wurde nichts Neues erkannt. Die Felder sind schon ausgefüllt oder der Text war nicht lesbar.';
  }

  function runOcr() {
    if (ocrPromise) { return ocrPromise; }
    var todo = items.filter(function (i) { return i.canvas && !i.ocr; });
    if (!todo.length) { fillFromOcr(); return Promise.resolve(); }
    if (ocrBtn) { ocrBtn.disabled = true; }
    ocrStatus.textContent = 'Texterkennung wird gestartet …';
    ocrPromise = window.BelegScanner.ocr(todo.map(function (i) { return i.canvas; }), function (t, p) {
      ocrStatus.textContent = t + ' · ' + Math.round(p * 100) + ' %';
    }).then(function (res) {
      res.forEach(function (r, k) { todo[k].ocr = r; });
      fillFromOcr();
    }).catch(function (e) {
      ocrStatus.textContent = 'Texterkennung nicht möglich: ' + (e && e.message ? e.message : 'unbekannter Fehler') + '. Du kannst die Angaben von Hand eintragen.';
    }).then(function () { ocrPromise = null; if (ocrBtn) { ocrBtn.disabled = false; } });
    return ocrPromise;
  }

  /* Gescannte Seiten zu einer PDF zusammenfassen (mit unsichtbarem Text, wenn die Texterkennung gelaufen ist) */
  function mergeToPdf() {
    var pages = items.filter(function (i) { return i.canvas; });
    if (!pages.length || !window.BelegScanner) { return Promise.resolve(false); }
    return (ocrPromise || Promise.resolve()).then(function () {
      return window.BelegScanner.buildPdf(pages.map(function (i) { return { canvas: i.canvas, words: i.ocr ? i.ocr.words : null }; }));
    }).then(function (blob) {
      var d = new Date();
      var stamp = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
      var file = new File([blob], 'Beleg-' + stamp + '.pdf', { type: 'application/pdf' });
      var rest = items.filter(function (i) { return !i.canvas; });
      items.forEach(function (i) { if (i.url) { URL.revokeObjectURL(i.url); } });
      items = rest;
      items.unshift({ file: file, url: null, canvas: null, ocr: null, pages: pages.length });
      sync(); render();
      return true;
    });
  }

  if (ocrBtn) { ocrBtn.addEventListener('click', function () { runOcr(); }); }
  if (mergeBtn) {
    mergeBtn.addEventListener('click', function () {
      mergeBtn.disabled = true;
      mergeToPdf().then(function (done) { if (done) { ocrStatus.textContent = 'Die Seiten sind jetzt eine PDF.'; } }).then(function () { mergeBtn.disabled = false; });
    });
  }
  /* Beim Speichern zuerst zur PDF zusammenfassen, dann erneut absenden */
  form.addEventListener('submit', function (ev) {
    if (!pdfBox || !pdfBox.checked || !window.BelegScanner || !items.some(function (i) { return i.canvas; })) { return; }
    ev.preventDefault();
    ev.stopImmediatePropagation();
    var submitter = ev.submitter;
    setBusy(true);
    mergeToPdf().then(function () {
      setBusy(false);
      if (typeof form.requestSubmit === 'function') { form.requestSubmit(submitter || undefined); } else { form.submit(); }
    }, function () {
      setBusy(false);
      alert('Die PDF konnte nicht erstellt werden. Bitte entferne den Haken bei „zu einer PDF zusammenfassen“ und speichere erneut.');
    });
  }, true);
})();
</script>
@endsection
