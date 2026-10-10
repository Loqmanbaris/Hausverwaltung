@extends('layouts.app')
@section('title', 'Vertrag unterschreiben')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('contracts.document-styles')
<style>
.sg-paper{background:#fff;border:1px solid #e3e6ea;border-radius:.6rem;padding:1.2rem 1.4rem;max-height:60vh;overflow:auto;margin-bottom:1rem}
.sg-pad{border:2px dashed #98a2b3;border-radius:.6rem;background:#fff;position:relative;touch-action:none}
.sg-pad canvas{display:block;width:100%;height:180px;touch-action:none;cursor:crosshair}
.sg-pad .x{position:absolute;left:1rem;right:1rem;bottom:2.4rem;border-bottom:1px solid #d0d5dd;pointer-events:none}
.sg-pad .hint{position:absolute;left:1rem;bottom:.5rem;color:#98a2b3;font-size:.85rem;pointer-events:none}
.sg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:1rem}
.sg-hint{background:#fffaeb;border:1px solid #fedf89;border-radius:.6rem;padding:.6rem .9rem;margin:0 0 1rem;color:#7a4b00}
</style>
<div class="sd-head">
  <h1>Vertrag unterschreiben</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('contracts.show', $c) }}">Abbrechen</a></div>
</div>
@if ($paper)
  <div class="sg-hint">Achtung: Für diesen Vertrag ist die Schriftform vorgeschrieben (fester Zeitraum oder Kündigungsverzicht über einem Jahr). Eine Unterschrift am Tablet ersetzt die Unterschrift auf Papier nicht. Bitte ausdrucken und von Hand unterschreiben.</div>
@endif
<p class="sd-muted">Bitte den Vertrag gemeinsam durchlesen. Darunter unterschreiben zuerst der Mieter, dann der Vermieter, mit dem Finger oder einem Stift. Nach dem Speichern kann der Text nicht mehr geändert werden.</p>

<div class="sg-paper">
  @include('contracts.document', ['c' => $c, 'sig' => ['tenant' => null, 'landlord' => null]])
</div>

<form method="POST" action="{{ route('contracts.sign.store', $c) }}" id="sg-form">
  @csrf
  <input type="hidden" name="hash" value="{{ $c->content_hash }}">
  <input type="hidden" name="tenant_signature" id="sg-tenant">
  <input type="hidden" name="landlord_signature" id="sg-landlord">
  <div class="sd-card">
    <label class="sd-check"><input type="checkbox" name="read" value="1" required> Der Vertrag wurde vollständig gelesen. Beide Seiten haben ein Exemplar als Ausdruck oder PDF erhalten bzw. erhalten es nach der Unterschrift.</label>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'place', 'label' => 'Ort', 'value' => $c->header['ort'] ?? '', 'required' => true])
      @include('partials.sd-field', ['name' => 'tenant_name', 'label' => 'Name des Mieters (wer unterschreibt)', 'value' => $tenantName, 'required' => true])
      @include('partials.sd-field', ['name' => 'landlord_name', 'label' => 'Name des Vermieters (wer unterschreibt)', 'value' => $landlordName, 'required' => true])
    </div>
  </div>
  <div class="sg-grid">
    <div class="sd-card">
      <h2>Unterschrift Mieter</h2>
      <div class="sg-pad"><canvas data-target="sg-tenant"></canvas><div class="x"></div><div class="hint">Hier unterschreiben</div></div>
      <button type="button" class="sd-btn sec small" data-clear="sg-tenant" style="margin-top:.4rem">Löschen</button>
    </div>
    <div class="sd-card">
      <h2>Unterschrift Vermieter</h2>
      <div class="sg-pad"><canvas data-target="sg-landlord"></canvas><div class="x"></div><div class="hint">Hier unterschreiben</div></div>
      <button type="button" class="sd-btn sec small" data-clear="sg-landlord" style="margin-top:.4rem">Löschen</button>
    </div>
  </div>
  <p><button class="sd-btn" type="submit">Unterschriften speichern</button></p>
</form>

<script>
(function () {
  var pads = {};
  document.querySelectorAll('.sg-pad canvas').forEach(function (cv) {
    var ctx, drawing = false, last = null, strokes = 0;
    function setup() {
      var r = window.devicePixelRatio || 1, w = cv.clientWidth, h = cv.clientHeight;
      cv.width = w * r; cv.height = h * r;
      ctx = cv.getContext('2d'); ctx.scale(r, r);
      ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0b1f44';
      strokes = 0;
    }
    function pos(e) { var b = cv.getBoundingClientRect(); return { x: e.clientX - b.left, y: e.clientY - b.top }; }
    cv.addEventListener('pointerdown', function (e) { e.preventDefault(); cv.setPointerCapture(e.pointerId); drawing = true; last = pos(e); ctx.beginPath(); ctx.arc(last.x, last.y, 1, 0, 6.3); ctx.fillStyle = ctx.strokeStyle; ctx.fill(); });
    cv.addEventListener('pointermove', function (e) { if (!drawing) return; e.preventDefault(); var p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; strokes++; });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (t) { cv.addEventListener(t, function () { drawing = false; }); });
    setup();
    pads[cv.dataset.target] = { cv: cv, reset: setup, used: function () { return strokes > 8; } };
  });
  document.querySelectorAll('[data-clear]').forEach(function (b) { b.addEventListener('click', function () { pads[b.dataset.clear].reset(); }); });
  document.getElementById('sg-form').addEventListener('submit', function (e) {
    for (var id in pads) {
      if (!pads[id].used()) { e.preventDefault(); alert('Bitte ' + (id === 'sg-tenant' ? 'den Mieter' : 'den Vermieter') + ' unterschreiben lassen.'); return; }
      document.getElementById(id).value = pads[id].cv.toDataURL('image/png');
    }
  });
})();
</script>
@endsection
