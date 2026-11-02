@extends('layouts.app')
@section('title', 'Bescheinigungen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  $bsDe = fn (?string $d) => \App\Support\Bescheinigung\Bescheinigung::de($d);
  $bsAuszug = $ende && $ende <= date('Y-m-d', strtotime('+3 months'));
@endphp
<style>
.bs-date{padding:.55rem .7rem;border:1px solid var(--line-strong,#d1d5db);border-radius:8px;font:inherit;background:#fff;width:100%;box-sizing:border-box}
.bs-radio{display:flex;gap:1.2rem;flex-wrap:wrap;margin-top:.3rem}
.bs-radio label{font-weight:400;display:flex;gap:.4rem;align-items:center}
</style>
<div class="sd-head">
  <h1>Bescheinigungen</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('leases.show', $lease) }}">Zurück zum Mietvertrag</a></div>
</div>
<p class="sd-muted">{{ $mieter }} · {{ $lage ?: '–' }} · {{ $wohnung }} · Mietbeginn {{ $bsDe($beginn) }}@if ($ende), Ende {{ $bsDe($ende) }}@endif</p>

<div class="sd-card">
  <h2>Wohnungsgeberbestätigung</h2>
  <p class="sd-muted" style="margin-top:0">Pflicht nach § 19 Bundesmeldegesetz: Wer ein- oder auszieht, braucht die Bestätigung für die Anmeldung beim Bürgeramt. Sie muss innerhalb von zwei Wochen nach dem Ein- bzw. Auszug vorliegen. Bitte alle Personen eintragen, die ein- oder ausziehen (auch Kinder).</p>
  <form method="GET" action="{{ route('bescheinigung.wohnungsgeber', $lease) }}" target="_blank" rel="noopener">
    <div class="sd-field">
      <label>Vorgang *</label>
      <div class="bs-radio">
        @foreach ($vorgaenge as $k => $label)
          <label><input type="radio" name="vorgang" value="{{ $k }}" @checked(old('vorgang', $bsAuszug ? 'auszug' : 'einzug') === $k) required> {{ $label }}</label>
        @endforeach
      </div>
    </div>
    <div class="sd-grid">
      <div class="sd-field">
        <label for="bs-datum">Datum des Ein- bzw. Auszugs *</label>
        <input id="bs-datum" class="bs-date" type="date" name="datum" value="{{ old('datum', $bsAuszug ? $ende : $beginn) }}" required>
        <small class="sd-muted">Vorschlag: Mietbeginn bzw. Vertragsende.</small>
      </div>
      <div class="sd-field">
        <label for="bs-brief">Datum der Bestätigung *</label>
        <input id="bs-brief" class="bs-date" type="date" name="briefdatum" value="{{ old('briefdatum', $today) }}" required>
      </div>
    </div>
    <div class="sd-field">
      <label for="bs-personen">Meldepflichtige Personen * <span class="sd-muted" style="font-weight:400">(Vor- und Nachname, eine Person je Zeile)</span></label>
      <textarea id="bs-personen" name="personen" rows="4" required>{{ old('personen', $personen) }}</textarea>
    </div>
    <button class="sd-btn" type="submit">Bestätigung erstellen</button>
  </form>
</div>

<div class="sd-card">
  <h2>Mietschuldenfreiheitsbescheinigung</h2>
  <p class="sd-muted" style="margin-top:0">Bestätigt dem Mieter, dass bis zum Stichtag keine Mietschulden bestehen, etwa für einen neuen Vermieter. Das System prüft das Mieterkonto: Sind bis zum Stichtag noch Beträge offen, wird keine Bescheinigung erstellt.</p>
  @if ($offen > 0.004)
    <div class="rt-warn">Stand heute sind {{ \App\Support\Money::format($offen) }} fällig und offen. Erst nach Zahlung ist die Bescheinigung möglich.</div>
  @else
    <div class="rt-ok" style="margin-bottom:.8rem">Stand heute ist im Mieterkonto nichts offen.</div>
  @endif
  <p class="sd-muted">Hinweis: Mieten werden im System erst ab {{ $bsDe($erfasstAb) }} erfasst. Für die Zeit davor bitte selbst prüfen, ob alles bezahlt ist.</p>
  <form method="GET" action="{{ route('bescheinigung.mietschuldenfrei', $lease) }}" target="_blank" rel="noopener">
    <div class="sd-grid">
      <div class="sd-field">
        <label for="bs-stichtag">Stichtag *</label>
        <input id="bs-stichtag" class="bs-date" type="date" name="stichtag" value="{{ old('stichtag', $today) }}" max="{{ $today }}" required>
      </div>
      <div class="sd-field">
        <label for="bs-zweck">Zweck (freiwillig)</label>
        <input id="bs-zweck" type="text" name="zweck" maxlength="190" value="{{ old('zweck') }}" placeholder="z. B. zur Vorlage beim neuen Vermieter">
      </div>
    </div>
    <label class="sd-check" style="font-weight:400"><input type="checkbox" name="puenktlich" value="1" @checked(old('puenktlich'))> Zusätzlich bestätigen, dass die Miete stets pünktlich gezahlt wurde</label>
    <div style="margin-top:.8rem"><button class="sd-btn" type="submit">Bescheinigung erstellen</button></div>
  </form>
</div>
@endsection
