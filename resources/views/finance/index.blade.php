@extends('layouts.app')
@section('title', 'Finanzierung')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('finance.styles')
@php
  $M = fn ($v) => \App\Support\Money::format($v);
  $P = fn ($v) => $v === null ? '–' : number_format($v, 1, ',', '.').' %';
  $t = $data['totals'];
@endphp
<div class="sd-head">
  <h1>Finanzierung</h1>
  <div class="sd-actions">
    <form method="POST" action="{{ route('finance.match') }}" style="display:inline">@csrf<button class="sd-btn sec" type="submit">Kontoauszüge abgleichen</button></form>
    <a class="sd-btn" href="{{ route('loans.create') }}">Darlehen anlegen</a>
  </div>
</div>

<form method="GET" action="{{ route('finance.index') }}" class="sd-actions" style="margin-bottom:1rem">
  @if ($owners->count() > 1)
    <select name="eigentuemer" style="width:auto" onchange="this.form.submit()" aria-label="Eigentümer"><option value="">Alle Eigentümer</option>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected($ownerId === (int) $o->id)>{{ $o->name }}</option>@endforeach</select>
  @endif
  <select name="jahr" style="width:auto" onchange="this.form.submit()" aria-label="Jahr">@for ($y = (int) date('Y'); $y >= (int) date('Y') - 6; $y--)<option value="{{ $y }}" @selected($year === $y)>Jahr {{ $y }}</option>@endfor</select>
  <noscript><button class="sd-btn sec small" type="submit">Anzeigen</button></noscript>
</form>

<div class="fi-kpis">
  <div class="fi-kpi"><span class="k">Restschuld heute</span><span class="v">{{ $M($t['debt']) }}</span><span class="s">alle Darlehen</span></div>
  <div class="fi-kpi"><span class="k">Raten pro Monat</span><span class="v">{{ $M($t['monthly_rate']) }}</span><span class="s">Zins und Tilgung</span></div>
  <div class="fi-kpi"><span class="k">Cashflow {{ $year }}</span><span class="v {{ $t['cashflow'] >= 0 ? 'fi-pos' : 'fi-neg' }}">{{ $M($t['cashflow']) }}</span><span class="s">Einnahmen − Ausgaben − Raten</span></div>
  <div class="fi-kpi"><span class="k">Wert / Vermögen</span><span class="v">{{ $t['market'] !== null ? $M($t['net_worth']) : '–' }}</span><span class="s">{{ $t['market'] !== null ? 'Wert '.$M($t['market']).' − Restschuld' : 'Werte bei den Objekten eintragen' }}</span></div>
</div>

@if ($ending->isNotEmpty())
  <div class="sd-card" style="border-left:4px solid #b54708">
    <h2>Zinsbindung endet bald</h2>
    @foreach ($ending as $l)
      <p style="margin:.2rem 0"><a href="{{ route('loans.show', $l) }}"><strong>{{ $l->name }}</strong></a>{{ $l->property ? ' ('.$l->property->name.')' : '' }}: Zinsbindung bis {{ $l->fixed_until->format('d.m.Y') }}, Restschuld dann etwa {{ $M(\App\Support\Finance\LoanPlan::balanceOn($l, $l->fixed_until->format('Y-m-d'))) }}. Rechtzeitig Angebote für die Anschlussfinanzierung einholen (Forward-Darlehen bis zu 5 Jahre vorher möglich).</p>
    @endforeach
  </div>
@endif

<div class="sd-card">
  <h2>Objekte</h2>
  <div class="sd-table-wrap">
    <table class="fi-table">
      <thead><tr><th>Objekt</th><th class="n">Restschuld</th><th class="n">Rate/Monat</th><th>Zinsbindung</th><th class="n">Cashflow {{ $year }}</th><th class="n">Brutto&shy;rendite</th><th class="n">Netto&shy;rendite</th><th class="n">EK-Rendite</th><th class="n">Beleihung</th><th></th></tr></thead>
      <tbody>
        @foreach ($data['rows'] as $r)
          <tr>
            <td><a href="{{ route('properties.show', $r['property']) }}#finanzierung">{{ $r['property']->name }}</a><div class="sd-muted" style="font-size:.82rem">{{ $r['loans']->count() }} {{ $r['loans']->count() === 1 ? 'Darlehen' : 'Darlehen' }}{{ $r['jnkm'] > 0 ? ' · Kaltmiete '.$M($r['jnkm']).'/Jahr' : '' }}</div></td>
            <td class="n">{{ $r['debt'] > 0 ? $M($r['debt']) : '–' }}</td>
            <td class="n">{{ $r['monthly_rate'] > 0 ? $M($r['monthly_rate']) : '–' }}</td>
            <td>@if ($r['fixed_next'])<span @class(['fi-tag', 'warn' => $r['fixed_next']->fixed_until->format('Y-m-d') <= date('Y-m-d', strtotime('+18 months')), 'grey' => $r['fixed_next']->fixed_until->format('Y-m-d') > date('Y-m-d', strtotime('+18 months'))])>bis {{ $r['fixed_next']->fixed_until->format('m/Y') }}</span>@else – @endif</td>
            <td class="n {{ $r['cashflow'] >= 0 ? 'fi-pos' : 'fi-neg' }}">{{ $M($r['cashflow']) }}</td>
            <td class="n">{{ $P($r['gross_yield']) }}</td>
            <td class="n">{{ $P($r['net_yield']) }}</td>
            <td class="n">{{ $P($r['equity_yield']) }}</td>
            <td class="n">{{ $P($r['ltv']) }}</td>
            <td class="n"><a class="sd-btn sec small" href="{{ route('properties.values', $r['property']) }}">Werte</a></td>
          </tr>
        @endforeach
        <tr class="sum"><td>Gesamt</td><td class="n">{{ $M($t['debt']) }}</td><td class="n">{{ $M($t['monthly_rate']) }}</td><td></td><td class="n">{{ $M($t['cashflow']) }}</td><td colspan="5"></td></tr>
      </tbody>
    </table>
  </div>
  <p class="sd-muted" style="font-size:.85rem;margin:.7rem 0 0">Rendite braucht Kaufpreis und Kaufnebenkosten, die Beleihung einen geschätzten Wert (Knopf „Werte“). Cashflow: Mieteingänge und Ausgaben laut Kontoauszügen und Belegen im Jahr, Darlehensraten laut Tilgungsplan.</p>
</div>

<div class="sd-card">
  <h2>Darlehen</h2>
  @if ($loans->isEmpty())
    <p class="sd-muted" style="margin:0">Noch kein Darlehen angelegt. <a href="{{ route('loans.create') }}">Jetzt anlegen</a></p>
  @else
    <div class="sd-table-wrap">
      <table class="fi-table">
        <thead><tr><th>Darlehen</th><th>Objekt</th><th class="n">Zins</th><th class="n">Rate</th><th class="n">Restschuld</th><th>Zinsbindung</th><th>Getilgt</th></tr></thead>
        <tbody>
          @foreach ($loans as $l)
            @php $bal = \App\Support\Finance\LoanPlan::balanceOn($l, date('Y-m-d')); @endphp
            @php $pay = \App\Support\Finance\LoanPlan::payoffDate($l); @endphp
            <tr @if ($l->status === 'getilgt') style="opacity:.6" @endif>
              <td><a href="{{ route('loans.show', $l) }}"><strong>{{ $l->name }}</strong></a><div class="sd-muted" style="font-size:.82rem">{{ $l->lender }}{{ $l->lender ? ' · ' : '' }}{{ $l->kindLabel() }}</div></td>
              <td>{{ $l->property?->name ?? 'Eigentümer: '.$l->owner?->name }}</td>
              <td class="n">{{ number_format((float) $l->interest_rate, 2, ',', '.') }} %</td>
              <td class="n">{{ $M(\App\Support\Finance\LoanPlan::next($l)['payment'] ?? 0) }}<div class="sd-muted" style="font-size:.8rem">{{ $l->intervalLabel() }}</div></td>
              <td class="n">{{ $M($bal) }}</td>
              <td>{{ $l->fixed_until?->format('d.m.Y') ?? '–' }}</td>
              <td>{{ $l->status === 'getilgt' ? 'getilgt' : ($pay ? 'voraussichtlich '.\Carbon\Carbon::parse($pay)->format('m/Y') : 'nicht absehbar') }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
