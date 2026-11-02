@extends('layouts.app')
@section('title', 'E-Rechnung')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €'; @endphp
<div class="sd-head"><h1>E-Rechnung</h1></div>
<div class="sd-card">
  <h2>Wann ist das nötig?</h2>
  <p style="margin:0">Nur wenn du Räume <strong>umsatzsteuerpflichtig</strong> an ein <strong>Unternehmen</strong> vermietest (Gewerbe oder Montage-Wohnung mit Umsatzsteuer). Dann musst du Rechnungen elektronisch im Format XRechnung oder ZUGFeRD ausstellen: ab 1. Januar 2027, wenn dein Umsatz im Vorjahr über 800.000 € lag, sonst ab 1. Januar 2028. Steuerfreie Wohnraumvermietung ist nicht betroffen. Ob dein bestehender Mietvertrag weiter als Rechnung gilt, hängt vom Einzelfall ab. Bitte mit dem Steuerberater klären.</p>
  <p class="sd-muted" style="margin:.5rem 0 0">Das CRM erzeugt XRechnung 3.0 (UBL) und prüft jede Datei gegen das offizielle Schema. Die inhaltlichen Geschäftsregeln der Finanzverwaltung (KoSIT) prüft es nicht. Teste die ersten Dateien deshalb mit einem Prüfdienst oder mit deiner Steuerberater-Software.</p>
</div>
@if ($errors->has('einvoice'))<div class="sd-err">{{ $errors->first('einvoice') }}</div>@endif

@if ($leases->isEmpty())
  <div class="sd-card"><p class="sd-muted" style="margin:0">Zurzeit gibt es keinen Mietvertrag mit Umsatzsteuer und einem Unternehmen als Mieter. Dann brauchst du nichts zu tun.</p></div>
@else
  @foreach ($issues as $i)
    <div class="sd-card" style="border-color:var(--brass-line);background:var(--brass-bg)">
      <strong>{{ $i['owner']->name }}: Angaben für die Rechnung fehlen</strong>
      <ul style="margin:.4rem 0 0;padding-left:1.2rem">@foreach ($i['items'] as $m)<li>{{ $m }}</li>@endforeach</ul>
      <p style="margin:.4rem 0 0"><a href="{{ route('owners.edit', $i['owner']) }}">Eigentümer bearbeiten</a></p>
    </div>
  @endforeach

  <div class="sd-card">
    <h2>Noch nicht abgerechnet</h2>
    @if ($pending->isEmpty())
      <p class="sd-muted" style="margin:0">Alle Monatsmieten der letzten Monate haben eine Rechnung.</p>
    @else
      <div class="sd-table-wrap"><table class="sd">
        <thead><tr><th>Mietvertrag</th><th>Monat</th><th>Fällig</th><th class="rt-num">Brutto</th><th></th></tr></thead>
        <tbody>
          @foreach ($pending as $c)
            <tr><td>{{ $c->lease?->title() }}</td><td>{{ $c->period->format('m/Y') }}</td><td>{{ $c->due_date->format('d.m.Y') }}</td><td class="rt-num">{{ $eur($c->total) }}</td>
              <td><form method="POST" action="{{ route('erechnung.create', $c) }}">@csrf<button class="sd-btn small" type="submit">Rechnung erstellen</button></form></td></tr>
          @endforeach
        </tbody>
      </table></div>
      <form method="POST" action="{{ route('erechnung.create_all') }}" style="margin-top:.6rem">@csrf<button class="sd-btn sec" type="submit">Alle fälligen Rechnungen erstellen</button></form>
      <p class="sd-muted" style="margin:.4rem 0 0">Rechnungsnummern laufen fortlaufend (R2027-0001 und so weiter). Erstellte Rechnungen werden nie gelöscht, nur durch eine Gutschrift storniert.</p>
    @endif
  </div>
@endif

<div class="sd-card">
  <h2>Erstellte Rechnungen</h2>
  @if ($invoices->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine Rechnung erstellt.</p>
  @else
    <div class="sd-table-wrap"><table class="sd">
      <thead><tr><th>Nummer</th><th>Mietvertrag</th><th class="rt-num">Brutto</th><th>Stand</th><th></th></tr></thead>
      <tbody>
        @foreach ($invoices as $inv)
          <tr>
            <td>{{ $inv->number }}<br><span class="sd-muted">{{ $inv->issued_on->format('d.m.Y') }} · {{ $inv->kindLabel() }}</span></td>
            <td>{{ $inv->lease?->title() }}</td>
            <td class="rt-num">{{ $inv->isCredit() ? '−' : '' }}{{ $eur($inv->gross) }}</td>
            <td>{{ $inv->statusLabel() }}{{ $inv->sent_on ? ' am '.$inv->sent_on->format('d.m.Y') : '' }}</td>
            <td style="white-space:nowrap">
              <a class="sd-btn sec small" href="{{ route('erechnung.xml', $inv) }}">XML</a>
              <a class="sd-btn sec small" href="{{ route('erechnung.view', $inv) }}" target="_blank" rel="noopener">Ansicht</a>
              <a class="sd-btn sec small" href="{{ route('mail.compose', ['art' => 'erechnung', 'ids' => $inv->id]) }}">E-Mail</a>
              @if (! $inv->isCredit() && $inv->status !== 'storniert')
                <form method="POST" action="{{ route('erechnung.credit', $inv) }}" style="display:inline" onsubmit="return confirm('Rechnung durch eine Gutschrift stornieren?')">@csrf<button class="sd-btn sec small" type="submit">Stornieren</button></form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table></div>
  @endif
</div>
@endsection
