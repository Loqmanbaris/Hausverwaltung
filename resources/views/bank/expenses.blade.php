@extends('layouts.app')
@section('title', 'Ausgaben abgleichen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Bank\ExpenseMatcher;
  $tabs = ['offen' => 'Ohne Beleg', 'zugeordnet' => 'Mit Beleg', 'kein_beleg' => 'Kein Beleg nötig', 'darlehen' => 'Darlehen', 'alle' => 'Alle'];
  $tabCount = ['offen' => $counts['ausgabe'] ?? 0, 'zugeordnet' => $counts['beleg'] ?? 0, 'kein_beleg' => $counts['kein_beleg'] ?? 0, 'darlehen' => $counts['darlehen'] ?? 0, 'alle' => $counts->sum()];
  $sureIds = collect($rows)->filter(fn ($r) => $r['sure'])->map(fn ($r) => $r['tx']->id)->values();
@endphp
<style>
.bk-tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem}
.bk-tabs a{padding:.4rem .8rem;border:1px solid #c5ccd3;border-radius:2rem;text-decoration:none;color:var(--ink);background:#fff}
.bk-tabs a.on{background:var(--ink);color:#fff;border-color:var(--ink)}
.bk-tx{border:1px solid #e3e6ea;border-radius:.6rem;padding:.8rem 1rem;margin:0 0 .8rem;background:#fff}
.bk-tx.bk-target{border-color:#e0b100;background:#fffdf3}
.bk-top{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.5rem}
.bk-amt{font-size:1.15rem;font-weight:700;white-space:nowrap;color:#b42318}
.bk-sug{background:#f4f8fc;border-radius:.45rem;padding:.5rem .7rem;margin:.4rem 0}
.bk-form{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-top:.4rem}
.bk-form select{flex:1 1 16rem;min-width:0;width:auto;padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;background:#fff}
.bk-form input[type=text]{width:7rem;padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit}
.bk-sure{display:inline-block;background:#ecfdf3;color:#067647;border-radius:1rem;padding:.05rem .5rem;font-size:.85rem;font-weight:600}
</style>

<div class="sd-head">
  <h1>Ausgaben abgleichen</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('bank.index') }}">Kontoauszug einlesen</a>
    <a class="sd-btn sec" href="{{ route('bank.transactions') }}">Gutschriften (Mieten)</a>
    <a class="sd-btn sec" href="{{ route('receipts.index') }}">Belege</a>
  </div>
</div>
<p class="sd-muted">Hier stehen die Abbuchungen aus den eingelesenen Kontoauszügen. Zu jeder gehört ein Beleg (Rechnung, Bescheid, Vertrag). Passt ein vorhandener Beleg, wird er vorgeschlagen. Fehlt er, kannst du ihn direkt aus der Abbuchung heraus erfassen; Datum, Betrag und Lieferant sind dann schon ausgefüllt.</p>

@if ($accounts->count() > 1)
  <form class="rt-filter" method="GET" action="{{ route('bank.expenses') }}">
    <input type="hidden" name="stand" value="{{ $state }}">
    <select name="konto" aria-label="Konto" onchange="this.form.submit()">
      <option value="">Alle Konten</option>
      @foreach ($accounts as $a)
        <option value="{{ $a->id }}" @selected($accountId === (int) $a->id)>{{ $a->owner?->name }} – {{ substr($a->formattedIban(), -9) }}</option>
      @endforeach
    </select>
  </form>
@endif

<nav class="bk-tabs">
  @foreach ($tabs as $k => $label)
    <a href="{{ route('bank.expenses', array_filter(['stand' => $k, 'konto' => $accountId ?: null])) }}" @class(['on' => $state === $k])>{{ $label }} ({{ $tabCount[$k] }})</a>
  @endforeach
</nav>

@if ($state === 'offen' && $sureIds->isNotEmpty())
  <form method="POST" action="{{ route('bank.expenses.accept') }}" class="sd-card" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center">
    @csrf
    <input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
    @foreach ($sureIds as $id)<input type="hidden" name="ids[]" value="{{ $id }}">@endforeach
    <span><strong>{{ $sureIds->count() }} sichere Vorschl{{ $sureIds->count() === 1 ? 'ag' : 'äge' }}</strong> (grün markiert).</span>
    <button class="sd-btn" type="submit">Alle sicheren Vorschläge übernehmen</button>
  </form>
@endif

@if (count($rows) === 0)
  <div class="sd-card"><p class="sd-muted" style="margin:0">@if ($state === 'offen') Zu allen Abbuchungen gibt es einen Beleg oder einen Vermerk. @else Keine Abbuchungen. @endif</p></div>
@endif

@foreach ($rows as $r)
  @php($tx = $r['tx'])
  @php($best = $r['suggestions'][0] ?? null)
  <div class="bk-tx" id="abbuchung-{{ $tx->id }}">
    <div class="bk-top">
      <div><strong>{{ $tx->counterparty_name ?: '–' }}</strong> <span class="sd-muted">· {{ $tx->booking_date->format('d.m.Y') }} · {{ $tx->booking_text }}</span>
        @if ($accounts->count() > 1 && ! $accountId)<br><span class="sd-muted">{{ $tx->owner?->name }}</span>@endif</div>
      <div class="bk-amt">{{ Money::format($tx->amount) }}</div>
    </div>
    @if ($tx->purpose)<div style="margin:.25rem 0;word-break:break-word">{{ $tx->purpose }}</div>@endif

    @foreach ($r['links'] as $l)
      <div class="sd-muted">Beleg: <a href="{{ route('receipts.show', $l->receipt_id) }}">Nr. {{ $l->receipt_id }}</a> {{ $l->vendor_name }} {{ $l->receipt_date ? \Carbon\Carbon::parse($l->receipt_date)->format('d.m.Y') : '' }}, {{ Money::format($l->amount) }}</div>
    @endforeach
    @if ($tx->status === 'kein_beleg')<div class="sd-muted">ohne Beleg: {{ $tx->status_note }}</div>@endif
    @if ($tx->status === 'darlehen')
      <div class="sd-muted">{{ $tx->status_note }} @php($le = \App\Models\LoanEvent::query()->where('bank_transaction_id', $tx->id)->first()) @if ($le) · <a href="{{ route('loans.show', $le->loan_id) }}">Darlehen ansehen</a>@endif</div>
      <form class="bk-form" method="POST" action="{{ route('bank.expenses.loan.reset', $tx) }}" onsubmit="return confirm('Zuordnung zum Darlehen entfernen?')">
        @csrf
        <button class="sd-btn sec small" type="submit">Zurücksetzen</button>
      </form>
    @endif

    @if ($tx->status === 'ausgabe')
      @if ($r['remaining'] !== (int) round(abs((float) $tx->amount) * 100))<div class="rt-part">noch ohne Beleg: {{ Money::format($r['remaining'] / 100) }}</div>@endif
      @if ($best)
        <div class="bk-sug">@if ($r['sure'])<span class="bk-sure">sicher</span>@else Vorschlag:@endif
          <strong>Beleg Nr. {{ $best['receipt']->id }}</strong> {{ $best['receipt']->vendor_name }}, {{ $best['receipt']->receipt_date?->format('d.m.Y') }}, {{ Money::format($best['receipt']->amount_gross) }}
          <span class="sd-muted">({{ implode(', ', $best['reasons']) }})</span></div>
      @endif
      <form class="bk-form" method="POST" action="{{ route('bank.expenses.link', $tx) }}">
        @csrf
        <input type="hidden" name="stand" value="{{ $state }}"><input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
        @if (count($r['suggestions']) > 0)
          <select name="receipt_id" aria-label="Beleg">
            @foreach ($r['suggestions'] as $s)
              <option value="{{ $s['receipt']->id }}">Nr. {{ $s['receipt']->id }} · {{ $s['receipt']->vendor_name ?: 'ohne Lieferant' }} · {{ $s['receipt']->receipt_date?->format('d.m.Y') }} · {{ Money::format($s['receipt']->amount_gross) }}</option>
            @endforeach
          </select>
        @else
          <input type="text" name="receipt_id" inputmode="numeric" placeholder="Beleg-Nr." aria-label="Beleg-Nr.">
        @endif
        <input type="text" name="amount" inputmode="decimal" value="{{ Money::input($r['remaining'] / 100) }}" aria-label="Betrag">
        <button class="sd-btn small" type="submit">Beleg verknüpfen</button>
        <a class="sd-btn sec small" href="{{ route('receipts.create', array_filter(['umsatz' => $tx->id, 'wiederkehrend' => $r['recurring']?->id])) }}">Beleg erfassen</a>
      </form>
      @if ($r['recurring'])<div class="sd-muted" style="font-size:.9rem;margin-top:.2rem">Passt zur wiederkehrenden Ausgabe „{{ $r['recurring']->title() }}“, der neue Beleg wird damit vorbelegt.</div>@endif
      @if ($r['loans']->isNotEmpty())
        @if ($r['loanSug'])
          <div class="bk-sug">Darlehensrate? <strong>{{ $r['loanSug']['loan']->name }}</strong> <span class="sd-muted">({{ implode(', ', $r['loanSug']['reasons']) }})</span></div>
        @endif
        <details style="margin-top:.4rem" @if ($r['loanSug']) open @endif>
          <summary class="sd-muted" style="cursor:pointer">Darlehensrate</summary>
          <form class="bk-form" method="POST" action="{{ route('bank.expenses.loan', $tx) }}">
            @csrf
            <input type="hidden" name="stand" value="{{ $state }}"><input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
            <select name="loan_id" aria-label="Darlehen">
              @foreach ($r['loans'] as $ln)<option value="{{ $ln->id }}" @selected($r['loanSug'] && $r['loanSug']['loan']->id === $ln->id)>{{ $ln->name }}</option>@endforeach
            </select>
            <button class="sd-btn small" type="submit">Dem Darlehen zuordnen</button>
          </form>
        </details>
      @endif
      <details style="margin-top:.4rem">
        <summary class="sd-muted" style="cursor:pointer">Kein Beleg nötig</summary>
        <form class="bk-form" method="POST" action="{{ route('bank.expenses.none', $tx) }}">
          @csrf
          <input type="hidden" name="stand" value="{{ $state }}"><input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
          <select name="reason" aria-label="Grund">@foreach (ExpenseMatcher::NO_RECEIPT as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select>
          <input type="text" name="note" maxlength="150" placeholder="Notiz (optional)" style="width:12rem" aria-label="Notiz">
          <button class="sd-btn sec small" type="submit">Speichern</button>
        </form>
      </details>
    @endif

    @if ($tx->status === 'beleg' || $tx->status === 'kein_beleg' || ($tx->status === 'ausgabe' && $r['links']->isNotEmpty()))
      <form class="bk-form" method="POST" action="{{ route('bank.expenses.reset', $tx) }}" onsubmit="return confirm('Zuordnung zurücksetzen?')">
        @csrf
        <input type="hidden" name="stand" value="{{ $state }}"><input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
        <button class="sd-btn sec small" type="submit">Zurücksetzen</button>
      </form>
    @endif
  </div>
@endforeach
<script>
(function () { var h = location.hash; if (h && h.indexOf('#abbuchung-') === 0) { var r = document.querySelector(h); if (r) r.classList.add('bk-target'); } })();
</script>
@endsection
