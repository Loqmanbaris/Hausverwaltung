@extends('layouts.app')
@section('title', $vendor->name)
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php($isAdmin = auth()->user()->isAdmin())
<div class="sd-head">
  <h1>{{ $vendor->name }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('vendors.edit', $vendor) }}">Bearbeiten</a>
    <a class="sd-btn sec" href="{{ route('vendors.index') }}">Alle Lieferanten</a>
  </div>
</div>

<div class="sd-card">
  <h2>Angaben</h2>
  <dl class="sd-dl">
    <dt>Anschrift</dt><dd>{{ $vendor->addressLine() ?: '–' }}</dd>
    <dt>E-Mail</dt><dd>{{ $vendor->email ?: '–' }}</dd>
    <dt>Telefon</dt><dd>{{ $vendor->phone ?: '–' }}</dd>
    <dt>USt-ID</dt><dd>{{ $vendor->vat_id ?: '–' }}</dd>
    <dt>Übliche Kostenart</dt><dd>{{ $vendor->defaultCategoryLabel() }}</dd>
    <dt>Stand</dt><dd>{{ $vendor->active ? 'aktiv' : 'stillgelegt' }}</dd>
    @if ($vendor->note)
      <dt>Notiz</dt><dd style="white-space:pre-line">{{ $vendor->note }}</dd>
    @endif
  </dl>
</div>

<div class="sd-card">
  <h2>Belege</h2>
  <p class="sd-muted" style="margin-top:0">{{ $count }} {{ $count === 1 ? 'Beleg' : 'Belege' }} mit zusammen {{ \App\Support\Money::format($sum) }} brutto (nur Belege, die du sehen darfst).</p>
  @if ($receipts->isNotEmpty())
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Nr.</th><th>Datum</th><th>Eigentümer und Objekt</th><th>Betrag</th></tr></thead>
        <tbody>
          @foreach ($receipts as $receipt)
            <tr>
              <td><a href="{{ route('receipts.show', $receipt) }}">{{ $receipt->id }}</a></td>
              <td style="white-space:nowrap">{{ $receipt->receipt_date?->format('d.m.Y') ?? '–' }}</td>
              <td>{{ $receipt->owner->name }}<br><span class="sd-muted">{{ $receipt->placeLabel() }}</span></td>
              <td style="white-space:nowrap">{{ $receipt->amountLabel() }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    @if ($count > $receipts->count())
      <p class="sd-muted">Die neuesten {{ $receipts->count() }} Belege sind aufgeführt.</p>
    @endif
  @endif
</div>

@if ($isAdmin)
  <div class="sd-card">
    <h2>{{ $vendor->active ? 'Lieferant stilllegen' : 'Lieferant wieder aktivieren' }}</h2>
    <p class="sd-muted">Ein stillgelegter Lieferant erscheint nicht mehr in der Auswahl. Bereits erfasste Belege bleiben unverändert.</p>
    <form method="POST" action="{{ route('vendors.toggle', $vendor) }}">
      @csrf
      <button class="sd-btn {{ $vendor->active ? 'danger' : 'sec' }}" type="submit">{{ $vendor->active ? 'Stilllegen' : 'Wieder aktivieren' }}</button>
    </form>
  </div>
@endif
@endsection
