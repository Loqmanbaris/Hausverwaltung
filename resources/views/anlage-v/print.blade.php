<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Anlage V {{ $year }} {{ $owner->name }}</title>
<style>
@page{size:A4;margin:16mm 16mm 16mm 20mm}
body{font:10pt/1.4 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:16mm 16mm 16mm 20mm}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
h1{font-size:14pt;margin:0 0 2mm}
h2{font-size:11pt;margin:7mm 0 1mm;page-break-after:avoid}
table{width:100%;border-collapse:collapse;page-break-inside:avoid}
td{padding:1.1mm 1.4mm;border-bottom:.3pt solid #ccc;vertical-align:top}
.n{text-align:right;white-space:nowrap;width:30mm}
.s td{font-weight:700;border-top:.8pt solid #333}
.small{font-size:8pt;color:#444}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  use App\Support\Tax\AnlageV;
  $eur = fn ($c) => number_format($c / 100, 2, ',', '.').' €';
@endphp
<div class="toolbar"><button type="button" onclick="window.print()">Drucken / als PDF speichern</button></div>
<div class="page">
  <h1>Anlage V {{ $year }} – Zusammenstellung</h1>
  <div>{{ $owner->name }}@if ($owner->street), {{ $owner->street }}, {{ trim($owner->zip.' '.$owner->city) }}@endif</div>
  <p class="small">Arbeitshilfe für den Steuerberater, erstellt am {{ now()->timezone('Europe/Berlin')->format('d.m.Y') }}. Einnahmen nach Zahlungseingang ({{ $year }}), aufgeteilt nach den bezahlten Monaten und deren Bestandteilen. Werbungskosten nach Belegdatum. Ohne AfA.</p>
  <table><tbody>
    <tr><td>Einnahmen gesamt</td><td class="n">{{ $eur($data['totals']['income']) }}</td></tr>
    <tr><td>Werbungskosten gesamt (ohne AfA)</td><td class="n">{{ $eur($data['totals']['costs']) }}</td></tr>
    <tr class="s"><td>Überschuss</td><td class="n">{{ $eur($data['totals']['result']) }}</td></tr>
  </tbody></table>

  @foreach ($data['properties'] as $o)
    <h2>{{ $o['property']->name }}, {{ $o['property']->addressLine() }}</h2>
    <table><tbody>
      @foreach (AnlageV::INCOME as $k => $label)
        @if ($o['income'][$k] !== 0)<tr><td>{{ $label }}</td><td class="n">{{ $eur($o['income'][$k]) }}</td></tr>@endif
      @endforeach
      <tr class="s"><td>Summe Einnahmen</td><td class="n">{{ $eur($o['income_total']) }}</td></tr>
      @foreach (AnlageV::COSTS as $k => $label)
        @if ($o['costs'][$k] !== 0)<tr><td>{{ $label }}<br><span class="small">@foreach ($o['cost_details'][$k] ?? [] as $l => $c){{ $l }} {{ $eur($c) }}{{ $loop->last ? '' : ' · ' }}@endforeach</span></td><td class="n">{{ $eur($o['costs'][$k]) }}</td></tr>@endif
      @endforeach
      <tr class="s"><td>Summe Werbungskosten</td><td class="n">{{ $eur($o['cost_total']) }}</td></tr>
      <tr class="s"><td>Überschuss (ohne AfA)</td><td class="n">{{ $eur($o['result']) }}</td></tr>
    </tbody></table>
  @endforeach

  @if (($data['unassigned']['receipts'] + ($data['unassigned']['loans'] ?? 0)) > 0)
    <h2>Kosten ohne Objekt (noch aufzuteilen)</h2>
    <table><tbody>
      @foreach (AnlageV::COSTS as $k => $label)
        @if ($data['unassigned']['costs'][$k] !== 0)<tr><td>{{ $label }}</td><td class="n">{{ $eur($data['unassigned']['costs'][$k]) }}</td></tr>@endif
      @endforeach
    </tbody></table>
  @endif
</div>
</body>
</html>
