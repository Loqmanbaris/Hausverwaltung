@extends('layouts.app')
@section('title', 'Import aus Excel')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  $labels = ['objekte' => 'Objekte', 'einheiten' => 'Einheiten', 'mieter' => 'Mieter', 'vertraege' => 'Mietverträge', 'zaehler' => 'Zähler'];
@endphp
<div class="sd-head"><h1>Import aus Excel</h1></div>
<p class="sd-muted">Objekte, Einheiten, Mieter, Mietverträge und Zähler auf einmal übernehmen. Die Datei wird zuerst geprüft; eingetragen wird erst nach deiner Bestätigung, und nur wenn alles fehlerfrei ist.</p>

<div class="sd-card">
  <h2>1. Vorlage herunterladen und ausfüllen</h2>
  <p style="margin-top:0">Die Vorlage hat ein Blatt „Anleitung“ mit Beispielen und den Namen deiner Eigentümer.</p>
  <a class="sd-btn sec" href="{{ route('import.template') }}">Excel-Vorlage herunterladen</a>
  <p class="sd-muted" style="margin:.8rem 0 0"><strong>Tipp für alte Verträge:</strong> In der Spalte „Soll ab“ ein Datum eintragen, z. B. den 1. Januar dieses Jahres. Mieten davor gelten dann als bezahlt, und das Mieterkonto beginnt ohne künstlichen Rückstand. Diese Übernahme zählt nicht als Mieteinnahme (nicht in DATEV, nicht in der Anlage V).</p>
</div>

<div class="sd-card">
  <h2>2. Ausgefüllte Datei hochladen und prüfen</h2>
  <form method="POST" action="{{ route('import.check') }}" enctype="multipart/form-data">
    @csrf
    <input type="file" name="datei" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
    <button class="sd-btn" type="submit">Prüfen</button>
  </form>
</div>

@if ($result)
  <div class="sd-card">
    <h2>3. Ergebnis der Prüfung{{ isset($fileName) ? ' – '.$fileName : '' }}</h2>
    <div class="rt-stats">
      @foreach ($labels as $k => $label)
        <div class="rt-stat"><strong>{{ $result['counts'][$k] }}</strong><span>{{ $label }} neu</span></div>
      @endforeach
    </div>
    @if ($result['errors'])
      <div class="sd-err"><strong>{{ count($result['errors']) }} Fehler – es wurde nichts eingetragen.</strong> Bitte in der Datei korrigieren und erneut hochladen.</div>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Blatt</th><th>Zeile</th><th>Fehler</th></tr></thead>
          <tbody>
            @foreach ($result['errors'] as $e)<tr><td>{{ $e['sheet'] }}</td><td>{{ $e['row'] }}</td><td class="rt-late">{{ $e['message'] }}</td></tr>@endforeach
          </tbody>
        </table>
      </div>
    @elseif (array_sum($result['counts']) === 0)
      <p>In der Datei wurde nichts Neues gefunden.</p>
    @endif
    @if ($result['warnings'])
      <h3 style="font-size:1rem;margin:1rem 0 .3rem">Hinweise</h3>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Blatt</th><th>Zeile</th><th>Hinweis</th></tr></thead>
          <tbody>
            @foreach ($result['warnings'] as $w)<tr><td>{{ $w['sheet'] }}</td><td>{{ $w['row'] }}</td><td>{{ $w['message'] }}</td></tr>@endforeach
          </tbody>
        </table>
      </div>
    @endif
    @if (! $result['errors'] && array_sum($result['counts']) > 0)
      <form method="POST" action="{{ route('import.run') }}" style="margin-top:1rem" onsubmit="return confirm('Jetzt alles eintragen?')">
        @csrf
        <button class="sd-btn" type="submit">Jetzt eintragen</button>
        <span class="sd-muted">Vorher am besten unter „Sicherungen“ eine Sicherung erstellen.</span>
      </form>
    @endif
  </div>
@endif
@endsection
