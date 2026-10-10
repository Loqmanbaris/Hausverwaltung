@extends('layouts.app')
@section('title', 'Mieteingang')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Rent\Mieten;
  $months = ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  $monthLabel = $months[(int) $month->format('n')].' '.$month->format('Y');
@endphp
<div class="sd-head">
  <h1>Mieteingang {{ $monthLabel }}</h1>
  <div class="sd-actions">
    @php($bankOpen = \App\Support\Bank\BankService::openCountFor(auth()->user()))
    <a class="sd-btn" href="{{ $bankOpen > 0 ? route('bank.transactions') : route('bank.index') }}">Kontoauszug{{ $bankOpen > 0 ? ' ('.$bankOpen.' zuzuordnen)' : ' einlesen' }}</a>
    <a class="sd-btn sec" href="{{ route('rent.open', array_filter(['eigentuemer' => $ownerId ?: null])) }}">Offene Posten</a>
    <a class="sd-btn sec" href="{{ route('leases.index') }}">Mietverträge</a>
  </div>
</div>

<form class="rt-filter" method="GET" action="{{ route('rent.month') }}">
  <a class="sd-btn sec small" href="{{ route('rent.month', array_filter(['monat' => $prev, 'eigentuemer' => $ownerId ?: null])) }}">‹ Vormonat</a>
  <input type="month" name="monat" value="{{ $month->format('Y-m') }}" max="{{ date('Y-m') }}" aria-label="Monat">
  @if ($owners->count() > 1)
    <select name="eigentuemer" aria-label="Eigentümer">
      <option value="">Alle Eigentümer</option>
      @foreach ($owners as $o)
        <option value="{{ $o->id }}" @selected($ownerId === (int) $o->id)>{{ $o->name }}</option>
      @endforeach
    </select>
  @endif
  <button class="sd-btn sec small" type="submit">Anzeigen</button>
  @if ($next)
    <a class="sd-btn sec small" href="{{ route('rent.month', array_filter(['monat' => $next, 'eigentuemer' => $ownerId ?: null])) }}">Folgemonat ›</a>
  @endif
</form>

<div class="rt-stats">
  <div class="rt-stat"><strong>{{ Money::format($totals['soll']) }}</strong><span>Soll</span></div>
  <div class="rt-stat"><strong class="rt-ok">{{ Money::format($totals['ist']) }}</strong><span>eingegangen</span></div>
  <div class="rt-stat"><strong @class(['rt-late' => $totals['offen'] > 0.004])>{{ Money::format($totals['offen']) }}</strong><span>offen</span></div>
</div>

<div class="sd-card">
  @if ($charges->isEmpty())
    <p class="sd-muted" style="margin:0">Für diesen Monat gibt es keine Soll-Mieten. Soll-Mieten entstehen aus den <a href="{{ route('leases.index') }}">Mietverträgen</a>.</p>
  @else
    <p class="sd-muted" style="margin-top:0">Ist eine Miete eingegangen, trägst du Datum und Betrag ein und klickst auf „Erfassen“. Ein Betrag, der höher ist als der offene Rest, wird automatisch mit dem nächsten Monat verrechnet.</p>
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Einheit und Mieter</th><th class="rt-num">Soll</th><th class="rt-num">Eingegangen</th><th>Stand</th><th>Zahlung erfassen</th></tr></thead>
        <tbody>
          @php($lastOwner = null)
          @foreach ($charges as $c)
            @php($ownerName = $c->lease?->owner?->name)
            @if ($owners->count() > 1 && ! $ownerId && $ownerName !== $lastOwner)
              <tr><td colspan="5" style="background:#f4f6f8;font-weight:700">{{ $ownerName }}</td></tr>
              @php($lastOwner = $ownerName)
            @endif
            <tr id="posten-{{ $c->id }}">
              <td><a href="{{ route('leases.show', $c->lease_id) }}">{{ $c->lease?->unit?->label }}</a>, {{ $c->lease?->unit?->property?->name }}<br>
                <span class="sd-muted">{{ $c->lease?->tenant?->display_name }} · fällig {{ $c->due_date->format('d.m.') }}@if ($c->isPartial()) · anteilig {{ $c->period_from->format('j.') }}–{{ $c->period_to->format('j.') }}@endif</span></td>
              <td class="rt-num" data-l="Soll">{{ Money::format($c->total) }}</td>
              <td class="rt-num" data-l="Eingegangen">{{ Money::format($c->paid_amount) }}</td>
              <td>
                @switch($c->state($today))
                  @case('bezahlt') <span class="rt-ok">bezahlt</span> @break
                  @case('ueberfaellig') <span class="rt-late">überfällig</span><br><span class="rt-nw">offen {{ Money::format($c->openAmount()) }}</span> @break
                  @case('teilweise') <span class="rt-part">teilweise bezahlt</span><br><span class="rt-nw">offen {{ Money::format($c->openAmount()) }}</span> @break
                  @case('kommt') <span class="rt-soon">noch nicht fällig</span> @break
                  @default <span class="rt-part">heute fällig</span>
                @endswitch
              </td>
              <td>
                @if ($c->openAmount() > 0.004)
                  <form class="rt-pay" method="POST" action="{{ route('rent.quick-pay') }}">
                    @csrf
                    <input type="hidden" name="charge_id" value="{{ $c->id }}">
                    <input type="hidden" name="eigentuemer" value="{{ $ownerId ?: '' }}">
                    <input type="hidden" name="method" value="ueberweisung">
                    <input type="date" name="paid_on" value="{{ $today }}" max="{{ $today }}" required aria-label="Zahldatum">
                    <input type="text" name="amount" inputmode="decimal" value="{{ Money::input($c->openAmount()) }}" required aria-label="Betrag">
                    <button class="sd-btn small" type="submit">Erfassen</button>
                  </form>
                @else
                  <span class="sd-muted">–</span>
                @endif
              </td>
            </tr>
          @endforeach
          <tr class="rt-sum"><td>Summe</td><td class="rt-num">{{ Money::format($totals['soll']) }}</td><td class="rt-num">{{ Money::format($totals['ist']) }}</td><td colspan="2">offen {{ Money::format($totals['offen']) }}</td></tr>
        </tbody>
      </table>
    </div>
  @endif
</div>
<p class="sd-muted" style="font-size:.9rem">Andere Zahlungsarten (bar, Lastschrift), Rückbuchungen und Notizen erfasst du im jeweiligen Mietvertrag unter „Zahlung erfassen“.</p>
<script>
(function () { var h = location.hash; if (h && h.indexOf('#posten-') === 0) { var r = document.querySelector(h); if (r) r.classList.add('rt-target'); } })();
</script>
@endsection
