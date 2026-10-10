<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Nebenkostenabrechnung {{ $settlement->property?->name }} {{ $settlement->periodLabel() }}</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
*{box-sizing:border-box}
body{font:10.5pt/1.4 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm;page-break-after:always}
.page:last-child{page-break-after:auto}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
.sender{font-size:8pt;border-bottom:1px solid #999;display:inline-block;margin-bottom:2mm}
.addr{min-height:30mm;margin-top:5mm}
.meta{text-align:right;margin:2mm 0 6mm}
h1{font-size:13pt;margin:0 0 4mm}
table{width:100%;border-collapse:collapse;margin:3mm 0}
th,td{padding:1.2mm 1.3mm;border-bottom:.3pt solid #bbb;text-align:left;vertical-align:top}
th{font-size:8.5pt;color:#444;border-bottom:.8pt solid #333}
.n{text-align:right;white-space:nowrap}
td:first-child{width:34%}
table{font-size:9.5pt}
.sum td{font-weight:700;border-top:.8pt solid #333;border-bottom:0}
.res td{font-size:11.5pt;font-weight:700;border-bottom:0}
.small{font-size:8.5pt;color:#333}
.box{border:.6pt solid #999;padding:3mm;margin-top:4mm;page-break-inside:avoid}
tr{page-break-inside:avoid}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  use App\Support\Kostenarten;
  use App\Support\Nk\NkCalculator;
  use App\Support\Nk\NkService;
  $eur = fn ($cents) => number_format($cents / 100, 2, ',', '.').' €';
  $owner = $settlement->owner;
  $account = $owner?->bankAccounts()->orderByDesc('is_primary')->first();
  $issued = $settlement->issued_on ?? now();
  $due = $issued->copy()->addDays(30);
  $basisLabel = ['flaeche' => 'Fläche', 'einheiten' => 'Einheiten', 'personen' => 'Personen', 'wasser' => 'Wasser', 'heizung_verbrauch' => 'Heizung', 'warmwasser_verbrauch' => 'Warmwasser', 'direkt' => 'direkt'];
@endphp
<div class="toolbar">
  <button type="button" onclick="window.print()">Drucken / als PDF speichern</button>
  <span>{{ count($leaseIds) }} Abrechnung{{ count($leaseIds) === 1 ? '' : 'en' }}@unless ($settlement->isFinal()) · <strong>Entwurf</strong>: Zahlen können sich noch ändern @endunless</span>
</div>

@foreach ($leaseIds as $id)
  @php($l = $result['leases'][$id])
  @php($lease = $leases->get($id))
  @php($tenant = $lease?->tenant)
  @php($contact = $tenant?->type === 'firma' ? $tenant->contacts->firstWhere('receives_invoices', true) : null)
  <div class="page">
    <div class="sender">{{ $owner?->name }} · {{ $owner?->street }} · {{ trim(($owner?->zip ?? '').' '.($owner?->city ?? '')) }}</div>
    <div class="addr">
      {{ $tenant?->type === 'firma' ? $tenant->company_name : trim(($tenant?->first_name ?? '').' '.($tenant?->last_name ?? '')) }}<br>
      @if ($contact) z. Hd. {{ $contact->name }}<br>@endif
      {{ $tenant?->street ?: $settlement->property?->street }}<br>
      {{ trim(($tenant?->zip ?: $settlement->property?->zip).' '.($tenant?->city ?: $settlement->property?->city)) }}
    </div>
    <div class="meta">{{ $owner?->city ? $owner->city.', ' : '' }}{{ $issued->format('d.m.Y') }}</div>

    <h1>Nebenkostenabrechnung {{ $settlement->periodLabel() }}</h1>
    <p>
      Objekt: {{ $settlement->property?->name }}, {{ $settlement->property?->addressLine() }}<br>
      Mieteinheit: {{ $l['unit'] }}<br>
      Abrechnungszeitraum: {{ $settlement->period_from->format('d.m.Y') }} bis {{ $settlement->period_to->format('d.m.Y') }} ({{ $result['days'] }} Tage)<br>
      Ihr Nutzungszeitraum: {{ \Carbon\Carbon::parse($l['from'])->format('d.m.Y') }} bis {{ \Carbon\Carbon::parse($l['to'])->format('d.m.Y') }} ({{ $l['days'] }} Tage)
    </p>
    <p>Sehr geehrte Damen und Herren,<br>nachstehend erhalten Sie die Abrechnung der Betriebskosten für den oben genannten Zeitraum.</p>

    <table>
      <thead><tr><th>Kostenart</th><th class="n">Gesamtkosten</th><th>Schlüssel</th><th class="n">Gesamt</th><th class="n">Ihr Anteil</th><th class="n">Ihre Kosten</th></tr></thead>
      <tbody>
        @foreach ($result['lines'] as $li => $line)
          @continue(! isset($l['lines'][$li]))
          @php($mine = $l['lines'][$li])
          <tr>
            <td>{{ $line['label'] }}@if ($line['part'])<br><span class="small">{{ $line['part'] }}</span>@endif @if ($line['commercial_percent'])<br><span class="small">nach Vorwegabzug Gewerbe {{ rtrim(rtrim(number_format((float) $line['commercial_percent'], 2, ',', ''), '0'), ',') }} %</span>@endif</td>
            <td class="n">{{ $eur($line['cents']) }}</td>
            <td>{{ $basisLabel[$line['basis']] ?? $line['basis'] }}</td>
            <td class="n">{{ $line['basis'] === 'direkt' ? '–' : NkCalculator::formatBase($line['basis'], (float) $line['base_total']) }}</td>
            <td class="n">{{ $line['basis'] === 'direkt' ? '–' : NkCalculator::formatBase($line['basis'], (float) $mine['base']) }}</td>
            <td class="n">{{ $eur($mine['cents']) }}</td>
          </tr>
        @endforeach
        <tr class="sum"><td colspan="5">Summe Ihrer Kosten</td><td class="n">{{ $eur($l['cost_cents']) }}</td></tr>
        <tr><td colspan="5">abzüglich Ihrer Vorauszahlungen laut Mietvertrag</td><td class="n">− {{ $eur($l['prepaid_cents']) }}</td></tr>
        <tr class="res"><td colspan="5">{{ $l['result_cents'] > 0 ? 'Nachzahlung' : ($l['result_cents'] < 0 ? 'Guthaben' : 'Ergebnis') }}</td><td class="n">{{ $eur(abs($l['result_cents'])) }}</td></tr>
      </tbody>
    </table>

    @if ($l['result_cents'] > 0)
      <p>Bitte überweisen Sie die Nachzahlung bis zum <strong>{{ $due->format('d.m.Y') }}</strong>@if ($account) auf das Konto {{ $account->account_holder }}, IBAN {{ $account->formattedIban() }}{{ $account->bank_name ? ' ('.$account->bank_name.')' : '' }}@endif. Verwendungszweck: Nebenkosten {{ $settlement->period_from->format('Y') }}, {{ $l['unit'] }}.</p>
    @elseif ($l['result_cents'] < 0)
      <p>Das Guthaben verrechnen wir mit Ihrer nächsten Mietzahlung.</p>
    @endif

    @if (count($l['c35a']) > 0)
      <div class="box small">
        <strong>Angaben für Ihre Steuererklärung (§ 35a EStG, haushaltsnahe Dienstleistungen und Handwerkerleistungen)</strong><br>
        In Ihren Kosten sind folgende Beträge enthalten:
        @foreach ($l['c35a'] as $key => $cents) {{ Kostenarten::label($key) }} {{ $eur($cents) }}{{ $loop->last ? '.' : ',' }} @endforeach
        Begünstigt ist nur der Anteil für Arbeits- und Fahrtkosten; er ergibt sich aus den Rechnungen, die Sie einsehen können.
      </div>
    @endif

    @php($co2 = $result['co2'] ?? null)
    @if ($co2 && ! ($co2['missing'] ?? true) && isset($l['co2']))
      <div class="box small">
        <strong>Aufteilung der CO2-Kosten (Kohlendioxidkostenaufteilungsgesetz)</strong><br>
        Brennstoff: {{ $co2['fuel'] }}. CO2-Ausstoß des Gebäudes im Abrechnungszeitraum: {{ number_format($co2['kg'], 1, ',', '.') }} kg bei {{ number_format($co2['area'], 2, ',', '.') }} m² Wohnfläche, das sind {{ number_format($co2['kg_per_sqm'], 1, ',', '.') }} kg CO2 je m² und Jahr.
        Einstufung: {{ $co2['step_label'] }}. Danach tragen der Mieter {{ \App\Support\Nk\Co2Split::pct($co2['tenant_pct']) }} und der Vermieter {{ \App\Support\Nk\Co2Split::pct($co2['landlord_pct']) }} der CO2-Kosten.@if ($co2['restriction'] !== 'keine') Der Anteil des Vermieters ist nach § 9 CO2KostAufG {{ $co2['restriction'] === 'halb' ? 'halbiert' : 'entfallen' }} ({{ $co2['restriction_note'] }}).@endif<br>
        CO2-Kosten des Gebäudes: {{ $eur($co2['cost_cents']) }}. Davon entfallen auf Sie (verteilt wie die Heizkosten) {{ $eur($l['co2']['share_cents']) }}. Der Anteil des Vermieters von {{ $eur($l['co2']['landlord_cents']) }} ist oben bereits abgezogen; Ihr Anteil an den CO2-Kosten beträgt {{ $eur($l['co2']['tenant_cents']) }}.
      </div>
    @endif

    @if ($result['settings']['heating_mode'] === 'zaehler' && isset($result['units'][$l['unit_id']]['consumption_prev']))
      @php($u = $result['units'][$l['unit_id']])
      @php($sumC = 0.0)
      @php($sumA = 0.0)
      @foreach ($result['units'] as $ux)
        @if (($ux['consumption']['heizung'] ?? null) !== null && (float) $ux['area'] > 0)
          @php($sumC += (float) $ux['consumption']['heizung'])
          @php($sumA += (float) $ux['area'])
        @endif
      @endforeach
      <div class="box small">
        <strong>Angaben zu Heizung und Warmwasser (§ 6a Heizkostenverordnung)</strong><br>
        Energieträger: {{ ($result['settings']['fuel'] ?? null) ? (\App\Models\NkSetting::FUELS[$result['settings']['fuel']] ?? '–').' (100 %)' : '–' }}. Steuern und Abgaben (z. B. Energiesteuer, CO2-Preis, Umsatzsteuer) sind in den Brennstoffkosten laut Rechnung des Versorgers enthalten.<br>
        Heizverbrauch der Einheit im Abrechnungszeitraum: {{ ($u['consumption']['heizung'] ?? null) !== null ? number_format((float) $u['consumption']['heizung'], 1, ',', '.').' '.($u['heat_unit'] ?? '') : '–' }};
        im Vorjahreszeitraum: {{ ($u['consumption_prev']['heizung'] ?? null) !== null ? number_format((float) $u['consumption_prev']['heizung'], 1, ',', '.').' '.($u['heat_unit'] ?? '') : 'keine Werte' }};
        Durchschnitt im Haus für eine Einheit dieser Größe: {{ $sumA > 0 && (float) $u['area'] > 0 ? number_format($sumC / $sumA * (float) $u['area'], 1, ',', '.').' '.($u['heat_unit'] ?? '') : '–' }}.<br>
        Energieberatung: Verbraucherzentrale (verbraucherzentrale-energieberatung.de). Bei Streit über die Abrechnung können Sie sich an eine Verbraucherschlichtungsstelle wenden, z. B. die Universalschlichtungsstelle des Bundes (verbraucher-schlichter.de).
      </div>
    @endif

    <p class="small" style="margin-top:5mm">
      @if ($result['settings']['heating_mode'] === 'zaehler') Heiz- und Warmwasserkosten wurden nach der Heizkostenverordnung zu {{ $result['settings']['share'] }} % nach Verbrauch und zu {{ 100 - $result['settings']['share'] }} % nach Wohnfläche verteilt. @endif
      Kosten für Zeiten, in denen Einheiten leer standen, trägt der Vermieter. Die Belege können Sie nach Absprache einsehen. Einwendungen gegen die Abrechnung sind spätestens bis zum Ablauf des zwölften Monats nach Zugang dieser Abrechnung mitzuteilen.
    </p>
    @if ($settlement->note)<p style="white-space:pre-line">{{ $settlement->note }}</p>@endif
    <p>Mit freundlichen Grüßen<br><br>{{ $owner?->name }}</p>
  </div>
@endforeach
</body>
</html>
