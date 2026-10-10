@extends('layouts.app')
@section('title', 'Belege')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.rc-filter{display:flex;flex-wrap:wrap;gap:.5rem;align-items:end;margin:.2rem 0 1rem}
.rc-filter .f{display:flex;flex-direction:column}
.rc-filter label{font-size:.85rem;margin:0 0 .15rem}
.rc-filter select,.rc-filter input[type=text]{padding:.5rem;border:1px solid #c5ccd3;border-radius:.45rem;font:inherit;background:#fff}
.rc-badge-ok{color:#067647;font-weight:600}
.rc-badge-open{color:#7a4b00;font-weight:600}
.rc-warn{background:#fffaf0;border:1px solid #f0d9a8;color:#7a4b00;padding:.7rem 1rem;border-radius:.5rem;margin-bottom:1rem}
.rc-num{text-align:right;white-space:nowrap}
</style>

<div class="sd-head">
  <h1>Belege</h1>
  <div class="sd-actions">
    <a class="sd-btn" href="{{ route('receipts.create') }}">Beleg erfassen</a>
    <a class="sd-btn sec" href="{{ route('einvoice.create') }}">E-Rechnung hochladen</a>
    <a class="sd-btn sec" href="{{ route('recurring.index') }}">Wiederkehrende Ausgaben</a>
    <a class="sd-btn sec" href="{{ route('vendors.index') }}">Lieferanten</a>
    @if (auth()->user()->isAdmin())
      <a class="sd-btn sec" href="{{ route('cost-types.index') }}">Kostenarten</a>
      <a class="sd-btn sec" href="{{ route('datev.index') }}">DATEV-Export</a>
    @endif
  </div>
</div>

@if (session('warning'))
  <div class="rc-warn">{{ session('warning') }}</div>
@endif

@if ($incompleteCount > 0 && $status !== 'unvollstaendig')
  <div class="rc-warn">
    <strong>{{ $incompleteCount }} {{ $incompleteCount === 1 ? 'Beleg ist' : 'Belege sind' }} unvollständig.</strong>
    <a href="{{ route('receipts.index', ['status' => 'unvollstaendig']) }}">Nur diese anzeigen</a>
  </div>
@endif

<form class="rc-filter" method="GET" action="{{ route('receipts.index') }}">
  @if ($owners->count() > 1)
    <div class="f">
      <label for="rc-owner">Eigentümer</label>
      <select id="rc-owner" name="eigentuemer">
        <option value="">Alle</option>
        @foreach ($owners as $owner)
          <option value="{{ $owner->id }}" @selected($ownerFilter === (int) $owner->id)>{{ $owner->name }}</option>
        @endforeach
      </select>
    </div>
  @endif
  <div class="f">
    <label for="rc-status">Stand</label>
    <select id="rc-status" name="status">
      <option value="" @selected($status === '')>Alle</option>
      <option value="unvollstaendig" @selected($status === 'unvollstaendig')>Unvollständig</option>
      <option value="vollstaendig" @selected($status === 'vollstaendig')>Vollständig</option>
    </select>
  </div>
  <div class="f">
    <label for="rc-q">Lieferant, Rechnungsnummer oder Notiz</label>
    <input id="rc-q" type="text" name="q" value="{{ $q }}" maxlength="100">
  </div>
  <button class="sd-btn sec small" type="submit">Anzeigen</button>
</form>

<div class="sd-card">
  <div class="sd-table-wrap">
    <table class="sd">
      <thead>
        <tr>
          <th>Nr.</th><th>Datum</th><th>Eigentümer und Objekt</th><th>Lieferant</th><th>Kostenart</th><th class="rc-num">Betrag</th><th>Seiten</th><th>Stand</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($receipts as $receipt)
          @php($missing = $receipt->missing())
          <tr>
            <td>
              <a href="{{ route('receipts.show', $receipt) }}">{{ $receipt->id }}</a>
              @if ($receipt->isEInvoice())<br><span class="sd-muted">E-Rechnung</span>@endif
            </td>
            <td style="white-space:nowrap">{{ $receipt->receipt_date?->format('d.m.Y') ?? '–' }}</td>
            <td>
              {{ $receipt->owner->name }}<br>
              <span class="sd-muted">{{ $receipt->placeLabel() }}</span>
            </td>
            <td>{{ $receipt->vendor_name ?: '–' }}</td>
            <td>{{ $receipt->categoryLabel() }}</td>
            <td class="rc-num">{{ $receipt->amountLabel() }}</td>
            <td>{{ $receipt->files_count }}</td>
            <td>
              @if ($missing === [])
                <span class="rc-badge-ok">vollständig</span>
              @else
                <span class="rc-badge-open">fehlt: {{ implode(', ', $missing) }}</span>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="8" class="sd-muted">Noch keine Belege gefunden.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="pager">
  @if ($receipts->previousPageUrl())
    <a class="sd-btn sec small" href="{{ $receipts->previousPageUrl() }}">Neuere Belege</a>
  @else
    <span></span>
  @endif
  @if ($receipts->nextPageUrl())
    <a class="sd-btn sec small" href="{{ $receipts->nextPageUrl() }}">Ältere Belege</a>
  @endif
</div>
@endsection
