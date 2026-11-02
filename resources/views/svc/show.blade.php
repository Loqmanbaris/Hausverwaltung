@extends('layouts.app')
@section('title', 'Vertrag')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $de = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '–';
  $box = 'padding:.4rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit';
@endphp
<div class="sd-head">
  <h1>{{ $c->title }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('svc.edit', $c) }}">Bearbeiten</a><a class="sd-btn sec" href="{{ route('svc.index') }}">Alle Verträge</a></div>
</div>
<p class="sd-muted">{{ $c->kindLabel() }} · {{ $c->place() }} · <strong>{{ $c->statusLabel() }}</strong></p>
@if ($errors->has('contract'))<div class="sd-err">{{ $errors->first('contract') }}</div>@endif

<div class="sd-card">
  <h2>Kündigung</h2>
  <p style="margin:0 0 .4rem"><strong>{{ $info['label'] }}</strong></p>
  @if ($info['missed_end'])<p class="sd-muted" style="margin:0 0 .4rem">Hinweis: Zum {{ $de($info['missed_end']) }} war eine Kündigung nicht mehr rechtzeitig, der Vertrag hat sich verlängert.</p>@endif
  @if ($c->status === 'gekuendigt')<p class="sd-muted" style="margin:0">Gekündigt am {{ $de($c->cancelled_on) }}, endet am {{ $de($c->cancel_effective) }}.</p>@endif
  @if ($c->status === 'aktiv')
    <div style="display:flex;flex-wrap:wrap;gap:1rem;margin-top:.6rem">
      <form method="POST" action="{{ route('svc.cancel', $c) }}" style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:flex-end">
        @csrf
        <div class="sd-field" style="margin:0"><label for="f-co" class="sd-muted">Gekündigt am</label><input id="f-co" type="date" name="cancelled_on" value="{{ date('Y-m-d') }}" style="{{ $box }}"></div>
        <div class="sd-field" style="margin:0"><label for="f-ce" class="sd-muted">Endet am</label><input id="f-ce" type="date" name="cancel_effective" value="{{ $info['next_end'] }}" style="{{ $box }}"></div>
        <button class="sd-btn" type="submit">Kündigung vermerken</button>
      </form>
      <form method="POST" action="{{ route('svc.renew', $c) }}" style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:flex-end">
        @csrf
        <div class="sd-field" style="margin:0"><label for="f-te" class="sd-muted">Neues Laufzeitende</label><input id="f-te" type="date" name="term_end" required style="{{ $box }}"></div>
        <button class="sd-btn sec" type="submit">Laufzeitende ändern</button>
      </form>
    </div>
    <p class="sd-muted" style="margin:.6rem 0 0">Die Kündigung musst du selbst an den Anbieter schicken. Das CRM vermerkt sie nur und beendet die Erinnerungen. Prüfe den Termin bitte gegen den Vertrag.</p>
  @else
    <div style="display:flex;gap:.5rem;margin-top:.6rem;flex-wrap:wrap">
      <form method="POST" action="{{ route('svc.reopen', $c) }}">@csrf<button class="sd-btn sec small" type="submit">Läuft doch weiter</button></form>
      @if ($c->status === 'gekuendigt')<form method="POST" action="{{ route('svc.end', $c) }}">@csrf<button class="sd-btn sec small" type="submit">Als beendet eintragen</button></form>@endif
    </div>
  @endif
</div>

<div class="sd-card">
  <dl class="sd-dl">
    <dt>Anbieter</dt><dd>{{ $c->provider ?: '–' }}</dd>
    <dt>Vertragsnummer</dt><dd>{{ $c->contract_no ?: '–' }}</dd>
    <dt>Kundennummer</dt><dd>{{ $c->customer_no ?: '–' }}</dd>
    <dt>Zählernummer</dt><dd>{{ $c->meter_no ?: '–' }}</dd>
    <dt>Vertragsbeginn</dt><dd>{{ $de($c->start_date) }}</dd>
    <dt>Laufzeitende</dt><dd>{{ $de($c->term_end) }}{{ $c->auto_renew ? ' (verlängert sich um '.$c->renew_months.' Monate)' : '' }}</dd>
    <dt>Kündigungsfrist</dt><dd>{{ $c->noticeLabel() }}</dd>
    <dt>Kosten</dt><dd>{{ $c->amount !== null ? $eur($c->amount).' '.$c->intervalLabel().' = '.$eur($c->annualCost()).' im Jahr' : '–' }}</dd>
    <dt>Kostenart</dt><dd>{{ $c->costType ? $c->costType->label.($c->costType->allocatable ? ' (umlagefähig)' : '') : '–' }}</dd>
    <dt>Ansprechpartner</dt><dd>{{ $c->contact ?: '–' }}</dd>
  </dl>
  @if ($c->note)<p style="white-space:pre-line;margin:.8rem 0 0" class="sd-muted">{{ $c->note }}</p>@endif
</div>

<div class="sd-card" id="dokumente">
  <h2>Dokumente</h2>
  @include('documents.panel', ['docType' => 'contract', 'docId' => $c->id])
</div>

<form method="POST" action="{{ route('svc.destroy', $c) }}" onsubmit="return confirm('Den Vertrag endgültig löschen?')" style="margin-top:1rem">
  @csrf @method('DELETE')
  <button class="sd-btn sec small" type="submit">Vertrag löschen</button>
</form>
@endsection
