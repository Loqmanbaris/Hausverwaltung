@extends('layouts.app')
@section('title', 'Lieferanten')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Lieferanten</h1>
  <div class="sd-actions">
    <a class="sd-btn" href="{{ route('vendors.create') }}">Lieferant anlegen</a>
    <a class="sd-btn sec" href="{{ route('receipts.index') }}">Zu den Belegen</a>
  </div>
</div>
<p class="sd-muted">Lieferanten entstehen automatisch, wenn du bei einem Beleg einen neuen Namen eintippst. Hier kannst du Anschrift, Kontakt und die übliche Kostenart ergänzen. Die Liste gilt für alle Eigentümer, die Zahl der Belege zählt nur die Belege, die du sehen darfst.</p>

<form method="GET" action="{{ route('vendors.index') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:.2rem 0 1rem">
  <input type="text" name="q" value="{{ $q }}" maxlength="100" placeholder="Name, Ort oder E-Mail" aria-label="Lieferanten suchen" style="padding:.5rem;border:1px solid var(--line-strong);border-radius:.45rem;font:inherit">
  <label class="sd-check" style="margin:0;font-weight:400"><input type="checkbox" name="alle" value="1" @checked($all)> auch stillgelegte</label>
  <button class="sd-btn sec small" type="submit">Anzeigen</button>
</form>

<div class="sd-card">
  <div class="sd-table-wrap">
    <table class="sd">
      <thead><tr><th>Name</th><th>Ort</th><th>Übliche Kostenart</th><th>Belege</th><th>Stand</th></tr></thead>
      <tbody>
        @forelse ($vendors as $vendor)
          <tr>
            <td><a href="{{ route('vendors.show', $vendor) }}">{{ $vendor->name }}</a></td>
            <td>{{ $vendor->city ?: '–' }}</td>
            <td>{{ $vendor->defaultCategoryLabel() }}</td>
            <td>{{ $vendor->receipts_count }}</td>
            <td>@if ($vendor->active) aktiv @else <span class="sd-muted">stillgelegt</span> @endif</td>
          </tr>
        @empty
          <tr><td colspan="5" class="sd-muted">Keine Lieferanten gefunden.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pager">
  @if ($vendors->previousPageUrl())
    <a class="sd-btn sec small" href="{{ $vendors->previousPageUrl() }}">Zurück</a>
  @else
    <span></span>
  @endif
  @if ($vendors->nextPageUrl())
    <a class="sd-btn sec small" href="{{ $vendors->nextPageUrl() }}">Weiter</a>
  @endif
</div>
@endsection
