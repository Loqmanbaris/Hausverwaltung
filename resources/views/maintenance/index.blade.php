@extends('layouts.app')
@section('title', 'Wartungsplan')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('workorders.styles')
@php($W = \App\Support\Technik\Wartung::class)
<div class="sd-head">
  <h1>Wartungsplan</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('workorders.index') }}">Schäden und Aufträge</a>
    <a class="sd-btn" href="{{ route('maintenance.create', array_filter(['objekt' => $pid])) }}">Wartung anlegen</a>
  </div>
</div>
<form method="GET" action="{{ route('maintenance.index') }}" class="sd-actions" style="margin-bottom:1rem">
  <select name="objekt" style="width:auto" onchange="this.form.submit()" aria-label="Objekt"><option value="">Alle Objekte</option>@foreach ($properties as $p)<option value="{{ $p->id }}" @selected($pid === (int) $p->id)>{{ $p->name }}</option>@endforeach</select>
  <noscript><button class="sd-btn sec small" type="submit">Anzeigen</button></noscript>
</form>

<div class="sd-card">
  @if ($tasks->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine Wartungen geplant. Lege unten mit einem Klick die üblichen Prüfungen für ein Objekt an.</p>
  @else
    <ul class="wo-list">
      @foreach ($tasks as $t)
        @php($st = $t->state())
        <li>
          <div>
            <a class="t" href="{{ route('maintenance.edit', $t) }}">{{ $t->title }}</a>
            <div class="wo-sub">{{ $t->place() }} · {{ $t->intervalLabel() }}{{ $t->vendor ? ' · '.$t->vendor->name : '' }}{{ $t->last_done ? ' · zuletzt '.$t->last_done->format('d.m.Y') : '' }}</div>
            <details style="margin-top:.35rem"><summary class="sd-muted" style="cursor:pointer;font-size:.88rem">Erledigt eintragen / Auftrag</summary>
              <div class="sd-actions" style="margin-top:.4rem">
                <form method="POST" action="{{ route('maintenance.done', $t) }}" class="sd-actions">@csrf
                  <input type="date" name="done_on" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}" required style="width:auto" aria-label="Erledigt am">
                  <input name="note" maxlength="500" placeholder="Notiz" style="width:auto" aria-label="Notiz">
                  <button class="sd-btn small" type="submit">Erledigt</button>
                </form>
                <a class="sd-btn sec small" href="{{ route('workorders.create', ['wartung' => $t->id]) }}">Auftrag an Firma</a>
              </div>
            </details>
          </div>
          <div style="text-align:right"><span class="wo-due {{ $st }}">{{ $st === 'ueberfaellig' ? 'überfällig seit' : 'fällig' }} {{ $t->next_due->format('d.m.Y') }}</span></div>
        </li>
      @endforeach
    </ul>
  @endif
</div>

<div class="sd-card">
  <h2>Übliche Prüfungen für ein Objekt anlegen</h2>
  <form method="POST" action="{{ route('maintenance.defaults') }}">@csrf
    <div class="wo-form" style="margin-bottom:.6rem">
      <div><label for="d-p">Objekt</label><select id="d-p" name="property_id" required>@foreach ($properties as $p)<option value="{{ $p->id }}" @selected($pid === (int) $p->id)>{{ $p->name }}</option>@endforeach</select></div>
      <div><label for="d-d">Erster Termin</label><input id="d-d" type="date" name="first_due" required value="{{ date('Y-m-d', strtotime('+1 month')) }}"></div>
    </div>
    @foreach (collect($W::CATALOG)->except('eigen')->groupBy(fn ($c) => $c[3], true) as $group => $items)
      <div style="margin:.4rem 0 .2rem;font-weight:700;font-size:.9rem">{{ $group }}</div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(17rem,1fr));gap:.2rem .8rem">
        @foreach ($items as $k => [$title, $months, $hint])
          <label style="display:flex;gap:.45rem;align-items:flex-start;font-weight:500;margin:0" title="{{ $hint }}"><input type="checkbox" name="types[]" value="{{ $k }}" style="width:auto;margin-top:.3rem" @checked(in_array($k, ['rauchmelder', 'heizung', 'schornsteinfeger'], true))><span>{{ $title }} <span class="sd-muted" style="font-size:.82rem">({{ $W::intervalLabel($months) }})</span></span></label>
        @endforeach
      </div>
    @endforeach
    <p class="sd-muted" style="font-size:.85rem">Die Abstände sind übliche Werte. Maßgeblich sind Landesrecht, Feuerstättenbescheid und Herstellerangaben – Termine danach anpassen.</p>
    <button class="sd-btn" type="submit">Anlegen</button>
  </form>
</div>

@if ($inactive->isNotEmpty())
  <details class="sd-card"><summary style="cursor:pointer;font-weight:700">Beendet und einmalige erledigt ({{ $inactive->count() }})</summary>
    <ul class="wo-list" style="margin-top:.5rem">@foreach ($inactive as $t)<li><div><a href="{{ route('maintenance.edit', $t) }}">{{ $t->title }}</a><div class="wo-sub">{{ $t->place() }}{{ $t->last_done ? ' · zuletzt '.$t->last_done->format('d.m.Y') : '' }}</div></div><div></div></li>@endforeach</ul>
  </details>
@endif
@endsection
