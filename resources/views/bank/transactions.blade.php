@extends('layouts.app')
@section('title', 'Umsätze – Eingänge')
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
.bk-tabs a{padding:.4rem .8rem;border:1px solid var(--line-strong);border-radius:2rem;text-decoration:none;color:var(--ink);background:var(--surface)}
.bk-tabs a.on{background:var(--primary);color:var(--on-primary);border-color:var(--ink)}
.bk-tx{border:1px solid var(--line);border-radius:.6rem;padding:.8rem 1rem;margin:0 0 .8rem;background:var(--surface)}
.bk-tx.bk-target{border-color:#e0b100;background:var(--brass-bg)}
.bk-top{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.5rem}
.bk-amt{font-size:1.15rem;font-weight:700;white-space:nowrap}
.bk-amt.in{color:var(--ok)}.bk-amt.out{color:var(--danger)}
.bk-purpose{margin:.25rem 0 .4rem;word-break:break-word}
.bk-sug{background:var(--surface-2);border-radius:.45rem;padding:.5rem .7rem;margin:.4rem 0}
.bk-form{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center;margin-top:.4rem}
.bk-form select{flex:1 1 16rem;min-width:0;width:auto;padding:.4rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;background:var(--surface)}
.bk-form input[name=amount]{width:7rem;padding:.4rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;text-align:right}
.bk-form input[name=note]{flex:1 1 10rem;width:auto;padding:.4rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit}
.bk-right{display:flex;flex-direction:column;align-items:flex-end;gap:.2rem}
.bk-edit{font-size:.85rem;color:var(--muted);text-decoration:none;border:1px solid var(--line-strong);border-radius:1rem;padding:.1rem .6rem;white-space:nowrap;background:var(--surface)}
.bk-edit:hover{color:var(--ink);border-color:var(--ink)}
.bk-sure{display:inline-block;background:var(--ok-bg);color:var(--ok);border-radius:1rem;padding:.05rem .5rem;font-size:.85rem;font-weight:600}
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
      <div class="sd-muted">{{ $p->incomeLabel() }}: {{ Money::format($p->amount) }} an <a href="{{ route('leases.show', $p->lease_id) }}">{{ $p->lease?->unit?->label }} – {{ $p->lease?->tenant?->display_name }}</a></div>
    @endforeach
    @if ($tx->relationLoaded('items'))
      @foreach ($tx->items as $it)
        <div class="sd-muted">{{ $it->kindLabel() }}: {{ Money::format($it->amount) }}@if ($it->lease) an <a href="{{ route('leases.show', $it->lease_id) }}">{{ $it->lease->unit?->label }} – {{ $it->lease->tenant?->display_name }}</a>@endif{{ $it->note ? ' · '.$it->note : '' }}</div>
      @endforeach
    @endif
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
        <button class="sd-btn sec small" type="button" data-sp-open="aufteilen-{{ $tx->id }}">Aufteilen …</button>
      </form>
      @include('bank.split-dialog')
      @if ($tx->status === 'offen')
        <details style="margin-top:.4rem">
          <summary class="sd-muted" style="cursor:pointer">Keine Miete (z. B. Strom, Privates) – Kaution und Teilbeträge über „Aufteilen …“</summary>
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
<style>
.sp-dlg{border:0;border-radius:14px;padding:0;width:min(760px,calc(100vw - 24px));max-height:calc(100vh - 24px);box-shadow:0 20px 50px rgba(0,0,0,.3)}
.sp-dlg::backdrop{background:rgba(17,24,39,.5)}
.sp-form{padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:.7rem}
.sp-head{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}
.sp-x{border:0;background:none;font-size:1.1rem;cursor:pointer;color:var(--muted)}
.sp-row{display:grid;grid-template-columns:repeat(6,minmax(0,1fr)) auto;gap:.5rem .6rem;align-items:end;border:1px solid var(--line);border-radius:10px;padding:.7rem;margin-bottom:.5rem;background:var(--surface-2)}
.sp-f{grid-column:span 2;display:flex;flex-direction:column;gap:.2rem;min-width:0}
.sp-f.sp-wide{grid-column:span 4}
.sp-f label{font-size:.82rem;color:var(--muted);margin:0}
.sp-f select,.sp-f input{width:100%;min-width:0;padding:.45rem .55rem;border:1px solid var(--line-strong);border-radius:8px;font:inherit;background:var(--surface)}
.sp-amt input{text-align:right}
.sp-del{border:0;background:none;cursor:pointer;font-size:1rem;padding:.4rem;grid-row:1;grid-column:7}
.sp-sum{text-align:right;font-size:1.05rem}
.sp-sum .bad{color:var(--danger)}
.sp-foot{display:flex;justify-content:flex-end;gap:.5rem}
@media (max-width:640px){.sp-row{grid-template-columns:1fr 1fr auto}.sp-f,.sp-f.sp-wide{grid-column:span 2}.sp-del{grid-column:3}}
</style>
<script>
(function () {
  function cents(v) { v = String(v || '').trim().replace(/\s/g, ''); if (v.indexOf(',') >= 0) v = v.replace(/\./g, '').replace(',', '.'); var n = parseFloat(v); return isNaN(n) ? 0 : Math.round(n * 100); }
  function eur(c) { return (c / 100).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €'; }
  function renumber(dlg) {
    dlg.querySelectorAll('.sp-row').forEach(function (row, i) {
      row.querySelectorAll('[name^="rows["]').forEach(function (el) { el.name = el.name.replace(/^rows\[\d+\]/, 'rows[' + i + ']'); });
      var kind = row.querySelector('[data-sp-kind]').value;
      row.querySelector('[data-sp-month]').style.display = kind === 'miete' ? '' : 'none';
      row.querySelector('[data-sp-req]').style.display = kind === 'sonstiges' ? 'none' : '';
    });
    var total = 0; dlg.querySelectorAll('[data-sp-amount]').forEach(function (el) { total += cents(el.value); });
    var max = Math.round(parseFloat(dlg.dataset.max) * 100), hint = dlg.querySelector('[data-sp-hint]'), t = dlg.querySelector('[data-sp-total]');
    t.textContent = eur(total); t.classList.toggle('bad', total > max);
    hint.textContent = total > max ? '(höher als offen: ' + eur(max) + ')' : (total < max && total > 0 ? '(Rest ' + eur(max - total) + ' bleibt offen)' : '');
    dlg.querySelector('[data-sp-save]').disabled = total <= 0 || total > max;
    dlg.querySelectorAll('[data-sp-del]').forEach(function (b) { b.style.visibility = dlg.querySelectorAll('.sp-row').length > 1 ? '' : 'hidden'; });
  }
  document.addEventListener('click', function (e) {
    var o = e.target.closest('[data-sp-open]');
    if (o) { var d = document.getElementById(o.dataset.spOpen); if (d) { renumber(d); d.showModal(); } return; }
    var dlg = e.target.closest('.sp-dlg'); if (!dlg) return;
    if (e.target.closest('[data-sp-close]')) { dlg.close(); return; }
    if (e.target.closest('[data-sp-add]')) {
      var rows = dlg.querySelector('.sp-rows'), first = rows.querySelector('.sp-row'), copy = first.cloneNode(true);
      copy.querySelectorAll('input').forEach(function (el) { el.value = ''; });
      copy.querySelector('[data-sp-kind]').value = 'miete';
      copy.querySelectorAll('select:not([data-sp-kind])').forEach(function (el) { el.selectedIndex = 0; });
      copy.querySelector('[name$="[lease_id]"]').value = first.querySelector('[name$="[lease_id]"]').value;
      var used = 0; dlg.querySelectorAll('[data-sp-amount]').forEach(function (el) { used += cents(el.value); });
      var rest = Math.round(parseFloat(dlg.dataset.max) * 100) - used;
      if (rest > 0) copy.querySelector('[data-sp-amount]').value = (rest / 100).toFixed(2).replace('.', ',');
      rows.appendChild(copy); renumber(dlg); return;
    }
    var del = e.target.closest('[data-sp-del]');
    if (del && dlg.querySelectorAll('.sp-row').length > 1) { del.closest('.sp-row').remove(); renumber(dlg); }
  });
  document.addEventListener('input', function (e) { var d = e.target.closest('.sp-dlg'); if (d) renumber(d); });
  document.addEventListener('change', function (e) { var d = e.target.closest('.sp-dlg'); if (d) renumber(d); });
})();
(function () { var h = location.hash; if (h && h.indexOf('#umsatz-') === 0) { var r = document.querySelector(h); if (r) r.classList.add('bk-target'); } })();
</script>
@endsection
