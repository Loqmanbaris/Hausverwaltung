<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ \App\Support\Interessenten\Letters::title($kind) }}</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
*{box-sizing:border-box}
body{font:10.5pt/1.45 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
.sender{font-size:8pt;border-bottom:1px solid #999;display:inline-block;margin-bottom:2mm}
.addr{min-height:30mm;margin-top:5mm}
.meta{text-align:right;margin:2mm 0 6mm}
h1{font-size:13pt;margin:0 0 4mm}
table{width:100%;border-collapse:collapse;margin:3mm 0;font-size:9.5pt}
th,td{padding:1.2mm 1.3mm;border-bottom:.3pt solid #bbb;text-align:left;vertical-align:top}
.n{text-align:right;white-space:nowrap}
.small{font-size:8.5pt;color:#333}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $de = fn ($d) => \Carbon\Carbon::parse($d)->format('d.m.Y');
  $deposit = $listing?->deposit;
@endphp
<div class="toolbar">
  <button type="button" onclick="window.print()">Drucken / als PDF speichern</button>
  <span>{{ \App\Support\Interessenten\Letters::title($kind) }}</span>
</div>
<div class="page">
  <div class="sender">{{ $owner?->name }} · {{ $owner?->street }} · {{ trim(($owner?->zip ?? '').' '.($owner?->city ?? '')) }}</div>
  <div class="addr">{{ $a->name() }}<br>@if ($a->email){{ $a->email }}@endif</div>
  <div class="meta">{{ $owner?->city ? $owner->city.', ' : '' }}{{ $de($date) }}</div>

  <h1>{{ \App\Support\Interessenten\Letters::title($kind) }}</h1>
  <p>Wohnung: {{ $unit?->label }}{{ $property ? ', '.$property->addressLine() : '' }}</p>
  <p>{{ $salutation }}</p>

  @if ($kind === 'zusage')
    <p>vielen Dank für Ihr Interesse und die netten Gespräche. Wir freuen uns, Ihnen mitteilen zu können, dass wir Ihnen die oben genannte Wohnung vermieten möchten.</p>
    <p>Vorgesehen sind folgende Bedingungen:</p>
    <table><tbody>
      @if ($listing?->rent_cold !== null)<tr><td>Kaltmiete</td><td class="n">{{ $eur($listing->rent_cold) }} im Monat</td></tr>@endif
      @if ($listing?->service_charge !== null)<tr><td>Vorauszahlung Nebenkosten</td><td class="n">{{ $eur($listing->service_charge) }} im Monat</td></tr>@endif
      @if ($listing?->heating_charge !== null)<tr><td>Vorauszahlung Heizkosten</td><td class="n">{{ $eur($listing->heating_charge) }} im Monat</td></tr>@endif
      @if (! empty($deposit))<tr><td>Kaution</td><td class="n">{{ is_numeric($deposit) ? $eur($deposit) : $deposit }}</td></tr>@endif
      <tr><td>Mietbeginn</td><td class="n">{{ $a->move_in ? 'ab '.$a->move_in->format('d.m.Y') : 'nach Absprache' }}</td></tr>
    </tbody></table>
    <p>Bitte teilen Sie uns innerhalb einer Woche mit, ob Sie die Wohnung zu diesen Bedingungen mieten möchten. Für den Mietvertrag benötigen wir dann noch einen Nachweis über Ihr Einkommen (zum Beispiel eine aktuelle Gehaltsabrechnung) und, falls vorhanden, eine Bescheinigung Ihres bisherigen Vermieters.</p>
    <p class="small">Diese Zusage ist noch kein Mietvertrag. Der Vertrag kommt erst zustande, wenn beide Seiten ihn unterschrieben haben.</p>
  @else
    <p>vielen Dank für Ihr Interesse an der Wohnung und für die Zeit, die Sie sich genommen haben.</p>
    <p>Leider müssen wir Ihnen mitteilen, dass wir die Wohnung nicht an Sie vermieten können. Wir haben uns für eine andere Bewerbung entschieden. Das ist keine Bewertung Ihrer Person.</p>
    <p>Wir wünschen Ihnen bei Ihrer Suche viel Erfolg.</p>
    <p class="small">Ihre Angaben (Kontaktdaten und gegebenenfalls Selbstauskunft) löschen wir spätestens {{ $keep_months }} Monate nach diesem Schreiben.</p>
  @endif

  <p style="margin-top:8mm">Mit freundlichen Grüßen<br>{{ $owner?->name }}</p>
</div>
</body>
</html>
