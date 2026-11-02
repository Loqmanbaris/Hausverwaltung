<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $inv->kindLabel() }} {{ $inv->number ?? '(Entwurf)' }}</title>
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
  $eur = fn ($cents) => number_format($cents / 100, 2, ',', '.').' €';
  $de = fn ($x) => $x ? $x->format('d.m.Y') : '–';
  $s = (array) ($inv->seller ?: \App\Support\Rechnung\Aussteller::snapshot());
  $b = (array) $inv->buyer;
  $sign = $inv->isCredit() ? '−' : '';
  $num = fn ($v, $d = 2) => rtrim(rtrim(number_format((float) $v, $d, ',', '.'), '0'), ',');
  $units = \App\Support\Rechnung\Berechnung::UNITS;
  $title = $inv->kindLabel().($inv->number ? ' Nr. '.$inv->number : ' (Entwurf)');
@endphp
<div class="toolbar"><button type="button" onclick="window.print()">Drucken / als PDF speichern</button><span>{{ $title }}</span></div>
<div class="page">
  @include('partials.briefkopf', ['bk' => \App\Support\Briefkopf::daten($s)])
  <div class="sender">{{ $s['name'] }} · {{ $s['street'] }} · {{ $s['zip'] }} {{ $s['city'] }}</div>
  <div class="addr">{{ $b['name'] ?? '' }}<br>{{ $b['street'] ?? '' }}<br>{{ $b['zip'] ?? '' }} {{ $b['city'] ?? '' }}</div>
  <div class="meta">{{ $s['city'] }}, {{ $de($inv->issued_on) }}</div>
  <h1>{{ $title }}</h1>
  <p class="small">@if ($inv->delivery_from)Leistungsdatum: {{ $de($inv->delivery_from) }}@if ($inv->delivery_to && $inv->delivery_to != $inv->delivery_from) bis {{ $de($inv->delivery_to) }}@endif @endif @if ($inv->buyer_reference) · Referenz: {{ $inv->buyer_reference }}@endif @if ($inv->original_id) · zur Rechnung (Storno)@endif</p>
  @if ($inv->note)<p>{{ $inv->note }}</p>@endif
  <table>
    <thead><tr><th>Pos.</th><th>Bezeichnung</th><th class="n">Menge</th><th class="n">Einzelpreis</th><th class="n">USt</th><th class="n">Netto</th></tr></thead>
    <tbody>
      @foreach ($inv->lines as $i => $l)
        @php($net = \App\Support\Rechnung\Berechnung::lineNet((float) $l->quantity, (float) $l->unit_net))
        <tr><td>{{ $i + 1 }}</td><td>{{ $l->name }}</td><td class="n">{{ $num($l->quantity, 3) }} {{ $units[$l->unit] ?? '' }}</td><td class="n">{{ $sign }}{{ number_format((float) $l->unit_net, 2, ',', '.') }} €</td><td class="n">{{ $num($l->vat_rate) }} %</td><td class="n">{{ $sign }}{{ $eur($net) }}</td></tr>
      @endforeach
      <tr><td></td><td>Summe netto</td><td></td><td></td><td></td><td class="n">{{ $sign }}{{ $eur($sum['net']) }}</td></tr>
      @foreach ($sum['groups'] as $g)
        @if ($g['rate'] > 0)<tr><td></td><td>Umsatzsteuer {{ $num($g['rate']) }} % auf {{ $eur($g['net']) }}</td><td></td><td></td><td></td><td class="n">{{ $sign }}{{ $eur($g['vat']) }}</td></tr>@endif
      @endforeach
      <tr class="sum"><td></td><td>{{ $inv->isCredit() ? 'Gutschriftbetrag' : 'Rechnungsbetrag' }}</td><td></td><td></td><td></td><td class="n">{{ $sign }}{{ $eur($sum['gross']) }}</td></tr>
    </tbody>
  </table>
  @foreach ($sum['groups'] as $g)
    @if ($g['rate'] <= 0)<p class="small">Leistungen mit 0 % Umsatzsteuer ({{ $eur($g['net']) }} netto): {{ $g['exemption'] }}</p>@endif
  @endforeach
  @if (($s['tax_mode'] ?? 'regel') === 'klein')<p class="small">Gemäß § 19 UStG wird keine Umsatzsteuer berechnet.</p>@endif
  @unless ($inv->isCredit())
    <p>Bitte überweisen Sie den Betrag bis zum <strong>{{ $de($inv->due_on) }}</strong> auf: IBAN {{ trim(chunk_split((string) $s['iban'], 4, ' ')) }}@if (! empty($s['bic'])), BIC {{ $s['bic'] }}@endif ({{ $s['holder'] ?? $s['name'] }}). Verwendungszweck: {{ $inv->number ?? 'Rechnungsnummer' }}.</p>
  @endunless
  @if (! empty($s['note']))<p class="small">{{ $s['note'] }}</p>@endif
  <p class="small">{{ $s['name'] }} · @if (! empty($s['vat_id']))USt-IdNr. {{ $s['vat_id'] }}@endif @if (! empty($s['tax_number']))Steuernummer {{ $s['tax_number'] }}@endif @if (! empty($b['vat_id'])) · USt-IdNr. des Empfängers {{ $b['vat_id'] }}@endif · {{ $s['phone'] }} · {{ $s['email'] }}</p>
  @if ($inv->xml)<p class="small">Die maßgebliche Rechnung ist die mitgesandte XML-Datei (XRechnung). Diese Seite ist die lesbare Ansicht.</p>@endif
  @include('partials.brieffuss', ['bk' => \App\Support\Briefkopf::daten($s)])
</div>
</body>
</html>
