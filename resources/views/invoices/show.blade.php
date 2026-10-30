@extends('layouts.app')
@section('title', $inv->kindLabel().' '.($inv->number ?? 'Entwurf'))
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  $eur = fn ($cents) => number_format($cents / 100, 2, ',', '.').' €';
  $de = fn ($x) => $x ? $x->format('d.m.Y') : '–';
  $b = (array) $inv->buyer;
  $sign = $inv->isCredit() ? '−' : '';
  $num = fn ($v, $d = 2) => rtrim(rtrim(number_format((float) $v, $d, ',', '.'), '0'), ',');
  $units = \App\Support\Rechnung\Berechnung::UNITS;
  $mailBody = "Guten Tag,\n\nanbei erhalten Sie die ".$inv->kindLabel().' '.$inv->number.' über '.$sign.$eur($sum['gross']).".\n".($inv->isCredit() ? '' : 'Bitte überweisen Sie den Betrag bis zum '.$de($inv->due_on).".\n")."\nMit freundlichen Grüßen\n".($seller['name'] ?? '');
@endphp
<div class="sd-head">
  <h1>{{ $inv->kindLabel() }} {{ $inv->number ?? '(Entwurf)' }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('invoices.index') }}">Zur Liste</a></div>
</div>
@if ($errors->has('finalize'))<div class="alert error" style="white-space:pre-line">{{ $errors->first('finalize') }}</div>@endif
@if ($errors->has('mail'))<div class="alert error">{{ $errors->first('mail') }}</div>@endif
<p class="sd-muted">Status: <strong>{{ $inv->statusLabel() }}</strong>@if ($inv->paid_on) · bezahlt am {{ $de($inv->paid_on) }}@endif @if ($original) · Gutschrift zu <a href="{{ route('invoices.show', $original) }}">{{ $original->number }}</a>@endif @if ($credit) · storniert durch <a href="{{ route('invoices.show', $credit) }}">{{ $credit->number }}</a>@endif</p>

@if ($inv->isDraft())
  @if (count($problems) > 0)
    <div class="alert warn"><strong>Vor dem Festschreiben fehlt noch:</strong><ul style="margin:.3rem 0 0 1.1rem">@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul></div>
  @else
    <div class="alert ok">Alle Pflichtangaben sind vorhanden. Du kannst die Rechnung festschreiben.</div>
  @endif
@endif

<div class="sd-card">
  <h2 style="margin-top:0">Empfänger</h2>
  <p style="margin:0">{{ $b['name'] ?? '' }}<br>{{ $b['street'] ?? '' }}<br>{{ $b['zip'] ?? '' }} {{ $b['city'] ?? '' }}@if (! empty($b['email']))<br>{{ $b['email'] }}@endif @if (! empty($b['vat_id']))<br>USt-IdNr. {{ $b['vat_id'] }}@endif</p>
  <p class="sd-muted" style="margin:.4rem 0 0">{{ ! empty($b['business']) ? 'Unternehmen: mit E-Rechnung (XML)' : 'Privatperson: Rechnung als PDF, keine E-Rechnung nötig' }}</p>
</div>

<div class="sd-card" style="overflow-x:auto">
  <h2 style="margin-top:0">Positionen</h2>
  <p class="sd-muted" style="margin-top:0">Leistung: {{ $de($inv->delivery_from) }}@if ($inv->delivery_to && $inv->delivery_to != $inv->delivery_from) bis {{ $de($inv->delivery_to) }}@endif · Rechnungsdatum {{ $de($inv->issued_on) }} · zahlbar bis {{ $de($inv->due_on) }}</p>
  <table style="width:100%;min-width:520px">
    <thead><tr><th>Bezeichnung</th><th style="text-align:right">Menge</th><th style="text-align:right">Einzelpreis</th><th style="text-align:right">USt</th><th style="text-align:right">Netto</th></tr></thead>
    <tbody>
      @foreach ($inv->lines as $l)
        <tr><td>{{ $l->name }}@if ($l->exemption)<br><span class="sd-muted">{{ $l->exemption }}</span>@endif</td><td style="text-align:right">{{ $num($l->quantity, 3) }} {{ $units[$l->unit] ?? '' }}</td><td style="text-align:right">{{ number_format((float) $l->unit_net, 2, ',', '.') }} €</td><td style="text-align:right">{{ $num($l->vat_rate) }} %</td><td style="text-align:right">{{ $eur(\App\Support\Rechnung\Berechnung::lineNet((float) $l->quantity, (float) $l->unit_net)) }}</td></tr>
      @endforeach
      <tr><td colspan="4" style="text-align:right">Summe netto</td><td style="text-align:right">{{ $sign }}{{ $eur($sum['net']) }}</td></tr>
      @foreach ($sum['groups'] as $g)@if ($g['rate'] > 0)<tr><td colspan="4" style="text-align:right">Umsatzsteuer {{ $num($g['rate']) }} %</td><td style="text-align:right">{{ $sign }}{{ $eur($g['vat']) }}</td></tr>@endif @endforeach
      <tr><td colspan="4" style="text-align:right"><strong>{{ $inv->isCredit() ? 'Gutschriftbetrag' : 'Rechnungsbetrag' }}</strong></td><td style="text-align:right"><strong>{{ $sign }}{{ $eur($sum['gross']) }}</strong></td></tr>
    </tbody>
  </table>
  @if ($inv->note)<p style="margin-bottom:0">{{ $inv->note }}</p>@endif
</div>

<div class="sd-card">
  <h2 style="margin-top:0">Aktionen</h2>
  @if ($inv->isDraft())
    <div class="sd-actions" style="margin-bottom:.8rem">
      <a class="sd-btn sec" href="{{ route('invoices.edit', $inv) }}">Bearbeiten</a>
      <a class="sd-btn sec" href="{{ route('invoices.view', $inv) }}" target="_blank" rel="noopener">Vorschau</a>
      <form method="POST" action="{{ route('invoices.destroy', $inv) }}" style="display:inline" onsubmit="return confirm('Entwurf löschen?')">@csrf @method('DELETE')<button class="sd-btn sec" type="submit">Löschen</button></form>
    </div>
    <form method="POST" action="{{ route('invoices.finalize', $inv) }}" style="display:grid;gap:.5rem" onsubmit="return confirm('Festschreiben? Danach ist die Rechnung unveränderlich und bekommt ihre Nummer.')">
      @csrf
      <label for="issued_on">Rechnungsdatum</label>
      <input id="issued_on" name="issued_on" type="date" value="{{ $inv->issued_on?->format('Y-m-d') ?? date('Y-m-d') }}" style="padding:.55rem;border:1px solid #c5ccd3;border-radius:.5rem;font:inherit">
      <button class="sd-btn" type="submit" @disabled(count($problems) > 0)>Festschreiben und Nummer vergeben</button>
      <small class="sd-muted">Festgeschriebene Rechnungen werden nicht geändert und nicht gelöscht (Aufbewahrung 8 Jahre). Fehler korrigierst du mit einer Gutschrift und einer neuen Rechnung.</small>
    </form>
  @else
    <div class="sd-actions" style="margin-bottom:.8rem">
      <a class="sd-btn" href="{{ route('invoices.pdf', $inv) }}">PDF herunterladen</a>
      <a class="sd-btn sec" href="{{ route('invoices.view', $inv) }}" target="_blank" rel="noopener">Ansicht / Drucken</a>
      @if ($inv->xml)<a class="sd-btn sec" href="{{ route('invoices.xml', $inv) }}">E-Rechnung (XML)</a>@endif
      @unless ($inv->isCredit())
        <form method="POST" action="{{ route('invoices.paid', $inv) }}" style="display:inline">@csrf<button class="sd-btn sec" type="submit">{{ $inv->paid_on ? 'Wieder als offen markieren' : 'Als bezahlt markieren' }}</button></form>
      @endunless
      @if ($inv->status === 'festgeschrieben')<form method="POST" action="{{ route('invoices.sent', $inv) }}" style="display:inline">@csrf<button class="sd-btn sec" type="submit">Als versendet markieren</button></form>@endif
      @if (! $inv->isCredit() && $inv->status !== 'storniert')
        <form method="POST" action="{{ route('invoices.credit', $inv) }}" style="display:inline" onsubmit="return confirm('Stornieren? Es wird eine Gutschrift über den ganzen Betrag erstellt.')">@csrf<button class="sd-btn sec" type="submit">Stornieren (Gutschrift)</button></form>
      @endif
    </div>
    <h3 style="margin:.4rem 0">Per E-Mail senden</h3>
    <form method="POST" action="{{ route('invoices.mail', $inv) }}" style="display:grid;gap:.5rem">
      @csrf
      <input name="to" type="email" required value="{{ old('to', $b['email'] ?? '') }}" placeholder="E-Mail-Adresse" style="padding:.55rem;border:1px solid #c5ccd3;border-radius:.5rem;font:inherit">
      <input name="subject" required value="{{ old('subject', $inv->kindLabel().' '.$inv->number) }}" style="padding:.55rem;border:1px solid #c5ccd3;border-radius:.5rem;font:inherit">
      <textarea name="body" rows="6" required style="padding:.55rem;border:1px solid #c5ccd3;border-radius:.5rem;font:inherit">{{ old('body', $mailBody) }}</textarea>
      <button class="sd-btn" type="submit">Senden mit PDF@if ($inv->xml) und XML@endif</button>
    </form>
  @endif
</div>
@endsection
