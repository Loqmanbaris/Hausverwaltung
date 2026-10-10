@extends('layouts.app')
@section('title', 'Protokoll abschließen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('handover.body-styles')
<style>
.sg-paper{background:#fff;border:1px solid #e3e6ea;border-radius:.6rem;padding:1rem 1.2rem;max-height:55vh;overflow:auto;margin-bottom:1rem}
.sg-pad{border:2px dashed #98a2b3;border-radius:.6rem;background:#fff;position:relative;touch-action:none}
.sg-pad canvas{display:block;width:100%;height:170px;touch-action:none}
.sg-pad .x{position:absolute;left:1rem;right:1rem;bottom:2.2rem;border-bottom:1px solid #d0d5dd;pointer-events:none}
.sg-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:1rem}
</style>
<div class="sd-head">
  <h1>{{ $p->title() }} abschließen</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('handover.edit', $p) }}">Zurück zum Bearbeiten</a></div>
</div>
<p class="sd-muted">Bitte das Protokoll gemeinsam durchsehen. Nach dem Abschließen kann es nicht mehr geändert werden, und die Zählerstände werden in die Nebenkosten übernommen.</p>
<div class="sg-paper">@include('handover.body', ['sig' => ['tenant' => null, 'landlord' => null]])</div>

<form method="POST" action="{{ route('handover.sign.store', $p) }}" id="sg-form">
  @csrf
  <input type="hidden" name="method" value="tablet" id="sg-method">
  <input type="hidden" name="tenant_signature" id="sg-tenant">
  <input type="hidden" name="landlord_signature" id="sg-landlord">
  <div class="sd-card">
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'tenant_name', 'label' => 'Name Mieter', 'value' => $p->lease?->tenant?->type === 'firma' ? '' : trim(($p->lease?->tenant?->first_name ?? '').' '.($p->lease?->tenant?->last_name ?? ''))])
      @include('partials.sd-field', ['name' => 'landlord_name', 'label' => 'Name Vermieter', 'value' => $landlordName])
    </div>
  </div>
  <div class="sg-grid">
    <div class="sd-card"><h2>Unterschrift Mieter</h2><div class="sg-pad"><canvas data-target="sg-tenant"></canvas><div class="x"></div></div><button type="button" class="sd-btn sec small" data-clear="sg-tenant" style="margin-top:.4rem">Löschen</button></div>
    <div class="sd-card"><h2>Unterschrift Vermieter</h2><div class="sg-pad"><canvas data-target="sg-landlord"></canvas><div class="x"></div></div><button type="button" class="sd-btn sec small" data-clear="sg-landlord" style="margin-top:.4rem">Löschen</button></div>
  </div>
  <div class="sd-actions" style="justify-content:flex-start">
    <button class="sd-btn" type="submit">Unterschriften speichern und abschließen</button>
    <button class="sd-btn sec" type="submit" onclick="document.getElementById('sg-method').value='papier';this.form.dataset.paper='1'">Auf Papier unterschrieben – nur abschließen</button>
  </div>
  <p class="sd-muted">Bei „auf Papier“: vorher das Protokoll drucken und von beiden unterschreiben lassen.</p>
</form>

<script>
(function () {
  var pads = {};
  document.querySelectorAll('.sg-pad canvas').forEach(function (cv) {
    var ctx, drawing = false, last = null, strokes = 0;
    function setup() { var r = window.devicePixelRatio || 1; cv.width = cv.clientWidth * r; cv.height = cv.clientHeight * r; ctx = cv.getContext('2d'); ctx.scale(r, r); ctx.lineWidth = 2.2; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0b1f44'; strokes = 0; }
    function pos(e) { var b = cv.getBoundingClientRect(); return { x: e.clientX - b.left, y: e.clientY - b.top }; }
    cv.addEventListener('pointerdown', function (e) { e.preventDefault(); cv.setPointerCapture(e.pointerId); drawing = true; last = pos(e); });
    cv.addEventListener('pointermove', function (e) { if (!drawing) return; e.preventDefault(); var p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); last = p; strokes++; });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach(function (t) { cv.addEventListener(t, function () { drawing = false; }); });
    setup();
    pads[cv.dataset.target] = { cv: cv, reset: setup, used: function () { return strokes > 8; } };
  });
  document.querySelectorAll('[data-clear]').forEach(function (b) { b.addEventListener('click', function () { pads[b.dataset.clear].reset(); }); });
  document.getElementById('sg-form').addEventListener('submit', function (e) {
    if (this.dataset.paper === '1') { if (!confirm('Protokoll ohne Unterschrift am Tablet abschließen? Bitte nur, wenn es auf Papier unterschrieben ist.')) { e.preventDefault(); this.dataset.paper = ''; document.getElementById('sg-method').value = 'tablet'; } return; }
    for (var id in pads) {
      if (!pads[id].used()) { e.preventDefault(); alert('Bitte ' + (id === 'sg-tenant' ? 'den Mieter' : 'den Vermieter') + ' unterschreiben lassen.'); return; }
      document.getElementById(id).value = pads[id].cv.toDataURL('image/png');
    }
  });
})();
</script>
@endsection
