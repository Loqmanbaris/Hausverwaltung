@extends('layouts.app')
@section('title', 'Jahresbericht')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $sel = 'padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit';
@endphp
<div class="sd-head"><h1>Jahresbericht</h1></div>
<p class="sd-muted">Ein Bericht je Eigentümer und Jahr für Gespräche mit dem Eigentümer: Ergebnis, Vermietung, Mieteingang, Nebenkosten, Darlehen und Instandhaltung. Die Zahlen kommen aus den Daten im CRM. Sie ersetzen keine Steuererklärung.</p>
<form method="GET" class="sd-card" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end">
  <div class="sd-field" style="margin:0"><label for="f-eig">Eigentümer</label>
    <select id="f-eig" name="eigentuemer" style="{{ $sel }}"><option value="">– wählen –</option>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected($owner && $owner->id === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
  <div class="sd-field" style="margin:0"><label for="f-jahr">Jahr</label>
    <select id="f-jahr" name="jahr" style="{{ $sel }}">@for ($y = (int) date('Y'); $y >= (int) date('Y') - 6; $y--)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endfor</select></div>
  <button class="sd-btn" type="submit">Anzeigen</button>
</form>

@if ($data)
  <div class="sd-card">
    <h2>{{ $owner->name }} · {{ $year }}</h2>
    @unless ($data['is_final'])<p class="sd-muted" style="margin-top:0">Das Jahr ist noch nicht zu Ende. Der Bericht zeigt den Stand bis {{ \Carbon\Carbon::parse($data['stichtag'])->format('d.m.Y') }}.</p>@endunless
    <dl class="sd-dl">
      <dt>Einnahmen</dt><dd>{{ $eur($data['summary']['income']) }}</dd>
      <dt>Ausgaben</dt><dd>{{ $eur($data['summary']['costs']) }}</dd>
      <dt>Ergebnis</dt><dd><strong>{{ $eur($data['summary']['result']) }}</strong></dd>
      <dt>Vermietet</dt><dd>{{ $data['letting_total']['rented'] }} von {{ $data['letting_total']['units'] }} Einheiten</dd>
      <dt>Offene Posten</dt><dd>{{ $eur($data['rent']['open']) }}@if ($data['rent']['open_count'] > 0) ({{ $data['rent']['open_count'] }} Verträge)@endif</dd>
    </dl>
    <p style="margin:.8rem 0 0;display:flex;flex-wrap:wrap;gap:.5rem">
      <a class="sd-btn" href="{{ route('report.print', ['eigentuemer' => $owner->id, 'jahr' => $year]) }}" target="_blank" rel="noopener">Bericht ansehen und drucken</a>
      <a class="sd-btn sec" href="{{ route('report.pdf', ['eigentuemer' => $owner->id, 'jahr' => $year]) }}">PDF herunterladen</a>
      <a class="sd-btn sec" href="{{ route('mail.compose', ['art' => 'jahresbericht', 'ids' => $owner->id.','.$year]) }}">Per E-Mail senden</a>
    </p>
  </div>
@endif
@endsection
