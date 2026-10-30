@extends('layouts.app')
@section('title', 'Rechnungen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $chip = ['entwurf' => '#6b7280', 'festgeschrieben' => '#1d4ed8', 'versendet' => '#0f8b8d', 'storniert' => '#b42318'];
@endphp
<div class="sd-head">
  <h1>Rechnungen</h1>
  <div class="sd-actions"><a class="sd-btn" href="{{ route('invoices.create') }}">Neue Rechnung</a> <a class="sd-btn sec" href="{{ route('invoices.settings') }}">Rechnungsdaten</a></div>
</div>
@if (count($issues) > 0)
  <div class="alert warn"><strong>Bevor du Rechnungen festschreiben kannst, fehlt bei den Rechnungsdaten noch:</strong>
    <ul style="margin:.3rem 0 .4rem 1.1rem">@foreach ($issues as $i)<li>{{ $i }}</li>@endforeach</ul>
    <a class="sd-btn sec small" href="{{ route('invoices.settings') }}">Rechnungsdaten ergänzen</a>
  </div>
@endif
<p class="sd-muted">Offen: <strong>{{ $openCount }}</strong> Rechnung(en), zusammen <strong>{{ $eur($openSum) }}</strong>. Mietrechnungen an Gewerbemieter mit Umsatzsteuer stellst du weiter unter <a href="{{ route('erechnung.index') }}">E-Rechnung ausstellen</a> aus.</p>
<div class="sd-card" style="overflow-x:auto">
  @if ($invoices->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine Rechnung. Tippe oben auf „Neue Rechnung".</p>
  @else
    <table style="width:100%;min-width:640px">
      <thead><tr><th>Nummer</th><th>Datum</th><th>Empfänger</th><th style="text-align:right">Brutto</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @foreach ($invoices as $i)
          <tr>
            <td>{{ $i->number ?? 'Entwurf' }}@if ($i->isCredit()) <span class="sd-muted">(Gutschrift)</span>@endif</td>
            <td>{{ $i->issued_on?->format('d.m.Y') ?? '–' }}</td>
            <td>{{ ((array) $i->buyer)['name'] ?? '' }}</td>
            <td style="text-align:right">{{ $i->isCredit() ? '−' : '' }}{{ $eur($i->gross) }}</td>
            <td><span style="color:{{ $chip[$i->status] ?? '#6b7280' }};font-weight:600">{{ $i->statusLabel() }}</span>@if ($i->paid_on) <span class="sd-muted">· bezahlt {{ $i->paid_on->format('d.m.') }}</span>@endif</td>
            <td><a class="sd-btn sec small" href="{{ route('invoices.show', $i) }}">Öffnen</a></td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
