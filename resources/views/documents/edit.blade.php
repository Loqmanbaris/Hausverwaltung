@extends('layouts.app')
@section('title', 'Dokument ändern')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('documents.styles')
<div class="sd-head">
  <h1>Dokument ändern</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('documents.file', $doc) }}" target="_blank" rel="noopener">Ansehen</a>
    @if ($target)<a class="sd-btn sec" href="{{ $target[3] }}">Zurück zu {{ $target[2] }}</a>@endif
  </div>
</div>
<form method="POST" action="{{ route('documents.update', $doc) }}" class="sd-card">
  @csrf
  @method('PUT')
  <p class="sd-muted" style="margin-top:0">Datei: {{ $doc->original_name }} · {{ $doc->sizeLabel() }} · abgelegt am {{ $doc->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}{{ $doc->creator ? ' von '.$doc->creator->name : '' }}</p>
  <div class="dk-grid">
    <div style="grid-column:1/-1"><label for="d-title">Bezeichnung *</label><input id="d-title" name="title" required maxlength="190" value="{{ old('title', $doc->title) }}"></div>
    <div><label for="d-cat">Art *</label>
      <select id="d-cat" name="category">
        @foreach (\App\Support\Ablage\Ablage::categoriesFor($doc->attach_type) as $k => [$label])
          <option value="{{ $k }}" @selected(old('category', $doc->category) === $k)>{{ $label }}</option>
        @endforeach
      </select>
    </div>
    <div><label for="d-date">Datum des Dokuments</label><input id="d-date" type="date" name="doc_date" value="{{ old('doc_date', $doc->doc_date?->format('Y-m-d')) }}"></div>
    <div><label for="d-exp">Gültig bis / Frist</label><input id="d-exp" type="date" name="expires_on" value="{{ old('expires_on', $doc->expires_on?->format('Y-m-d')) }}"></div>
    <div><label for="d-rem">Erinnern … Tage vorher</label><input id="d-rem" type="number" min="1" max="365" name="remind_days" value="{{ old('remind_days', $doc->remind_days) }}"></div>
    <div style="grid-column:1/-1"><label for="d-note">Notiz</label><textarea id="d-note" name="note" rows="3" maxlength="2000">{{ old('note', $doc->note) }}</textarea></div>
    <label style="display:flex;gap:.5rem;align-items:center;font-weight:500;grid-column:1/-1"><input type="checkbox" name="archived" value="1" style="width:auto" @checked(old('archived', $doc->archived))> Archiviert (erscheint nicht mehr bei den Fristen)</label>
  </div>
  <div style="margin-top:.8rem"><button class="sd-btn" type="submit">Speichern</button></div>
</form>
<form method="POST" action="{{ route('documents.destroy', $doc) }}" onsubmit="return confirm('Dokument endgültig löschen?')" style="margin-top:1rem">
  @csrf
  @method('DELETE')
  <button class="sd-btn danger" type="submit">Dokument löschen</button>
</form>
@endsection
