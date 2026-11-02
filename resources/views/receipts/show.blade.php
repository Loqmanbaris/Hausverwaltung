@extends('layouts.app')
@section('title', 'Beleg Nr. '.$receipt->id)
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.rc-warn{background:var(--brass-bg);border:1px solid var(--brass-line);color:var(--brass);padding:.7rem 1rem;border-radius:.5rem;margin-bottom:1rem}
.rc-pages{display:grid;grid-template-columns:repeat(auto-fit,minmax(16rem,1fr));gap:1rem}
.rc-page{border:1px solid var(--line);border-radius:.6rem;padding:.7rem;background:var(--surface)}
.rc-page img{display:block;max-width:100%;height:auto;border-radius:.3rem;background:var(--surface-2)}
.rc-pdfbox{display:flex;align-items:center;justify-content:center;height:8rem;background:var(--surface-2);border-radius:.3rem;font-weight:700;color:var(--muted)}
.rc-page .sd-actions{margin-top:.6rem}
</style>
@php($isAdmin = auth()->user()->isAdmin())
@php($missing = $receipt->missing())

<div class="sd-head">
  <h1>Beleg Nr. {{ $receipt->id }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('receipts.edit', $receipt) }}">Bearbeiten</a>
    <a class="sd-btn sec" href="{{ route('receipts.index') }}">Alle Belege</a>
  </div>
</div>

@if (session('warning'))
  <div class="rc-warn">{{ session('warning') }}</div>
@endif

@if ($missing !== [])
  <div class="rc-warn">
    <strong>Dieser Beleg ist unvollständig.</strong> Es fehlt: {{ implode(', ', $missing) }}.
    <a href="{{ route('receipts.edit', $receipt) }}">Jetzt ergänzen</a>
  </div>
@endif

<div class="sd-card">
  <h2>Angaben</h2>
  <dl class="sd-dl">
    <dt>Eigentümer</dt><dd><a href="{{ route('owners.show', $receipt->owner) }}">{{ $receipt->owner->name }}</a></dd>
    <dt>Zuordnung</dt>
    <dd>
      @if ($receipt->property && ! $receipt->owner_level)
        <a href="{{ route('properties.show', $receipt->property) }}">{{ $receipt->property->name }}</a>@if ($receipt->unit), {{ $receipt->unit->label }}@endif
      @else
        {{ $receipt->placeLabel() }}
      @endif
    </dd>
    <dt>Datum</dt><dd>{{ $receipt->receipt_date?->format('d.m.Y') ?? '–' }}</dd>
    <dt>Betrag brutto</dt><dd>{{ $receipt->amountLabel() }}</dd>
    <dt>Kostenart</dt><dd>{{ $receipt->categoryLabel() }}</dd>
    <dt>Lieferant</dt>
    <dd>
      @if ($receipt->vendor_id && $receipt->vendor_name)
        <a href="{{ route('vendors.show', $receipt->vendor_id) }}">{{ $receipt->vendor_name }}</a>
      @else
        {{ $receipt->vendor_name ?: '–' }}
      @endif
    </dd>
    @if ($receipt->note)
      <dt>Notiz</dt><dd style="white-space:pre-line">{{ $receipt->note }}</dd>
    @endif
    <dt>Erfasst</dt>
    <dd>{{ $receipt->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}@if ($receipt->creator) von {{ $receipt->creator->name }}@endif</dd>
  </dl>
</div>

@if ($receipt->isEInvoice())
<div class="sd-card">
  <h2>Angaben aus der E-Rechnung</h2>
  <dl class="sd-dl">
    <dt>Format</dt><dd>{{ $receipt->einvoice_format ?: 'E-Rechnung' }}</dd>
    <dt>Rechnungsnummer</dt><dd>{{ $receipt->invoice_number ?: '–' }}</dd>
    <dt>Fällig am</dt><dd>{{ $receipt->due_date?->format('d.m.Y') ?? '–' }}</dd>
    <dt>Betrag netto</dt><dd>{{ $receipt->net_amount === null ? '–' : \App\Support\Money::format($receipt->net_amount) }}</dd>
    <dt>Umsatzsteuer</dt><dd>{{ $receipt->tax_amount === null ? '–' : \App\Support\Money::format($receipt->tax_amount) }}</dd>
  </dl>
  <p class="sd-muted" style="margin-bottom:0">Diese Angaben stammen aus der Rechnungsdatei. Wenn du oben etwas änderst, bleibt die Originaldatei unverändert.</p>
</div>
@endif

<div class="sd-card">
  <h2>Dateien ({{ $receipt->files->count() }})</h2>
  <p class="sd-muted" style="margin-top:0">Die Dateien eines Belegs können nicht geändert werden. Sie liegen verschlüsselt im geschützten Speicher.</p>
  <div class="rc-pages">
    @foreach ($receipt->files as $file)
      <div class="rc-page">
        @if ($file->isImage())
          <a href="{{ route('receipts.file', [$receipt, $file]) }}" target="_blank" rel="noopener">
            <img src="{{ route('receipts.file', [$receipt, $file]) }}" alt="Seite {{ $file->position }}" loading="lazy">
          </a>
        @else
          <div class="rc-pdfbox">{{ $file->isXml() ? 'XML' : 'PDF' }}</div>
        @endif
        <div style="margin-top:.4rem">Seite {{ $file->position }} <span class="sd-muted">({{ \App\Support\Backup\BackupService::formatSize((int) $file->size_bytes) }})</span></div>
        <div class="sd-actions">
          <a class="sd-btn sec small" href="{{ route('receipts.file', [$receipt, $file]) }}" target="_blank" rel="noopener">Öffnen</a>
          <a class="sd-btn sec small" href="{{ route('receipts.file', [$receipt, $file, 'herunterladen' => 1]) }}">Herunterladen</a>
        </div>
      </div>
    @endforeach
  </div>
</div>

@php($paidBy = \Illuminate\Support\Facades\DB::table('bank_transaction_receipts as l')->join('bank_transactions as t', 't.id', '=', 'l.bank_transaction_id')->where('l.receipt_id', $receipt->id)->orderBy('t.booking_date')->get(['t.id', 't.booking_date', 't.counterparty_name', 'l.amount']))
<div class="sd-card">
  <h2>Bezahlt laut Kontoauszug</h2>
  @if ($paidBy->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keiner Abbuchung zugeordnet. Das geht unter <a href="{{ route('bank.expenses') }}">Umsätze → Ausgaben zuordnen</a>.</p>
  @else
    <ul style="margin:0;padding-left:1.2rem">
      @foreach ($paidBy as $pb)
        <li><a href="{{ route('bank.expenses', ['stand' => 'alle']) }}#abbuchung-{{ $pb->id }}">{{ \Carbon\Carbon::parse($pb->booking_date)->format('d.m.Y') }}</a> · {{ $pb->counterparty_name }} · {{ \App\Support\Money::format($pb->amount) }}</li>
      @endforeach
    </ul>
  @endif
</div>

@if ($isAdmin)
  <div class="sd-card">
    <h2>Beleg archivieren</h2>
    <p class="sd-muted">Der Beleg verschwindet aus den Listen. Die Dateien bleiben aufbewahrt, im Protokoll steht, wer ihn archiviert hat.</p>
    <form method="POST" action="{{ route('receipts.destroy', $receipt) }}" onsubmit="return confirm('Diesen Beleg wirklich archivieren?')">
      @csrf @method('DELETE')
      <button class="sd-btn danger" type="submit">Beleg archivieren</button>
    </form>
  </div>
@endif
@endsection
