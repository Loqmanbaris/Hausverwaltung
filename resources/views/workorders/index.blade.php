@extends('layouts.app')
@section('title', 'Schäden und Aufträge')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('workorders.styles')
<div class="sd-head">
  <h1>Schäden und Aufträge</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('maintenance.index') }}">Wartungsplan</a>
    <a class="sd-btn" href="{{ route('workorders.create') }}">Schaden erfassen</a>
  </div>
</div>
@php
  $open = ($counts['gemeldet'] ?? 0) + ($counts['beauftragt'] ?? 0) + ($counts['termin'] ?? 0);
  $tabs = ['offen' => 'Offen ('.$open.')', 'gemeldet' => 'Neu gemeldet ('.($counts['gemeldet'] ?? 0).')', 'erledigt' => 'Erledigt, Rechnung fehlt ('.($counts['erledigt'] ?? 0).')', 'abgerechnet' => 'Abgerechnet', 'alle' => 'Alle'];
@endphp
<div class="sd-actions" style="margin-bottom:.8rem">
  @foreach ($tabs as $k => $l)
    <a @class(['sd-btn small', 'sec' => $state !== $k]) href="{{ route('workorders.index', array_filter(['stand' => $k, 'objekt' => $pid])) }}">{{ $l }}</a>
  @endforeach
</div>
<form method="GET" action="{{ route('workorders.index') }}" class="sd-actions" style="margin-bottom:1rem">
  <input type="hidden" name="stand" value="{{ $state }}">
  <select name="objekt" style="width:auto" aria-label="Objekt" onchange="this.form.submit()"><option value="">Alle Objekte</option>@foreach ($properties as $p)<option value="{{ $p->id }}" @selected($pid === (int) $p->id)>{{ $p->name }}</option>@endforeach</select>
  <input type="search" name="q" value="{{ $q }}" placeholder="Suchen" style="width:auto" aria-label="Suche">
  <button class="sd-btn sec small" type="submit">Anzeigen</button>
</form>
<div class="sd-card">
  @if ($orders->isEmpty())
    <p class="sd-muted" style="margin:0">Keine Einträge.</p>
  @else
    <ul class="wo-list">
      @foreach ($orders as $o)
        <li>
          <div>
            @if ($o->priority !== 'normal')<span class="wo-pr">{{ $o->priority === 'notfall' ? 'Notfall' : 'Dringend' }}</span> @endif
            <a class="t" href="{{ route('workorders.show', $o) }}">{{ $o->title }}</a>
            <div class="wo-sub">{{ $o->number() }} · {{ $o->place() }} · {{ $o->created_at->format('d.m.Y') }}{{ $o->vendor ? ' · '.$o->vendor->name : '' }}{{ $o->appointment_at && $o->status === 'termin' ? ' · Termin '.$o->appointment_at->format('d.m. H:i') : '' }}</div>
          </div>
          <div><span class="wo-st {{ $o->status }}">{{ $o->statusLabel() }}</span></div>
        </li>
      @endforeach
    </ul>
    <div class="sd-actions" style="margin-top:.8rem">
      @if ($orders->previousPageUrl())<a class="sd-btn sec small" href="{{ $orders->previousPageUrl() }}">Zurück</a>@endif
      @if ($orders->nextPageUrl())<a class="sd-btn sec small" href="{{ $orders->nextPageUrl() }}">Weiter</a>@endif
    </div>
  @endif
</div>
@endsection
