@extends('layouts.app')
@section('title', 'Ratenvereinbarung')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php use App\Support\Money; @endphp
<style>.pf-in{padding:.45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;width:100%}</style>
<div class="sd-head">
  <h1>Ratenvereinbarung</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('leases.show', $lease) }}#zinsen">Zum Mietvertrag</a></div>
</div>
<p class="sd-muted">{{ $lease->unit?->label }}, {{ $lease->unit?->property?->name }} · {{ $lease->tenant?->display_name }}. Überfälliger Rückstand heute: <strong class="rt-late">{{ Money::format($overdue) }}</strong>. Die laufende Miete ist neben den Raten weiter pünktlich zu zahlen.</p>
@if ($active)
  <div class="sd-err">Es läuft schon eine <a href="{{ route('installments.show', $active) }}">Ratenvereinbarung vom {{ $active->agreed_on->format('d.m.Y') }}</a>. Bitte diese zuerst abschließen oder stornieren.</div>
@endif

<div class="sd-card">
  <h2>1. Raten berechnen</h2>
  <form method="GET" action="{{ route('installments.create', $lease) }}">
    <div class="sd-grid">
      <div class="sd-field"><label for="pf-b">Betrag (€)</label><input class="pf-in" id="pf-b" name="betrag" inputmode="decimal" value="{{ Money::input($debt) }}"><small class="sd-muted">Vorschlag: überfälliger Rückstand. Gebuchte Verzugszinsen und Mahngebühren sind darin enthalten.</small></div>
      <div class="sd-field"><label for="pf-a">Anzahl Raten</label><input class="pf-in" id="pf-a" name="anzahl" inputmode="numeric" value="{{ $count }}"><small class="sd-muted">oder rechts die Höhe einer Rate</small></div>
      <div class="sd-field"><label for="pf-r">Höhe einer Rate (€)</label><input class="pf-in" id="pf-r" name="rate" inputmode="decimal" value="{{ $rate !== null ? Money::input($rate) : '' }}"></div>
      <div class="sd-field"><label for="pf-e">Erste Rate fällig am</label><input class="pf-in" id="pf-e" type="date" name="erste" value="{{ $first }}"><small class="sd-muted">Weitere Raten jeweils am gleichen Tag der Folgemonate</small></div>
    </div>
    <button class="sd-btn sec" type="submit">Berechnen</button>
  </form>
</div>

@if ($error)
  <div class="sd-err">{{ $error }}</div>
@elseif ($schedule)
  <div class="sd-card">
    <h2>2. Plan prüfen und speichern</h2>
    <table class="sd" style="max-width:30rem">
      <thead><tr><th>Rate</th><th>fällig am</th><th class="rt-num">Betrag</th></tr></thead>
      <tbody>
        @foreach ($schedule as $s)
          <tr><td>{{ $s['no'] }}.</td><td>{{ \Carbon\Carbon::parse($s['due'])->format('d.m.Y') }}</td><td class="rt-num">{{ Money::format($s['amount']) }}</td></tr>
        @endforeach
        <tr><td colspan="2"><strong>Summe</strong></td><td class="rt-num"><strong>{{ Money::format(collect($schedule)->sum(fn ($s) => (float) $s['amount'])) }}</strong></td></tr>
      </tbody>
    </table>
    <form method="POST" action="{{ route('installments.store', $lease) }}" style="margin-top:.8rem">
      @csrf
      <input type="hidden" name="debt" value="{{ $debt }}">
      <input type="hidden" name="count" value="{{ $rate === null ? $count : '' }}">
      <input type="hidden" name="rate" value="{{ $rate ?? '' }}">
      <input type="hidden" name="first" value="{{ $first }}">
      <div class="sd-field"><label for="pf-n">Zusätzliche Vereinbarungen (erscheinen im Schreiben)</label><textarea id="pf-n" name="note" rows="3" placeholder="z. B. Bei Verzug mit einer Rate wird der gesamte Restbetrag sofort fällig."></textarea></div>
      <button class="sd-btn" type="submit" @disabled($active)>Ratenvereinbarung speichern</button>
    </form>
  </div>
@endif
@endsection
