@extends('layouts.app')
@section('title', $t->exists ? 'Wartung bearbeiten' : 'Wartung anlegen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('workorders.styles')
@php($W = \App\Support\Technik\Wartung::class)
<div class="sd-head">
  <h1>{{ $t->exists ? $t->title : 'Wartung anlegen' }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('maintenance.index') }}">Zurück</a></div>
</div>
<form method="POST" action="{{ $t->exists ? route('maintenance.update', $t) : route('maintenance.store') }}" class="sd-card">
  @csrf @if ($t->exists) @method('PUT') @endif
  <div class="wo-form">
    <div><label for="m-type">Art</label>
      <select id="m-type" name="type">@foreach ($W::CATALOG as $k => [$title, $months, $hint])<option value="{{ $k }}" data-title="{{ $k === 'eigen' ? '' : $title }}" data-months="{{ $months }}" data-hint="{{ $hint }}" @selected(old('type', $t->type) === $k)>{{ $title }}</option>@endforeach</select>
      <small class="sd-muted" id="m-hint"></small></div>
    <div><label for="m-title">Bezeichnung *</label><input id="m-title" name="title" required maxlength="150" value="{{ old('title', $t->title) }}"></div>
    <div><label for="m-prop">Objekt *</label><select id="m-prop" name="property_id" required>@foreach ($properties as $p)<option value="{{ $p->id }}" @selected((int) old('property_id', $t->property_id) === (int) $p->id)>{{ $p->name }}</option>@endforeach</select></div>
    <div><label for="m-unit">Einheit</label><select id="m-unit" name="unit_id"><option value="">ganzes Haus</option>@foreach ($units as $u)<option value="{{ $u->id }}" data-p="{{ $u->property_id }}" @selected((int) old('unit_id', $t->unit_id) === (int) $u->id)>{{ $u->label }}</option>@endforeach</select></div>
    <div><label for="m-int">Wiederholung</label><select id="m-int" name="interval_months"><option value="">einmalig</option>@foreach ($W::INTERVALS as $m => $l)<option value="{{ $m }}" @selected((int) old('interval_months', $t->interval_months) === (int) $m)>{{ $l }}</option>@endforeach</select></div>
    <div><label for="m-next">Nächster Termin *</label><input id="m-next" type="date" name="next_due" required value="{{ old('next_due', $t->next_due?->format('Y-m-d')) }}"></div>
    <div><label for="m-last">Zuletzt erledigt</label><input id="m-last" type="date" name="last_done" value="{{ old('last_done', $t->last_done?->format('Y-m-d')) }}"></div>
    <div><label for="m-v">Firma</label><select id="m-v" name="vendor_id"><option value="">–</option>@foreach ($vendors as $v)<option value="{{ $v->id }}" @selected((int) old('vendor_id', $t->vendor_id) === (int) $v->id)>{{ $v->name }}</option>@endforeach</select></div>
    <div><label for="m-rem">Erinnern … Tage vorher</label><input id="m-rem" type="number" min="1" max="365" name="remind_days" value="{{ old('remind_days', $t->remind_days ?? 30) }}"></div>
    <div class="full"><label for="m-note">Notiz</label><textarea id="m-note" name="note" rows="2" maxlength="2000">{{ old('note', $t->note) }}</textarea></div>
  </div>
  <div style="margin-top:.8rem"><button class="sd-btn" type="submit">Speichern</button></div>
</form>
@if ($t->exists)
  @if ($t->logs->isNotEmpty())
    <div class="sd-card"><h2>Erledigt</h2><ul class="wo-notes">@foreach ($t->logs as $l)<li><div class="h">{{ $l->done_on->format('d.m.Y') }}{{ $l->user ? ' · '.$l->user->name : '' }}@if ($l->work_order_id) · <a href="{{ route('workorders.show', $l->work_order_id) }}">Auftrag</a>@endif</div>@if ($l->note)<div class="b">{{ $l->note }}</div>@endif</li>@endforeach</ul></div>
  @endif
  <form method="POST" action="{{ route('maintenance.destroy', $t) }}" onsubmit="return confirm('Wartung beenden? Sie erscheint dann nicht mehr im Plan.')">@csrf @method('DELETE')<button class="sd-btn danger small" type="submit">Wartung beenden</button></form>
@endif
<script>
(function () {
  var ty = document.getElementById('m-type'), ti = document.getElementById('m-title'), iv = document.getElementById('m-int'), hi = document.getElementById('m-hint');
  var p = document.getElementById('m-prop'), u = document.getElementById('m-unit');
  function hint() { hi.textContent = ty.selectedOptions[0].getAttribute('data-hint') || ''; }
  ty.addEventListener('change', function () { var o = ty.selectedOptions[0]; if (o.getAttribute('data-title')) ti.value = o.getAttribute('data-title'); iv.value = o.getAttribute('data-months'); hint(); });
  function units() { Array.prototype.forEach.call(u.options, function (o) { if (o.value) o.hidden = o.getAttribute('data-p') !== p.value; }); if (u.selectedOptions[0] && u.selectedOptions[0].hidden) u.value = ''; }
  p.addEventListener('change', units); units(); hint();
})();
</script>
@endsection
