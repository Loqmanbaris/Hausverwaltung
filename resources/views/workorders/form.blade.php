@extends('layouts.app')
@section('title', 'Schaden erfassen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('workorders.styles')
<div class="sd-head">
  <h1>{{ $task ? 'Auftrag für Wartung' : 'Schaden erfassen' }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('workorders.index') }}">Abbrechen</a></div>
</div>
<form method="POST" action="{{ route('workorders.store') }}" enctype="multipart/form-data" class="sd-card">
  @csrf
  @if ($task)<input type="hidden" name="maintenance_task_id" value="{{ $task->id }}">@endif
  <div class="wo-form">
    <div><label for="w-prop">Objekt *</label>
      <select id="w-prop" name="property_id" required><option value="">– bitte wählen –</option>@foreach ($properties as $p)<option value="{{ $p->id }}" @selected((int) old('property_id', $o->property_id) === (int) $p->id)>{{ $p->name }}</option>@endforeach</select></div>
    <div><label for="w-unit">Einheit</label>
      <select id="w-unit" name="unit_id"><option value="">ganzes Haus / Allgemeinbereich</option>@foreach ($units as $u)<option value="{{ $u->id }}" data-p="{{ $u->property_id }}" @selected((int) old('unit_id', $o->unit_id) === (int) $u->id)>{{ $u->label }}</option>@endforeach</select></div>
    <div><label for="w-prio">Dringlichkeit</label>
      <select id="w-prio" name="priority">@foreach (\App\Models\WorkOrder::PRIORITY as $k => $l)<option value="{{ $k }}" @selected(old('priority', $o->priority) === $k)>{{ $l }}</option>@endforeach</select></div>
    <div class="full"><label for="w-title">Was ist los? *</label><input id="w-title" name="title" required maxlength="190" value="{{ old('title', $o->title) }}" placeholder="z. B. Wasserhahn in der Küche tropft"></div>
    <div class="full"><label for="w-desc">Beschreibung</label><textarea id="w-desc" name="description" rows="4" maxlength="5000" placeholder="Seit wann, wo genau, was wurde schon versucht …">{{ old('description', $o->description) }}</textarea></div>
    <div><label for="w-rn">Gemeldet von</label><input id="w-rn" name="reporter_name" maxlength="120" value="{{ old('reporter_name') }}" placeholder="leer = Mieter der Einheit"></div>
    <div><label for="w-rp">Telefon</label><input id="w-rp" name="reporter_phone" maxlength="60" value="{{ old('reporter_phone') }}"></div>
    <div class="full"><label for="w-f">Fotos</label><input id="w-f" type="file" name="fotos[]" accept="image/*" multiple capture="environment"><small class="sd-muted">Bis zu 15 Fotos, am Handy direkt aufnehmen.</small></div>
  </div>
  <div style="margin-top:.9rem"><button class="sd-btn" type="submit">Speichern</button></div>
</form>
<script>
(function () {
  var p = document.getElementById('w-prop'), u = document.getElementById('w-unit');
  function upd() { Array.prototype.forEach.call(u.options, function (o) { if (o.value) o.hidden = o.getAttribute('data-p') !== p.value; }); if (u.selectedOptions[0] && u.selectedOptions[0].hidden) u.value = ''; }
  p.addEventListener('change', upd); upd();
})();
</script>
@endsection
