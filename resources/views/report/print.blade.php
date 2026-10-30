<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Jahresbericht {{ $data['year'] }} {{ $data['owner']->name }}</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
*{box-sizing:border-box}
body{font:10pt/1.45 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
h1{font-size:15pt;margin:0 0 2mm}
h2{font-size:11pt;margin:6mm 0 1mm}
table{width:100%;border-collapse:collapse;margin:2mm 0;font-size:9pt}
th,td{padding:1.1mm 1.3mm;border-bottom:.3pt solid #bbb;text-align:left;vertical-align:top}
th{font-size:8.5pt;color:#444;border-bottom:.8pt solid #333}
.n{text-align:right;white-space:nowrap}
.sum td{font-weight:700;border-top:.8pt solid #333;border-bottom:0}
.small{font-size:8.5pt;color:#333}
tr{page-break-inside:avoid}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $de = fn ($d) => $d ? (is_string($d) ? \Carbon\Carbon::parse($d)->format('d.m.Y') : $d->format('d.m.Y')) : '–';
  $o = $data['owner'];
  $s = $data['summary'];
  $lt = $data['letting_total'];
  $rent = $data['rent'];
  $quote = $lt['units'] > 0 ? round($lt['rented'] / $lt['units'] * 100) : 0;
  $areaQuote = $lt['area'] > 0 ? round($lt['area_rented'] / $lt['area'] * 100) : 0;
@endphp
<div class="toolbar">
  <button type="button" onclick="window.print()">Drucken / als PDF speichern</button>
  <span>Jahresbericht {{ $data['year'] }}</span>
</div>
<div class="page">
  <div class="small">{{ $o->name }} · {{ $o->street }} · {{ trim(($o->zip ?? '').' '.($o->city ?? '')) }}</div>
  <h1>Jahresbericht {{ $data['year'] }}</h1>
  <p class="small">Stand: {{ $de($data['stichtag']) }}{{ $data['is_final'] ? ' (Jahresende)' : ' (Jahr noch nicht abgeschlossen)' }}. Erstellt am {{ date('d.m.Y') }}.</p>

  <h2>1. Ergebnis</h2>
  <table>
    <tbody>
      <tr><td>Einnahmen (Zahlungseingänge, netto, ohne Verzugszinsen)</td><td class="n">{{ $eur($s['income']) }}</td></tr>
      <tr><td>Ausgaben (Belege und Darlehenszinsen)</td><td class="n">− {{ $eur($s['costs']) }}</td></tr>
      @if ($s['afa'] > 0)<tr><td class="small">davon Gebäude-AfA (keine Auszahlung)</td><td class="n small">{{ $eur($s['afa']) }}</td></tr>@endif
      <tr class="sum"><td>Ergebnis</td><td class="n">{{ $eur($s['result']) }}</td></tr>
    </tbody>
  </table>
  <p class="small">Einnahmen nach Zufluss, Ausgaben nach Belegdatum, wie in der Anlage V. Einschließlich Gebäude-AfA, soweit sie beim Objekt hinterlegt ist. Ohne Fahrtkosten und Tilgung.</p>
  @if ($data['rows'] !== [])
    <table>
      <thead><tr><th>Objekt</th><th class="n">Einnahmen</th><th class="n">Ausgaben</th><th class="n">davon Zinsen</th><th class="n">Ergebnis</th></tr></thead>
      <tbody>
        @foreach ($data['rows'] as $r)
          <tr><td>{{ $r['name'] }}</td><td class="n">{{ $eur($r['income']) }}</td><td class="n">{{ $eur($r['costs']) }}</td><td class="n">{{ $eur($r['interest']) }}</td><td class="n">{{ $eur($r['result']) }}</td></tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <h2>2. Vermietung am {{ $de($data['stichtag']) }}</h2>
  @if ($data['letting'] === [])
    <p class="small">Keine Einheiten erfasst.</p>
  @else
    <table>
      <thead><tr><th>Objekt</th><th class="n">Einheiten</th><th class="n">vermietet</th><th class="n">Fläche vermietet</th><th class="n">Jahres-Kaltmiete</th></tr></thead>
      <tbody>
        @foreach ($data['letting'] as $l)
          <tr><td>{{ $l['name'] }}@if ($l['vacant'] !== [])<br><span class="small">leer: {{ implode(', ', $l['vacant']) }}</span>@endif</td><td class="n">{{ $l['units'] }}</td><td class="n">{{ $l['rented'] }}</td><td class="n">{{ number_format($l['area_rented'], 1, ',', '.') }} von {{ number_format($l['area'], 1, ',', '.') }} m²</td><td class="n">{{ $eur($l['cold']) }}</td></tr>
        @endforeach
        <tr class="sum"><td>Gesamt</td><td class="n">{{ $lt['units'] }}</td><td class="n">{{ $lt['rented'] }} ({{ $quote }} %)</td><td class="n">{{ $areaQuote }} % der Fläche</td><td class="n">{{ $eur($lt['cold']) }}</td></tr>
      </tbody>
    </table>
    <p class="small">Jahres-Kaltmiete = heute gültige Kaltmiete, Stellplatz, Garage und Möblierung der laufenden Verträge mal zwölf.</p>
  @endif

  <h2>3. Mieteingang</h2>
  <table>
    <tbody>
      <tr><td>Soll-Miete im Jahr (Kaltmiete und Bestandteile laut Verträgen)</td><td class="n">{{ $eur($rent['soll']) }}</td></tr>
      <tr><td>Zahlungseingänge im Jahr (ohne Übernahmen, NK- und CO2-Gutschriften)</td><td class="n">{{ $eur($rent['received']) }}</td></tr>
      <tr class="sum"><td>Offene Posten bis {{ $de($data['stichtag']) }} (heutiger Stand der Zahlungen)</td><td class="n">{{ $eur($rent['open']) }}</td></tr>
    </tbody>
  </table>
  @if ($rent['open_rows'] !== [])
    <table>
      <thead><tr><th>Mietvertrag</th><th class="n">offen</th></tr></thead>
      <tbody>@foreach ($rent['open_rows'] as $r)<tr><td>{{ $r['label'] }}</td><td class="n">{{ $eur($r['amount']) }}</td></tr>@endforeach</tbody>
    </table>
    @if ($rent['open_count'] > 10)<p class="small">Es werden die 10 größten von {{ $rent['open_count'] }} Verträgen gezeigt.</p>@endif
  @endif

  @if ($data['increases'] !== [])
    <h2>4. Mieterhöhungen im Jahr</h2>
    <table>
      <thead><tr><th>Mietvertrag</th><th>Art</th><th>ab</th><th class="n">Kaltmiete</th></tr></thead>
      <tbody>@foreach ($data['increases'] as $i)<tr><td>{{ $i['label'] }}</td><td>{{ $i['kind'] }}</td><td>{{ $de($i['from']) }}</td><td class="n">{{ $eur($i['old']) }} → {{ $eur($i['new']) }}</td></tr>@endforeach</tbody>
    </table>
  @endif

  <h2>5. Nebenkostenabrechnungen</h2>
  @if ($data['nk'] === [])
    <p class="small">Für {{ $data['year'] }} liegt keine Abrechnung mit Abrechnungszeitraum im Jahr vor.</p>
  @else
    <table>
      <thead><tr><th>Objekt</th><th>Zeitraum</th><th>Stand</th></tr></thead>
      <tbody>@foreach ($data['nk'] as $n)<tr><td>{{ $n['property'] }}</td><td>{{ $de($n['from']) }} bis {{ $de($n['to']) }}</td><td>{{ $n['status'] }}</td></tr>@endforeach</tbody>
    </table>
  @endif

  <h2>6. Darlehen</h2>
  @if ($data['loans'] === [])
    <p class="small">Keine Darlehen erfasst.</p>
  @else
    <table>
      <thead><tr><th>Darlehen</th><th class="n">Zinsen</th><th class="n">Tilgung</th><th class="n">Restschuld</th><th>Zinsbindung bis</th></tr></thead>
      <tbody>@foreach ($data['loans'] as $l)<tr><td>{{ $l['name'] }}@if ($l['lender'])<br><span class="small">{{ $l['lender'] }}</span>@endif</td><td class="n">{{ $eur($l['interest']) }}</td><td class="n">{{ $eur($l['principal']) }}</td><td class="n">{{ $eur($l['balance']) }}</td><td>{{ $de($l['fixed_until']) }}</td></tr>@endforeach</tbody>
    </table>
    <p class="small">Zinsen, Tilgung und Restschuld laut Tilgungsplan im CRM.</p>
  @endif

  <h2>7. Instandhaltung</h2>
  <p>Schäden und Aufträge im Jahr gemeldet: {{ $data['orders']['created'] }}. Im Jahr erledigt: {{ $data['orders']['done'] }}. Noch offen: {{ $data['orders']['open'] }}.</p>
</div>
</body>
</html>
