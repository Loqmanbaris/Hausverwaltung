@extends('layouts.app')
@section('title', 'Kontoauszüge – Eingänge')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  $tabs = ['offen' => 'Zuzuordnen', 'zugeordnet' => 'Zugeordnet', 'ignoriert' => 'Keine Miete', 'alle' => 'Alle'];
  $tabCount = [
    'offen' => ($counts['offen'] ?? 0) + ($counts['teilweise'] ?? 0),
    'zugeordnet' => ($counts['zugeordnet'] ?? 0) + ($counts['teilweise'] ?? 0),
    'ignoriert' => $counts['ignoriert'] ?? 0,
    'ausgaben' => $counts['ausgabe'] ?? 0,
    'alle' => $counts->sum(),
  ];
@endphp
<style>
.bk-tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem}
.bk-tabs a{padding:.4rem .8rem;border:1px solid #c5ccd3;border-radius:2rem;text-decoration:none;color:var(--ink);background:#fff}
.bk-tabs a.on{background:var(--ink);color:#fff;border-color:var(--ink)}
.bk-tx{border:1px solid #e3e6ea;border-radius:.6rem;padding:.8rem 1rem;margin:0 0 .8rem;background:#fff}
.bk-tx.bk-target{border-color:#e0b100;background:#fffdf3}
.bk-top{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.5rem}
.bk-amt{font-size:1.15rem;font-weight:700;white-space:nowrap}
.bk-amt.in{color:#067647}.bk-amt.out{color:#b42318}
.bk-purpose{margin:.25rem 0 .4rem;word-break:break-word}
.bk-sug{background:#f4f8fc;border-radius:.45rem;padding:.5rem .7rem;margin:.4rem 0}
.bk-form{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-top:.4rem}
.bk-form select{flex:1 1 16rem;min-width:0;width:auto;padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;background:#fff}
.bk-form input[name=amount]{width:7rem;padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;text-align:right}
.bk-form input[name=note]{flex:1 1 10rem;width:auto;padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit}
.bk-right{display:flex;flex-direction:column;align-items:flex-end;gap:.2rem}
.bk-edit{font-size:.85rem;color:var(--muted);text-decoration:none;border:1px solid var(--line-strong);border-radius:1rem;padding:.1rem .6rem;white-space:nowrap;background:#fff}
.bk-edit:hover{color:var(--ink);border-color:var(--ink)}
.bk-sure{display:inline-block;background:#ecfdf3;color:#067647;border-radius:1rem;padding:.05rem .5rem;font-size:.85rem;font-weight:600}
</style>

@php
  $kzActions = '<a class="sd-btn sec" href="'.e(route('rent.month')).'">Mieteingang</a>';
@endphp
@include('partials.bank-tabs')

@php($filterRoute = 'bank.transactions')
@include('partials.bank-filter')

<nav class="bk-tabs">
  @foreach ($tabs as $k => $label)
    <a href="{{ $k === 'ausgaben' ? route('bank.expenses', array_filter(['konto' => $accountId ?: null])) : route('bank.transactions', array_filter(['stand' => $k, 'konto' => $accountId ?: null])) }}" @class(['on' => $state === $k])>{{ $label }} ({{ $tabCount[$k] }})</a>
  @endforeach
</nav>

@if ($state === 'offen' && $sureCount > 0)
  <form method="POST" action="{{ route('bank.accept') }}" class="sd-card" style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center">
    @csrf
    <input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
    @foreach ($rows as $r)
      @if ($r['sure'] && $r['tx']->status === 'offen')<input type="hidden" name="ids[]" value="{{ $r['tx']->id }}">@endif
    @endforeach
    <span><strong>{{ $sureCount }} sichere Vorschl{{ $sureCount === 1 ? 'ag' : 'äge' }}</strong> (grün markiert).</span>
    <button class="sd-btn" type="submit">Alle sicheren Vorschläge übernehmen</button>
  </form>
@endif

@if (count($rows) === 0)
  <div class="sd-card"><p class="sd-muted" style="margin:0">@if ($state === 'offen') Alles zugeordnet. Neue Umsätze kommen mit dem nächsten Kontoauszug. @else Keine Umsätze. @endif</p></div>
@endif

@foreach ($rows as $r)
  @php($tx = $r['tx'])
  @php($best = $r['suggestions'][0] ?? null)
  <div class="bk-tx" id="umsatz-{{ $tx->id }}">
    <div class="bk-top">
      <div>
        <strong>{{ $tx->counterparty_name ?: '–' }}</strong>
        <span class="sd-muted"> · {{ $tx->booking_date->format('d.m.Y') }} · {{ $tx->booking_text }}</span>
        @if ($accounts->count() > 1 && ! $accountId)<br><span class="sd-muted">{{ $tx->owner?->name }}</span>@endif
      </div>
      <div class="bk-right"><div class="bk-amt {{ (float) $tx->amount >= 0 ? 'in' : 'out' }}">{{ Money::format($tx->amount) }}</div>
        <a class="bk-edit" href="{{ route('bank.edit', array_filter(['tx' => $tx->id, 'stand' => $state, 'konto' => $accountId ?: null])) }}">✎ Bearbeiten</a></div>
    </div>
    @if ($tx->purpose)<div class="bk-purpose">{{ $tx->purpose }}</div>@endif

    @foreach ($tx->payments as $p)
      <div class="sd-muted">zugeordnet: {{ Money::format($p->amount) }} an <a href="{{ route('leases.show', $p->lease_id) }}">{{ $p->lease?->unit?->label }} – {{ $p->lease?->tenant?->display_name }}</a></div>
    @endforeach
    @if ($tx->status === 'ignoriert')<div class="sd-muted">keine Miete{{ $tx->status_note ? ': '.$tx->status_note : '' }}</div>@endif

    @if (in_array($tx->status, ['offen', 'teilweise'], true))
      @php($remaining = $tx->remaining())
      @if ($best)
        <div class="bk-sug">
          @if ($r['sure'])<span class="bk-sure">sicher</span>@else Vorschlag:@endif
          <strong>{{ $best['lease']->unit?->label }} – {{ $best['lease']->tenant?->display_name }}</strong>
          <span class="sd-muted">({{ implode(', ', $best['reasons']) }})</span>
        </div>
      @endif
      <form class="bk-form" method="POST" action="{{ route('bank.assign', $tx) }}">
        @csrf
        <input type="hidden" name="stand" value="{{ $state }}"><input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
        <select name="lease_id" required aria-label="Mietvertrag">
          <option value="">– Mietvertrag wählen –</option>
          @if (count($r['suggestions']) > 0)
            <optgroup label="Vorschläge">
              @foreach ($r['suggestions'] as $i => $s)
                <option value="{{ $s['lease']->id }}" @selected($i === 0 && $s['score'] >= 55)>{{ $s['lease']->unit?->label }} – {{ $s['lease']->tenant?->display_name }}</option>
              @endforeach
            </optgroup>
          @endif
          <optgroup label="Alle Verträge">
            @foreach ($leaseOptions[$tx->owner_id] ?? [] as $l)
              <option value="{{ $l->id }}">{{ $l->unit?->property?->name }}, {{ $l->unit?->label }} – {{ $l->tenant?->display_name }}{{ $l->end_date && $l->end_date->format('Y-m-d') < date('Y-m-d') ? ' (beendet)' : '' }}</option>
            @endforeach
          </optgroup>
        </select>
        <input type="text" name="amount" inputmode="decimal" value="{{ Money::input($remaining) }}" required aria-label="Betrag">
        <label class="sd-check" style="margin:0;font-weight:400"><input type="checkbox" name="merken" value="1" checked> Konto merken</label>
        <button class="sd-btn small" type="submit">Zuordnen</button>
      </form>
      @if ($tx->status === 'offen')
        <details style="margin-top:.4rem">
          <summary class="sd-muted" style="cursor:pointer">Keine Miete (z. B. Kaution, Strom, Privates)</summary>
          <form class="bk-form" method="POST" action="{{ route('bank.ignore', $tx) }}">
            @csrf
            <input type="hidden" name="stand" value="{{ $state }}"><input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
            <input type="text" name="note" maxlength="190" placeholder="Grund, z. B. Kaution oder Stromabschlag" aria-label="Grund">
            <button class="sd-btn sec small" type="submit">Als „keine Miete“ markieren</button>
          </form>
        </details>
      @endif
    @endif

    @if (in_array($tx->status, ['teilweise', 'zugeordnet', 'ignoriert'], true))
      <form class="bk-form" method="POST" action="{{ route('bank.reset', $tx) }}" onsubmit="return confirm('Zuordnung zurücksetzen? Zugeordnete Zahlungen werden storniert.')">
        @csrf
        <input type="hidden" name="stand" value="{{ $state }}"><input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
        <button class="sd-btn sec small" type="submit">Zurücksetzen</button>
      </form>
    @endif
  </div>
@endforeach
<script>
(function () { var h = location.hash; if (h && h.indexOf('#umsatz-') === 0) { var r = document.querySelector(h); if (r) r.classList.add('bk-target'); } })();
</script>
@endsection
