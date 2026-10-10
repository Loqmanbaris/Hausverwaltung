@extends('layouts.app')
@section('title', 'Ratenvereinbarung')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php use App\Support\Money; use App\Models\InstallmentPlan; @endphp
<div class="sd-head">
  <h1>Ratenvereinbarung</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('leases.show', $plan->lease_id) }}#zinsen">Zum Mietvertrag</a>
    <a class="sd-btn" href="{{ route('installments.print', $plan) }}" target="_blank" rel="noopener">Drucken</a>
    <a class="sd-btn sec" href="{{ route('documents.pdf', ['art' => 'raten', 'id' => $plan->id]) }}">PDF</a>
    <a class="sd-btn" href="{{ route('mail.compose', ['art' => 'raten', 'ids' => $plan->id]) }}">Per E-Mail senden</a>
  </div>
</div>
<p class="sd-muted">{{ $plan->lease?->unit?->label }}, {{ $plan->lease?->unit?->property?->name }} · {{ $plan->lease?->tenant?->display_name }} · vereinbart am {{ $plan->agreed_on->format('d.m.Y') }} über {{ Money::format($plan->debt) }}</p>

<div class="rt-stats">
  <div class="rt-stat"><strong>{{ $plan->statusLabel() }}</strong><span>Status</span></div>
  <div class="rt-stat"><strong @class(['rt-ok' => $state['ok'], 'rt-late' => ! $state['ok']])>{{ $state['ok'] ? 'eingehalten' : 'im Rückstand' }}</strong><span>laut Kontostand</span></div>
  <div class="rt-stat"><strong>{{ Money::format($state['overdue']) }}</strong><span>überfällig heute</span></div>
  <div class="rt-stat"><strong>{{ Money::format($state['allowed']) }}</strong><span>laut Plan erlaubt</span></div>
</div>

<div class="sd-card">
  <h2>Raten</h2>
  <table class="sd" style="max-width:34rem">
    <thead><tr><th>Rate</th><th>fällig am</th><th class="rt-num">Betrag</th><th>Stand</th></tr></thead>
    <tbody>
      @foreach ($state['items'] as $i)
        <tr>
          <td>{{ $i['no'] }}.</td>
          <td>{{ $i['due']->format('d.m.Y') }}</td>
          <td class="rt-num">{{ Money::format($i['amount']) }}</td>
          <td @class(['rt-ok' => $i['state'] === 'bezahlt', 'rt-late' => $i['state'] === 'offen'])>{{ ['bezahlt' => 'bezahlt', 'offen' => 'offen', 'kommt' => 'noch nicht fällig'][$i['state']] }}</td>
        </tr>
      @endforeach
    </tbody>
  </table>
  <p class="sd-muted" style="margin:.6rem 0 0">Die Zahlungen werden im Mieterkonto immer auf die älteste offene Forderung angerechnet. Der Stand der Raten ergibt sich daher aus dem Kontostand: Rückstand bei Abschluss minus heute noch überfälliger Betrag.</p>
  @if ($plan->note)<p style="white-space:pre-line">{{ $plan->note }}</p>@endif
</div>

<div class="sd-card">
  <h2>Status ändern</h2>
  <div style="display:flex;flex-wrap:wrap;gap:.5rem">
    @foreach (['erfuellt' => 'Als erfüllt markieren', 'geplatzt' => 'Nicht eingehalten', 'storniert' => 'Stornieren', 'aktiv' => 'Wieder aktiv'] as $st => $label)
      @continue($plan->status === $st)
      <form method="POST" action="{{ route('installments.status', $plan) }}" class="sd-inline">@csrf<input type="hidden" name="status" value="{{ $st }}"><button class="sd-btn sec small" type="submit">{{ $label }}</button></form>
    @endforeach
  </div>
  <p class="sd-muted" style="margin:.6rem 0 0">Solange die Vereinbarung läuft und eingehalten wird, schlägt das Mahnwesen keine Mahnung vor. Erfüllte Vereinbarungen werden täglich automatisch abgeschlossen.</p>
</div>
@endsection
