{{-- Finanzierung auf der Objektseite. Erwartet $property. --}}
@php
  try {
      $fk = \App\Support\Finance\Kennzahlen::property($property, (int) date('Y'));
  } catch (\Throwable $e) {
      report($e);
      $fk = null;
  }
  $M = fn ($v) => \App\Support\Money::format($v);
  $P = fn ($v) => $v === null ? '–' : number_format($v, 1, ',', '.').' %';
@endphp
@if ($fk)
@include('finance.styles')
<div class="sd-card" id="finanzierung">
  <h2>Finanzierung und Rendite</h2>
  <div class="fi-kpis" style="margin-bottom:.6rem">
    <div class="fi-kpi"><span class="k">Restschuld</span><span class="v">{{ $M($fk['debt']) }}</span><span class="s">{{ $fk['monthly_rate'] > 0 ? 'Rate '.$M($fk['monthly_rate']).' / Monat' : 'kein Darlehen' }}</span></div>
    <div class="fi-kpi"><span class="k">Cashflow {{ $fk['year'] }}</span><span class="v {{ $fk['cashflow'] >= 0 ? 'fi-pos' : 'fi-neg' }}">{{ $M($fk['cashflow']) }}</span><span class="s">{{ $M($fk['cashflow_month']) }} je Monat</span></div>
    <div class="fi-kpi"><span class="k">Rendite</span><span class="v">{{ $P($fk['gross_yield']) }}</span><span class="s">brutto · netto {{ $P($fk['net_yield']) }} · EK {{ $P($fk['equity_yield']) }}</span></div>
    <div class="fi-kpi"><span class="k">Wert</span><span class="v">{{ $fk['market'] !== null ? $M($fk['market']) : '–' }}</span><span class="s">{{ $fk['ltv'] !== null ? 'Beleihung '.$P($fk['ltv']).' · Vermögen '.$M($fk['net_worth']) : 'noch nicht eingetragen' }}</span></div>
  </div>
  @if ($fk['loans']->isNotEmpty())
    <ul style="margin:.2rem 0 .6rem;padding-left:1.1rem">
      @foreach ($fk['loans'] as $l)
        <li><a href="{{ route('loans.show', $l) }}">{{ $l->name }}</a>: {{ number_format((float) $l->interest_rate, 2, ',', '.') }} %, Restschuld {{ $M(\App\Support\Finance\LoanPlan::balanceOn($l, date('Y-m-d'))) }}{{ $l->fixed_until ? ', Zinsbindung bis '.$l->fixed_until->format('m/Y') : '' }}</li>
      @endforeach
    </ul>
  @endif
  <div class="sd-actions">
    <a class="sd-btn sec small" href="{{ route('loans.create', ['objekt' => $property->id]) }}">Darlehen anlegen</a>
    <a class="sd-btn sec small" href="{{ route('properties.values', $property) }}">Kaufpreis und Wert</a>
    <a class="sd-btn sec small" href="{{ route('afa.edit', $property) }}">AfA</a>
    <a class="sd-btn sec small" href="{{ route('finance.index') }}">Alle Objekte</a>
  </div>
</div>
@endif
