@extends('layouts.app')
@section('title', 'Umsatz bearbeiten')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  $in = (float) $tx->amount >= 0;
  $remaining = $in ? $tx->remaining() : 0;
  $keep = ['stand' => $stand, 'konto' => $konto, 'ziel' => 'bearbeiten'];
  $leaseLabel = fn ($l) => ($l->unit?->property?->name ? $l->unit->property->name.', ' : '').($l->unit?->label ?? 'Einheit').' – '.($l->tenant?->display_name ?? 'Mieter').($l->end_date && $l->end_date->format('Y-m-d') < date('Y-m-d') ? ' (beendet)' : '');
@endphp
<style>
.be-sum{display:flex;flex-wrap:wrap;justify-content:space-between;gap:.6rem;align-items:flex-start}
.be-amt{font-size:1.5rem;font-weight:700;white-space:nowrap}
.be-amt.in{color:#067647}.be-amt.out{color:#b42318}
.be-st{display:inline-block;border-radius:1rem;padding:.08rem .6rem;font-size:.82rem;font-weight:700;background:#eef2ff;color:#3730a3}
.be-st.offen,.be-st.teilweise{background:#fff7ed;color:#b54708}
.be-st.zugeordnet{background:#ecfdf3;color:#067647}
.be-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:.2rem 1rem}
.be-grid .wide{grid-column:1/-1}
.be-grid textarea{width:100%;font:inherit;padding:.55rem .7rem;border:1px solid var(--line-strong);border-radius:8px;min-height:5rem}
.be-pay{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;padding:.7rem 0;border-top:1px solid var(--line)}
.be-pay:first-of-type{border-top:0}
.be-pay form{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:0;flex:1 1 30rem}
.be-pay select{flex:1 1 18rem;min-width:0;width:auto;padding:.45rem;border:1px solid var(--line-strong);border-radius:8px;font:inherit;background:#fff}
.be-pay input[name=amount]{width:7.5rem;padding:.45rem;border:1px solid var(--line-strong);border-radius:8px;font:inherit;text-align:right}
.be-pay input[name=note]{flex:1 1 14rem;width:auto;padding:.45rem;border:1px solid var(--line-strong);border-radius:8px;font:inherit}
.be-warn{font-size:.85rem;color:#b54708;width:100%}
.be-meta{display:grid;grid-template-columns:auto 1fr;gap:.15rem .9rem;font-size:.9rem;margin:.6rem 0 0}
.be-meta dt{color:var(--muted)}.be-meta dd{margin:0;overflow-wrap:anywhere}
</style>

<div class="sd-head">
  <h1>Umsatz bearbeiten</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ $backUrl }}">Zurück zur Liste</a></div>
</div>
@error('tx')<div class="sd-err">{{ $message }}</div>@enderror

{{-- Überblick --}}
<div class="sd-card">
  <div class="be-sum">
    <div>
      <strong style="font-size:1.1rem">{{ $tx->counterparty_name ?: '–' }}</strong>
      <div class="sd-muted">{{ $tx->booking_date->format('d.m.Y') }}@if ($tx->booking_text) · {{ $tx->booking_text }}@endif</div>
      <div style="margin-top:.35rem"><span class="be-st {{ $tx->status }}">{{ $tx->statusLabel() }}</span>@if ($in && in_array($tx->status, ['teilweise'], true)) <span class="sd-muted">· offen: {{ Money::format($remaining) }}</span>@endif</div>
    </div>
    <div class="be-amt {{ $in ? 'in' : 'out' }}">{{ Money::format($tx->amount) }}</div>
  </div>
  <dl class="be-meta">
    <dt>Konto</dt><dd>{{ $tx->owner?->name }}@if ($tx->account) · {{ $tx->account->formattedIban() }}@endif</dd>
    <dt>IBAN Gegenseite</dt><dd>{{ $tx->maskedIban() }}</dd>
    @if ($tx->import)<dt>Eingelesen aus</dt><dd>{{ $tx->import->filename }} ({{ $tx->import->created_at?->timezone('Europe/Berlin')->format('d.m.Y') }})</dd>@endif
  </dl>
</div>

{{-- Zuordnung (nur Gutschriften) --}}
@if ($in)
  <div class="sd-card">
    <h2>Zuordnung zur Miete</h2>
    @if ($tx->status === 'ignoriert')
      <p style="margin:0 0 .4rem">Als <strong>keine Miete</strong> markiert{{ $tx->status_note ? ': '.$tx->status_note : '' }}.</p>
      <div class="be-pay">
        <form method="POST" action="{{ route('bank.ignore', $tx) }}">
          @csrf
          @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
          <input type="text" name="note" maxlength="190" value="{{ $tx->status_note }}" placeholder="Grund, z. B. Kaution" aria-label="Grund">
          <button class="sd-btn sec small" type="submit">Grund speichern</button>
        </form>
        <form method="POST" action="{{ route('bank.reset', $tx) }}" style="flex:0 0 auto">
          @csrf
          @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
          <button class="sd-btn small" type="submit">Doch Miete – wieder zuordnen</button>
        </form>
      </div>
    @else
      @forelse ($tx->payments as $p)
        <div class="be-pay">
          <span class="be-st">{{ $p->incomeLabel() }}</span>
          <form method="POST" action="{{ route('bank.payment.update', [$tx, $p]) }}">
            @csrf @method('PUT')
            @foreach (['stand' => $stand, 'konto' => $konto] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="lease_id" aria-label="Mietvertrag">
              @foreach ($leases as $l)<option value="{{ $l->id }}" @selected((int) $l->id === (int) $p->lease_id)>{{ $leaseLabel($l) }}</option>@endforeach
            </select>
            <input type="text" name="amount" inputmode="decimal" value="{{ Money::input($p->amount) }}" aria-label="Betrag">
            <button class="sd-btn small" type="submit">Ändern</button>
          </form>
          <form method="POST" action="{{ route('bank.payment.remove', [$tx, $p]) }}" style="flex:0 0 auto" onsubmit="return confirm('Diese Zuordnung entfernen? Die Zahlung wird storniert, der Betrag ist wieder offen.')">
            @csrf @method('DELETE')
            @foreach (['stand' => $stand, 'konto' => $konto] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <button class="sd-btn danger small" type="submit">Entfernen</button>
          </form>
          @if (isset($exported[$p->id]))<div class="be-warn">Diese Zahlung wurde schon an DATEV übergeben. Eine Änderung bitte dem Steuerberater mitteilen.</div>@endif
        </div>
      @empty
        @if ($tx->relationLoaded('items') && $tx->items->isNotEmpty())@else<p class="sd-muted" style="margin:0 0 .4rem">Noch keinem Mietvertrag zugeordnet.</p>@endif
      @endforelse
      @if ($tx->relationLoaded('items'))
        @foreach ($tx->items as $it)
          <div class="be-pay">
            <span class="be-st">{{ $it->kindLabel() }}</span>
            <span style="flex:1 1 18rem">{{ Money::format($it->amount) }}@if ($it->lease) · {{ $leaseLabel($it->lease) }}@endif @if ($it->note)<span class="sd-muted"> · {{ $it->note }}</span>@endif
              @if ($it->kind === 'kaution')<br><span class="sd-muted">bei der Kaution als Einzahlung gebucht</span>@endif</span>
            <form method="POST" action="{{ route('bank.item.remove', [$tx, $it]) }}" style="flex:0 0 auto" onsubmit="return confirm('Diesen Teil entfernen? Der Betrag ist danach wieder offen.')">
              @csrf @method('DELETE')
              @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
              <button class="sd-btn danger small" type="submit">Entfernen</button>
            </form>
          </div>
        @endforeach
      @endif

      @if ($remaining > 0.004)
        <h3 style="font-size:1rem;margin:1rem 0 .2rem">{{ $tx->payments->isEmpty() ? 'Zuordnen' : 'Rest zuordnen ('.Money::format($remaining).')' }}</h3>
        <div class="be-pay">
          <form method="POST" action="{{ route('bank.assign', $tx) }}">
            @csrf
            @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
            <select name="lease_id" required aria-label="Mietvertrag">
              <option value="">– Mietvertrag wählen –</option>
              @if ($suggestions)
                <optgroup label="Vorschläge">
                  @foreach ($suggestions as $i => $s)<option value="{{ $s['lease']->id }}" @selected($i === 0 && $s['score'] >= 55)>{{ $leaseLabel($s['lease']) }}</option>@endforeach
                </optgroup>
              @endif
              <optgroup label="Alle Verträge">
                @foreach ($leases as $l)<option value="{{ $l->id }}">{{ $leaseLabel($l) }}</option>@endforeach
              </optgroup>
            </select>
            <input type="text" name="amount" inputmode="decimal" value="{{ Money::input($remaining) }}" required aria-label="Betrag">
            <label class="sd-check" style="margin:0;font-weight:400"><input type="checkbox" name="merken" value="1" checked> Konto merken</label>
            <button class="sd-btn small" type="submit">Zuordnen</button>
          </form>
        </div>
        @if ($tx->payments->isEmpty())
          <details style="margin-top:.4rem">
            <summary class="sd-muted" style="cursor:pointer">Keine Miete (z. B. Kaution, Strom, Privates)</summary>
            <div class="be-pay">
              <form method="POST" action="{{ route('bank.ignore', $tx) }}">
                @csrf
                @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
                <input type="text" name="note" maxlength="190" placeholder="Grund, z. B. Kaution oder Stromabschlag" aria-label="Grund">
                <button class="sd-btn sec small" type="submit">Als „keine Miete“ markieren</button>
              </form>
            </div>
          </details>
        @endif
      @endif
    @endif
  </div>
@else
  <div class="sd-card">
    <h2>Abbuchung</h2>
    <p style="margin:0">
      @if ($loan) Als Darlehensrate gebucht.
      @elseif ($receipts > 0.004) Mit Belegen über {{ Money::format($receipts) }} verknüpft.
      @elseif ($tx->status_note) {{ $tx->status_note }}
      @else Noch keinem Beleg zugeordnet.
      @endif
      Belege und Darlehen ordnest du unter <a href="{{ route('bank.expenses', array_filter(['konto' => $konto ?: null])) }}#abbuchung-{{ $tx->id }}">Umsätze → Ausgaben zuordnen</a> zu.
    </p>
  </div>
@endif

{{-- Angaben korrigieren --}}
<form method="POST" action="{{ route('bank.update', $tx) }}" class="sd-card">
  @csrf @method('PUT')
  @foreach (['stand' => $stand, 'konto' => $konto] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
  <h2>Angaben korrigieren</h2>
  <p class="sd-muted" style="margin-top:0">Die Angaben kommen aus dem Kontoauszug. Ändere sie nur, wenn etwas falsch eingelesen wurde. Zugeordnete Zahlungen übernehmen einen neuen Buchungstag automatisch.</p>
  <div class="be-grid">
    @include('partials.sd-field', ['name' => 'booking_date', 'label' => 'Buchungstag', 'type' => 'date', 'value' => $tx->booking_date->format('Y-m-d'), 'required' => true])
    @include('partials.sd-field', ['name' => 'value_date', 'label' => 'Wertstellung', 'type' => 'date', 'value' => $tx->value_date?->format('Y-m-d')])
    @include('partials.sd-field', ['name' => 'amount', 'label' => 'Betrag in €', 'value' => Money::input($tx->amount), 'required' => true, 'inputmode' => 'decimal', 'hint' => 'Abbuchungen mit Minus, z. B. -120,50'])
    <div class="wide">@include('partials.sd-field', ['name' => 'counterparty_name', 'label' => $in ? 'Auftraggeber' : 'Empfänger', 'value' => $tx->counterparty_name])</div>
    @include('partials.sd-field', ['name' => 'booking_text', 'label' => 'Buchungstext', 'value' => $tx->booking_text, 'hint' => 'z. B. Gutschrift, Lastschrift'])
    <div class="sd-field wide">
      <label for="f-purpose">Verwendungszweck</label>
      <textarea id="f-purpose" name="purpose" maxlength="2000">{{ old('purpose', $tx->purpose) }}</textarea>
    </div>
  </div>
  <div class="sd-actions"><button class="sd-btn" type="submit">Speichern</button></div>
</form>

@if ($in && in_array($tx->status, ['teilweise', 'zugeordnet'], true))
  <form method="POST" action="{{ route('bank.reset', $tx) }}" class="sd-card" onsubmit="return confirm('Alle Zuordnungen zurücksetzen? Alle Zahlungen aus diesem Umsatz werden storniert.')">
    @csrf
    @foreach ($keep as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
    <div style="display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;justify-content:space-between">
      <span class="sd-muted">Alles auf einmal zurücknehmen und neu zuordnen.</span>
      <button class="sd-btn sec small" type="submit">Alle Zuordnungen zurücksetzen</button>
    </div>
  </form>
@endif
@endsection
