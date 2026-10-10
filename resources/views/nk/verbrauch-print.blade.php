<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Verbrauchsinformation {{ $info['label'] }}</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
*{box-sizing:border-box}
body{font:10.5pt/1.45 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm;page-break-after:always}
.page:last-child{page-break-after:auto}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
.sender{font-size:8pt;border-bottom:1px solid #999;display:inline-block;margin-bottom:2mm}
.addr{min-height:30mm;margin-top:5mm}
.meta{text-align:right;margin:2mm 0 6mm}
h1{font-size:13pt;margin:0 0 4mm}
table{width:100%;border-collapse:collapse;margin:3mm 0;font-size:10pt}
th,td{padding:1.3mm 1.4mm;border-bottom:.3pt solid #bbb;text-align:left;vertical-align:top}
th{font-size:8.5pt;color:#444;border-bottom:.8pt solid #333}
.n{text-align:right;white-space:nowrap}
.small{font-size:8.5pt;color:#333}
.box{border:.6pt solid #999;padding:3mm;margin-top:4mm}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  use App\Support\Nk\Verbrauchsinfo as V;
  $owner = $property->owner;
@endphp
<div class="toolbar"><button type="button" onclick="window.print()">Drucken / als PDF speichern</button> {{ count($rows) }} Mieter</div>
@foreach ($rows as $r)
  @php($lease = $r['lease'])
  @php($tenant = $lease?->tenant)
  <div class="page">
    <div class="sender">{{ $owner?->name }} · {{ $owner?->street }} · {{ trim(($owner?->zip ?? '').' '.($owner?->city ?? '')) }}</div>
    <div class="addr">
      {{ $tenant?->type === 'firma' ? $tenant->company_name : trim(($tenant?->first_name ?? '').' '.($tenant?->last_name ?? '')) }}<br>
      {{ $tenant?->street ?: $property->street }}<br>
      {{ trim(($tenant?->zip ?: $property->zip).' '.($tenant?->city ?: $property->city)) }}
    </div>
    <div class="meta">{{ $owner?->city ? $owner->city.', ' : '' }}{{ now()->format('d.m.Y') }}</div>

    <h1>Ihr Verbrauch im {{ $info['label'] }}</h1>
    <p>Mietobjekt: {{ $property->addressLine() }}, {{ $r['unit']->label }}{{ $r['area'] > 0 ? ' ('.number_format($r['area'], 2, ',', '.').' m²)' : '' }}<br>
    Monatliche Verbrauchsinformation nach § 6a Heizkostenverordnung</p>

    <table>
      <thead><tr><th></th><th class="n">{{ $info['label'] }}</th><th class="n">Vormonat</th><th class="n">Vorjahresmonat</th></tr></thead>
      <tbody>
        @if ($r['heat'])
          <tr><td>Heizung ({{ $r['heat_unit'] }})</td><td class="n">{{ V::fmt($r['heat']['value']) }}</td><td class="n">{{ V::fmt($r['heat']['prev']) }}{{ V::change($r['heat']['value'], $r['heat']['prev']) ? ' ('.V::change($r['heat']['value'], $r['heat']['prev']).')' : '' }}</td><td class="n">{{ V::fmt($r['heat']['last_year']) }}{{ V::change($r['heat']['value'], $r['heat']['last_year']) ? ' ('.V::change($r['heat']['value'], $r['heat']['last_year']).')' : '' }}</td></tr>
        @endif
        @if ($r['water'])
          <tr><td>Warmwasser (m³)</td><td class="n">{{ V::fmt($r['water']['value'], 2) }}</td><td class="n">{{ V::fmt($r['water']['prev'], 2) }}</td><td class="n">{{ V::fmt($r['water']['last_year'], 2) }}</td></tr>
        @endif
      </tbody>
    </table>

    @if ($r['heat'])
      <p><strong>Vergleich:</strong>
        @if (($r['heat']['bench'] ?? null) !== null) Ein durchschnittlicher Nutzer mit Ihrer Wohnfläche verbraucht im {{ V::MONTHS[(int) substr($info['month'], 5, 2)] }} etwa {{ V::fmt($r['heat']['bench']) }} kWh (Vergleichswert {{ V::fmt($info['benchmark']) }} kWh je m² und Jahr, nach Gradtagszahlen auf den Monat verteilt). @endif
        @if (($r['heat']['avg'] ?? null) !== null) Im Durchschnitt des Hauses entfallen auf eine Wohnung Ihrer Größe {{ V::fmt($r['heat']['avg']) }} {{ $r['heat_unit'] }}. @endif
        @if (($r['heat']['bench'] ?? null) === null && ($r['heat']['avg'] ?? null) === null) Für diesen Monat liegt noch kein Vergleichswert vor. @endif
      </p>
    @endif
    @if ($r['heat'] && $r['heat']['value'] === null || $r['water'] && $r['water']['value'] === null)
      <p class="small">„–“: Für diesen Zeitraum liegen keine vollständigen Zählerstände vor.</p>
    @endif

    <p>Mit freundlichen Grüßen<br><br>{{ $owner?->name }}</p>
    <div class="box small">
      @if ($r['heat_unit'] === 'Einheiten')Heizkostenverteiler zeigen Verbrauchseinheiten, keine kWh; sie eignen sich zum Vergleich mit Ihren eigenen Vormonaten und mit anderen Wohnungen im Haus.<br>@endif
      Tipps zum Energiesparen und eine unabhängige Energieberatung bietet die Verbraucherzentrale (verbraucherzentrale-energieberatung.de). Die Kosten werden wie bisher einmal im Jahr mit der Heizkostenabrechnung abgerechnet.
    </div>
  </div>
@endforeach
</body>
</html>
