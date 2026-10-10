<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Erstattung CO2-Kosten</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
*{box-sizing:border-box}
body{font:10.5pt/1.45 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
.sender{font-size:8pt;border-bottom:1px solid #999;display:inline-block;margin-bottom:2mm}
.addr{min-height:30mm;margin-top:5mm}
.meta{text-align:right;margin:2mm 0 6mm}
h1{font-size:13pt;margin:0 0 4mm}
table{width:100%;border-collapse:collapse;margin:3mm 0;font-size:10pt}
td{padding:1.2mm 1.3mm;border-bottom:.3pt solid #bbb;vertical-align:top}
.n{text-align:right;white-space:nowrap}
.small{font-size:8.5pt;color:#333}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  use App\Support\Money;
  $lease = $claim->lease;
  $tenant = $lease?->tenant;
  $owner = $claim->owner;
@endphp
<div class="toolbar"><button type="button" onclick="window.print()">Drucken / als PDF speichern</button></div>
<div class="page">
  <div class="sender">{{ $owner?->name }} · {{ $owner?->street }} · {{ trim(($owner?->zip ?? '').' '.($owner?->city ?? '')) }}</div>
  <div class="addr">
    {{ $tenant?->type === 'firma' ? $tenant->company_name : trim(($tenant?->first_name ?? '').' '.($tenant?->last_name ?? '')) }}<br>
    {{ $tenant?->street ?: $lease?->unit?->property?->street }}<br>
    {{ trim(($tenant?->zip ?: $lease?->unit?->property?->zip).' '.($tenant?->city ?: $lease?->unit?->property?->city)) }}
  </div>
  <div class="meta">{{ $owner?->city ? $owner->city.', ' : '' }}{{ now()->format('d.m.Y') }}</div>

  <h1>Erstattung des Vermieteranteils an den CO2-Kosten</h1>
  <p>Mietobjekt: {{ $lease?->unit?->property?->addressLine() }}, {{ $lease?->unit?->label }}<br>
  Rechnung Ihres Versorgers für den Zeitraum {{ $claim->period_from->format('d.m.Y') }} bis {{ $claim->period_to->format('d.m.Y') }}</p>
  <p>{{ \App\Support\Mail\Outbox::salutation($tenant) }}<br>
  nach dem Kohlendioxidkostenaufteilungsgesetz tragen Vermieter einen Teil der CO2-Kosten Ihrer Heizung. Auf Grundlage Ihrer Angaben ergibt sich:</p>
  <table>
    <tr><td>CO2-Ausstoß laut Rechnung</td><td class="n">{{ number_format((float) $claim->co2_kg, 1, ',', '.') }} kg</td></tr>
    <tr><td>Wohnfläche</td><td class="n">{{ number_format((float) $claim->area, 2, ',', '.') }} m²</td></tr>
    <tr><td>CO2-Ausstoß je m² und Jahr</td><td class="n">{{ number_format((float) $claim->kg_per_sqm, 1, ',', '.') }} kg</td></tr>
    <tr><td>Einstufung</td><td class="n">{{ $claim->building_use === 'nichtwohn' ? 'Nichtwohngebäude, hälftige Teilung' : 'Stufe '.$claim->step.' ('.\App\Support\Nk\Co2Split::stepLabel($claim->step).')' }}</td></tr>
    <tr><td>Anteil Vermieter</td><td class="n">{{ $claim->landlord_pct }} %@if ($claim->restriction !== 'keine') (nach § 9 CO2KostAufG {{ $claim->restriction === 'halb' ? 'halbiert' : 'entfallen' }})@endif</td></tr>
    <tr><td>CO2-Kosten laut Rechnung</td><td class="n">{{ Money::format($claim->co2_cost) }}</td></tr>
    @if ($claim->gas_stove)<tr><td>Kürzung um 5 % wegen Gasherd am selben Zähler</td><td class="n">berücksichtigt</td></tr>@endif
    <tr><td><strong>Erstattung an Sie</strong></td><td class="n"><strong>{{ Money::format($claim->amount) }}</strong></td></tr>
  </table>
  @if ($claim->status === 'gutgeschrieben')
    <p>Den Betrag haben wir Ihrem Mietkonto gutgeschrieben; er wird mit der nächsten Mietzahlung verrechnet.</p>
  @elseif ($claim->status === 'ausgezahlt')
    <p>Den Betrag haben wir am {{ $claim->settled_on?->format('d.m.Y') }} an Sie überwiesen.</p>
  @else
    <p>Den Betrag erstatten wir Ihnen bzw. verrechnen ihn mit der nächsten Abrechnung.</p>
  @endif
  <p>Mit freundlichen Grüßen<br><br>{{ $owner?->name }}</p>
</div>
</body>
</html>
