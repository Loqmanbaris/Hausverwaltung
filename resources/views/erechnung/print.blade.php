<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $inv->kindLabel() }} {{ $inv->number }}</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
*{box-sizing:border-box}
body{font:10.5pt/1.45 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
.sender{font-size:8pt;border-bottom:1px solid #999;display:inline-block;margin-bottom:2mm}
.addr{min-height:30mm;margin-top:5mm}
.meta{text-align:right;margin:2mm 0 6mm}
h1{font-size:14pt;margin:0 0 4mm}
table{width:100%;border-collapse:collapse;margin:3mm 0;font-size:9.5pt}
th,td{padding:1.2mm 1.3mm;border-bottom:.3pt solid #bbb;text-align:left;vertical-align:top}
th{font-size:8.5pt;color:#444;border-bottom:.8pt solid #333}
.n{text-align:right;white-space:nowrap}
.sum td{font-weight:700;border-top:.8pt solid #333;border-bottom:0}
.small{font-size:8.5pt;color:#333}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $de = fn ($x) => $x ? $x->format('d.m.Y') : '–';
  $s = $d['seller'];
  $b = $d['buyer'];
  $sign = $inv->isCredit() ? '−' : '';
@endphp
<div class="toolbar"><button type="button" onclick="window.print()">Drucken / als PDF speichern</button><span>{{ $inv->kindLabel() }} {{ $inv->number }}</span></div>
<div class="page">
  @include('partials.briefkopf', ['bk' => \App\Support\Briefkopf::daten($s)])
  <div class="sender">{{ $s['name'] }} · {{ $s['street'] }} · {{ $s['zip'] }} {{ $s['city'] }}</div>
  <div class="addr">{{ $b['name'] }}<br>{{ $b['street'] }}<br>{{ $b['zip'] }} {{ $b['city'] }}</div>
  <div class="meta">{{ $s['city'] }}, {{ $de($inv->issued_on) }}</div>
  <h1>{{ $inv->kindLabel() }} Nr. {{ $inv->number }}</h1>
  <p class="small">Leistungszeitraum: {{ $de($inv->period_from) }} bis {{ $de($inv->period_to) }}@if ($d['reference']) · Referenz: {{ $d['reference'] }}@endif @if ($d['original']) · zur Rechnung {{ $d['original'] }}@endif</p>
  @if ($d['note'])<p>{{ $d['note'] }}</p>@endif
  <table>
    <thead><tr><th>Position</th><th class="n">Netto</th></tr></thead>
    <tbody>
      @foreach ($d['lines'] as $l)<tr><td>{{ $l['name'] }}</td><td class="n">{{ $sign }}{{ $eur($l['net'] / 100) }}</td></tr>@endforeach
      <tr><td>Summe netto</td><td class="n">{{ $sign }}{{ $eur($inv->net) }}</td></tr>
      <tr><td>Umsatzsteuer {{ rtrim(rtrim(number_format((float) $inv->vat_rate, 2, ',', ''), '0'), ',') }} %</td><td class="n">{{ $sign }}{{ $eur($inv->vat) }}</td></tr>
      <tr class="sum"><td>{{ $inv->isCredit() ? 'Gutschriftbetrag' : 'Rechnungsbetrag' }}</td><td class="n">{{ $sign }}{{ $eur($inv->gross) }}</td></tr>
    </tbody>
  </table>
  @unless ($inv->isCredit())
    <p>Bitte überweisen Sie den Betrag bis zum <strong>{{ $de($inv->due_on) }}</strong> auf: IBAN {{ trim(chunk_split($d['iban'], 4, ' ')) }}@if ($d['bic']), BIC {{ $d['bic'] }}@endif. Verwendungszweck: {{ $inv->number }}.</p>
  @endunless
  <p class="small">Steuerliche Angaben des Rechnungstellers: @if ($s['vat'])USt-IdNr. {{ $s['vat'] }}@endif @if ($s['tax'])Steuernummer {{ $s['tax'] }}@endif @if ($b['vat']) · USt-IdNr. des Mieters {{ $b['vat'] }}@endif</p>
  <p class="small">Dies ist die Ansicht Ihrer elektronischen Rechnung. Die maßgebliche Rechnung ist die mitgesandte XML-Datei (XRechnung).</p>
  @include('partials.brieffuss', ['bk' => \App\Support\Briefkopf::daten($s)])
</div>
</body>
</html>
