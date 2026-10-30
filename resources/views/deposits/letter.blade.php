<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Kautionsabrechnung</title>
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
h1{font-size:13pt;margin:0 0 4mm}
table{width:100%;border-collapse:collapse;margin:3mm 0;font-size:9.5pt}
th,td{padding:1.2mm 1.3mm;border-bottom:.3pt solid #bbb;text-align:left;vertical-align:top}
.n{text-align:right;white-space:nowrap}
.sum td{font-weight:700;border-top:.8pt solid #333;border-bottom:0}
.small{font-size:8.5pt;color:#333}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  $eur = fn ($c) => number_format($c / 100, 2, ',', '.').' €';
  $t = $lease->tenant;
  $name = $t?->type === 'firma' ? $t->company_name : trim(($t?->first_name ?? '').' '.($t?->last_name ?? ''));
  $prop = $lease->unit?->property;
  $settled = $d->settled_on;
  $outEntries = $entries->whereIn('kind', ['einbehalt']);
  $claim = (int) round((float) $d->claim * 100);
@endphp
<div class="toolbar"><button type="button" onclick="window.print()">Drucken / als PDF speichern</button><span>Kautionsabrechnung</span></div>
<div class="page">
  <div class="sender">{{ $owner?->name }} · {{ $owner?->street }} · {{ trim(($owner?->zip ?? '').' '.($owner?->city ?? '')) }}</div>
  <div class="addr">{{ $name }}<br>{{ $t?->street }}<br>{{ trim(($t?->zip ?? '').' '.($t?->city ?? '')) }}</div>
  <div class="meta">{{ $owner?->city ? $owner->city.', ' : '' }}{{ $settled?->format('d.m.Y') }}</div>
  <h1>Abrechnung Ihrer Mietkaution</h1>
  <p>Mietverhältnis: {{ $lease->unit?->label }}, {{ $prop?->addressLine() }}@if ($lease->end_date), Mietende {{ $lease->end_date->format('d.m.Y') }}@endif</p>
  <p>Guten Tag{{ $name !== '' ? ' '.$name : '' }},</p>
  <p>wir rechnen Ihre Mietkaution wie folgt ab:</p>
  <table><tbody>
    <tr><td>Eingezahlte Kaution</td><td class="n">{{ $eur($s['paid']) }}</td></tr>
    @if ($s['interest'] > 0)<tr><td>Zinsen</td><td class="n">{{ $eur($s['interest']) }}</td></tr>@endif
    @foreach ($outEntries as $e)<tr><td>abzüglich: {{ $e->note }}</td><td class="n">− {{ number_format((float) $e->amount, 2, ',', '.') }} €</td></tr>@endforeach
    @if ($s['returned'] > 0)<tr class="sum"><td>Auszahlung an Sie</td><td class="n">{{ $eur($s['returned']) }}</td></tr>@endif
    @if ($claim > 0)<tr class="sum"><td>Offener Betrag zu unseren Gunsten</td><td class="n">{{ $eur($claim) }}</td></tr>@endif
  </tbody></table>
  @if ($s['returned'] > 0)<p>Den Betrag überweisen wir auf das Konto, das Sie uns genannt haben. Falls wir Ihre Bankverbindung noch nicht kennen, teilen Sie sie uns bitte mit.</p>@endif
  @if ($claim > 0)<p>Bitte überweisen Sie den offenen Betrag innerhalb von 14 Tagen.</p>@endif
  <p class="small">Die Einbehalte beziehen sich auf Forderungen aus dem Mietverhältnis. Auf Wunsch erläutern wir sie und legen Belege vor.</p>
  <p style="margin-top:8mm">Mit freundlichen Grüßen<br>{{ $owner?->name }}</p>
</div>
</body>
</html>
