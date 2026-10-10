@extends('layouts.app')
@section('title', $loan->name)
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('finance.styles')
@php
  $M = fn ($v) => \App\Support\Money::format($v);
  $today = date('Y-m-d');
  $curYear = (int) date('Y');
@endphp
<div class="sd-head">
  <h1>{{ $loan->name }} @if ($loan->status === 'getilgt')<span class="fi-tag grey">getilgt</span>@endif</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('finance.index') }}">Finanzierung</a>
    <a class="sd-btn" href="{{ route('loans.edit', $loan) }}">Bearbeiten</a>
  </div>
</div>

<div class="fi-kpis">
  <div class="fi-kpi"><span class="k">Restschuld heute</span><span class="v">{{ $M($balance) }}</span><span class="s">von {{ $M($loan->principal) }} ({{ $loan->principal > 0 ? number_format(100 - $balance / (float) $loan->principal * 100, 0, ',', '.') : 0 }} % getilgt)</span></div>
  <div class="fi-kpi"><span class="k">Nächste Rate</span><span class="v">{{ $next ? $M($next['payment']) : '–' }}</span><span class="s">{{ $next ? \Carbon\Carbon::parse($next['date'])->format('d.m.Y').' · Zins '.$M($next['interest']).', Tilgung '.$M($next['principal']) : 'keine weitere Rate' }}</span></div>
  <div class="fi-kpi"><span class="k">Zinsbindung</span><span class="v">{{ $loan->fixed_until?->format('m/Y') ?? '–' }}</span><span class="s">{{ $fixedBalance !== null ? 'Restschuld dann '.$M($fixedBalance) : 'nicht eingetragen' }}</span></div>
  <div class="fi-kpi"><span class="k">Schuldenfrei</span><span class="v">{{ $payoff ? \Carbon\Carbon::parse($payoff)->format('m/Y') : '–' }}</span><span class="s">{{ $payoff ? 'laut Plan bei gleichem Zins' : 'mit dieser Rate nicht absehbar' }}</span></div>
</div>

@if (count($years) > 1)
  @php
    $maxB = max((float) $loan->principal, 1);
    $W = 640; $H = 200; $L = 52; $B = 24; $T = 10;
    $n = count($years); $step = ($W - $L - 8) / max(1, $n - 1);
    $y = fn ($v) => $T + ($H - $B - $T) * (1 - $v / $maxB);
    $pts = []; foreach ($years as $i => $r) { $pts[] = round($L + $i * $step, 1).','.round($y($r['balance']), 1); }
    $fixedY = $loan->fixed_until ? (int) $loan->fixed_until->format('Y') : null;
    $labelEvery = max(1, (int) ceil($n / 10));
  @endphp
  <div class="sd-card">
    <h2>Restschuld im Verlauf</h2>
    <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%;height:auto;display:block" role="img" aria-label="Restschuld je Jahresende">
      @foreach ([0, 0.5, 1] as $f)
        <line x1="{{ $L }}" x2="{{ $W - 4 }}" y1="{{ $y($maxB * $f) }}" y2="{{ $y($maxB * $f) }}" stroke="#eef0f3"/>
        <text x="{{ $L - 6 }}" y="{{ $y($maxB * $f) + 4 }}" text-anchor="end" font-size="11" fill="#9ca3af">{{ number_format($maxB * $f / 1000, 0, ',', '.') }} T€</text>
      @endforeach
      <polygon points="{{ $L }},{{ $y(0) }} {{ implode(' ', $pts) }} {{ round($L + ($n - 1) * $step, 1) }},{{ $y(0) }}" fill="var(--brand)" opacity=".12"/>
      <polyline points="{{ implode(' ', $pts) }}" fill="none" stroke="var(--brand)" stroke-width="2.5"/>
      @foreach ($years as $i => $r)
        @if ($r['year'] === $curYear)<circle cx="{{ $L + $i * $step }}" cy="{{ $y($r['balance']) }}" r="4.5" fill="var(--ink)"/>@endif
        @if ($fixedY && $r['year'] === $fixedY)<line x1="{{ $L + $i * $step }}" x2="{{ $L + $i * $step }}" y1="{{ $T }}" y2="{{ $H - $B }}" stroke="#b54708" stroke-dasharray="4 3"/><text x="{{ $L + $i * $step + 4 }}" y="{{ $T + 10 }}" font-size="11" fill="#b54708">Zinsbindung</text>@endif
        @if ($i % $labelEvery === 0 || $i === $n - 1)<text x="{{ $L + $i * $step }}" y="{{ $H - 6 }}" text-anchor="{{ $i === $n - 1 ? 'end' : ($i === 0 ? 'start' : 'middle') }}" font-size="11" fill="#6b7280">{{ $r['year'] }}</text>@endif
      @endforeach
    </svg>
  </div>
@endif

<div class="fi-grid">
  <div class="sd-card">
    <h2>Angaben</h2>
    <dl class="fi-dl">
      <dt>Art</dt><dd>{{ $loan->kindLabel() }}</dd>
      <dt>Darlehensnehmer</dt><dd>{{ $loan->owner?->name }}</dd>
      <dt>Objekt</dt><dd>@if ($loan->property)<a href="{{ route('properties.show', $loan->property) }}#finanzierung">{{ $loan->property->name }}</a>@else – @endif</dd>
      <dt>Bank</dt><dd>{{ $loan->lender ?: '–' }}{{ $loan->account_no ? ', Nr. '.$loan->account_no : '' }}</dd>
      <dt>Betrag</dt><dd>{{ $M($loan->principal) }}{{ $loan->paid_out_on ? ', ausgezahlt '.$loan->paid_out_on->format('d.m.Y') : '' }}</dd>
      <dt>Zins</dt><dd>{{ number_format((float) $loan->interest_rate, 3, ',', '.') }} % p. a.</dd>
      <dt>Rate</dt><dd>{{ $installment > 0 ? $M($installment).' '.$loan->intervalLabel().($loan->kind === 'tilgung' ? ' Tilgung + Zinsen' : '') : 'nur Zinsen' }}{{ $loan->initial_repayment !== null ? ' (anfängl. Tilgung '.rtrim(rtrim(number_format((float) $loan->initial_repayment, 3, ',', ''), '0'), ',').' %)' : '' }}</dd>
      <dt>Erste Rate</dt><dd>{{ $loan->first_payment_on->format('d.m.Y') }}</dd>
      @if ($loan->free_until)<dt>Tilgungsfrei bis</dt><dd>{{ $loan->free_until->format('d.m.Y') }}</dd>@endif
      @if ($loan->end_date)<dt>Rückzahlung</dt><dd>{{ $loan->end_date->format('d.m.Y') }}</dd>@endif
      @if ($loan->special_repayment_pct)<dt>Sondertilgung</dt><dd>bis {{ rtrim(rtrim(number_format((float) $loan->special_repayment_pct, 2, ',', ''), '0'), ',') }} % pro Jahr = {{ $M((float) $loan->principal * (float) $loan->special_repayment_pct / 100) }}</dd>@endif
      @if ($loan->note)<dt>Notiz</dt><dd style="white-space:pre-line">{{ $loan->note }}</dd>@endif
    </dl>
  </div>

  <div class="sd-card" id="ereignisse">
    <h2>Zahlungen und Änderungen</h2>
    @php $evs = $loan->events->sortByDesc(fn ($e) => $e->date->format('Y-m-d').'-'.$e->id); @endphp
    @if ($evs->isEmpty())
      <p class="sd-muted">Noch nichts eingetragen. Raten aus den Kontoauszügen werden automatisch zugeordnet, wenn Darlehensnummer oder IBAN der Bank und Betrag passen.</p>
    @else
      <table class="fi-table">
        @foreach ($evs->take(15) as $e)
          <tr>
            <td style="white-space:nowrap">{{ $e->date->format('d.m.Y') }}</td>
            <td>{{ $e->typeLabel() }}@if ($e->bank_transaction_id) <span class="fi-tag ok">Konto</span>@endif
              @if ($e->type === 'jahreszinsen')<div class="sd-muted" style="font-size:.82rem">für {{ $e->date->format('Y') }}</div>@endif
              @if ($e->note)<div class="sd-muted" style="font-size:.82rem">{{ \Illuminate\Support\Str::limit($e->note, 70) }}</div>@endif</td>
            <td class="n">{{ $e->amount !== null ? $M($e->amount) : '' }}{{ $e->new_rate !== null ? number_format((float) $e->new_rate, 3, ',', '.').' %' : '' }}{{ $e->new_installment !== null ? ' · Rate '.$M($e->new_installment) : '' }}</td>
            <td class="n"><form method="POST" action="{{ route('loans.events.destroy', [$loan, $e]) }}" onsubmit="return confirm('Eintrag entfernen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit" aria-label="Eintrag entfernen">×</button></form></td>
          </tr>
        @endforeach
      </table>
      @if ($evs->count() > 15)<p class="sd-muted" style="font-size:.85rem">und {{ $evs->count() - 15 }} ältere</p>@endif
    @endif
    <details style="margin-top:.8rem" @if ($errors->any()) open @endif>
      <summary style="cursor:pointer;font-weight:700">+ Eintragen</summary>
      <form method="POST" action="{{ route('loans.events.store', $loan) }}" class="fi-form" style="margin-top:.6rem">
        @csrf
        <div><label for="e-type">Was?</label>
          <select id="e-type" name="type">
            <option value="sondertilgung" @selected(old('type') === 'sondertilgung')>Sondertilgung</option>
            <option value="zinsaenderung" @selected(old('type') === 'zinsaenderung')>Neuer Zinssatz / neue Rate (Anschlussfinanzierung)</option>
            <option value="saldo" @selected(old('type') === 'saldo')>Restschuld laut Bank (Korrektur)</option>
            <option value="jahreszinsen" @selected(old('type') === 'jahreszinsen')>Zinsen im Jahr laut Bank (für die Anlage V)</option>
            <option value="zahlung" @selected(old('type') === 'zahlung')>Zahlung ohne Kontoauszug (z. B. bar)</option>
          </select></div>
        <div data-ev="sondertilgung zinsaenderung saldo zahlung"><label for="e-date">Datum</label><input id="e-date" type="date" name="date" value="{{ old('date', $today) }}"></div>
        <div data-ev="jahreszinsen"><label for="e-year">Jahr</label><input id="e-year" type="number" name="year" min="2000" max="2100" value="{{ old('year', $curYear - 1) }}"></div>
        <div data-ev="sondertilgung saldo jahreszinsen zahlung"><label for="e-amount">Betrag</label><input id="e-amount" name="amount" inputmode="decimal" value="{{ old('amount') }}"></div>
        <div data-ev="zinsaenderung"><label for="e-rate">Neuer Zinssatz in %</label><input id="e-rate" name="new_rate" inputmode="decimal" value="{{ old('new_rate') }}"></div>
        <div data-ev="zinsaenderung"><label for="e-inst">Neue Rate</label><input id="e-inst" name="new_installment" inputmode="decimal" value="{{ old('new_installment') }}"></div>
        <div style="grid-column:1/-1"><label for="e-note">Notiz</label><input id="e-note" name="note" maxlength="190" value="{{ old('note') }}"></div>
        <div><button class="sd-btn" type="submit">Eintragen</button></div>
      </form>
    </details>
  </div>
</div>

<div class="sd-card" style="margin-top:1rem">
  <h2>Tilgungsplan je Jahr</h2>
  <div class="sd-table-wrap">
    <table class="fi-table">
      <thead><tr><th>Jahr</th><th class="n">Zahlungen laut Plan</th><th class="n">davon Zinsen</th><th class="n">davon Tilgung</th><th class="n">Restschuld am Jahresende</th><th class="n">laut Konto gezahlt</th></tr></thead>
      <tbody>
        @foreach ($years as $r)
          @php $int = \App\Support\Finance\LoanPlan::interestForYear($loan, $r['year']); @endphp
          <tr @class(['hl' => $r['year'] === $curYear])>
            <td>{{ $r['year'] }}@if ($loan->fixed_until && (int) $loan->fixed_until->format('Y') === $r['year']) <span class="fi-tag warn">Ende Zinsbindung</span>@endif</td>
            <td class="n">{{ $M($r['payment']) }}</td>
            <td class="n">{{ $M($int['amount']) }}@if ($int['source'] === 'bank')<div class="sd-muted" style="font-size:.78rem">laut Bank</div>@endif</td>
            <td class="n">{{ $M($r['principal']) }}</td>
            <td class="n">{{ $M($r['balance']) }}</td>
            <td class="n">{{ isset($paid[$r['year']]) ? $M($paid[$r['year']]) : ($r['year'] <= $curYear ? '–' : '') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <p class="sd-muted" style="font-size:.85rem;margin:.6rem 0 0">Der Plan rechnet mit dem eingetragenen Zinssatz bis zur Volltilgung. Nach der Zinsbindung gilt der neue Zins – trage ihn dann unter „Eintragen“ ein. Für die Steuer gilt der Jahreskontoauszug der Bank: Trage die Zinsen dort als „Zinsen im Jahr laut Bank“ ein, dann nimmt die Anlage V diesen Wert.</p>
</div>
<script>
(function () {
  var t = document.getElementById('e-type');
  function upd() { document.querySelectorAll('[data-ev]').forEach(function (el) { el.classList.toggle('fi-hide', el.getAttribute('data-ev').split(' ').indexOf(t.value) < 0); }); }
  t.addEventListener('change', upd); upd();
})();
</script>
@endsection
