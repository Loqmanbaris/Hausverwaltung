<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Ratenvereinbarung</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
body{font:10.5pt/1.45 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm;box-sizing:border-box}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
h1{font-size:13pt;margin:0 0 4mm}
table{border-collapse:collapse;margin:3mm 0;min-width:60%}
th,td{border-bottom:.3pt solid #bbb;padding:1.2mm 2mm;text-align:left}
th{font-size:8.5pt;color:#444;border-bottom:.8pt solid #333}
.n{text-align:right}
.signs{display:flex;gap:2rem;margin-top:22mm}
.signs div{flex:1;border-top:1px solid #333;padding-top:1mm;font-size:9pt}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  $l = $plan->lease; $t = $l?->tenant; $o = $l?->owner;
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $account = $o?->bankAccounts()->orderByDesc('is_primary')->first();
@endphp
<div class="toolbar"><button type="button" onclick="window.print()">Drucken / als PDF speichern</button></div>
<div class="page">
  <h1>Ratenzahlungsvereinbarung</h1>
  <p>zwischen <strong>{{ $o?->name }}</strong>, {{ $o?->street }}, {{ trim(($o?->zip ?? '').' '.($o?->city ?? '')) }} (Vermieter)<br>
  und <strong>{{ $t?->type === 'firma' ? $t->company_name : trim(($t?->first_name ?? '').' '.($t?->last_name ?? '')) }}</strong>{{ $t?->street ? ', '.$t->street.', '.trim(($t->zip ?? '').' '.($t->city ?? '')) : '' }} (Mieter)</p>
  <p>Mietverhältnis: {{ $l?->unit?->label }}, {{ $l?->unit?->property?->addressLine() }}</p>
  <p>1. Der Mieter erkennt an, dem Vermieter aus dem Mietverhältnis zum {{ $plan->agreed_on->format('d.m.Y') }} einen Betrag von <strong>{{ $eur($plan->debt) }}</strong> zu schulden.</p>
  <p>2. Der Vermieter gestattet dem Mieter, diesen Betrag in folgenden Raten zu zahlen:</p>
  <table>
    <thead><tr><th>Rate</th><th>fällig am</th><th class="n">Betrag</th></tr></thead>
    <tbody>
      @foreach ($plan->installments as $i)
        <tr><td>{{ $i->no }}.</td><td>{{ $i->due_date->format('d.m.Y') }}</td><td class="n">{{ $eur($i->amount) }}</td></tr>
      @endforeach
    </tbody>
  </table>
  <p>3. Die Raten sind zusätzlich zur laufenden Miete zu zahlen{{ $account ? ' auf das Konto '.$account->account_holder.', IBAN '.$account->formattedIban() : '' }}. Die laufende Miete ist weiterhin pünktlich zu entrichten.</p>
  <p>4. Gerät der Mieter mit einer Rate oder mit der laufenden Miete in Verzug, wird der gesamte noch offene Betrag sofort fällig.</p>
  <p>5. Rechte des Vermieters aus dem Mietvertrag, insbesondere das Recht zur Kündigung bei erneutem Zahlungsverzug, bleiben unberührt.</p>
  @if ($plan->note)<p>6. {!! nl2br(e($plan->note)) !!}</p>@endif
  <p>{{ $o?->city ?: '________' }}, den {{ $plan->agreed_on->format('d.m.Y') }}</p>
  <div class="signs"><div>Vermieter</div><div>Mieter</div></div>
</div>
</body>
</html>
