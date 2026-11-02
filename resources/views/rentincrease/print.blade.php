<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ \App\Support\Rent\Erhoehung\Schreiben::title($inc) }}</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
*{box-sizing:border-box}
body{font:10.5pt/1.45 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
.draft{background:#fff3cd;color:#664d03;padding:.4rem 1rem;font-size:.9rem}
.sender{font-size:8pt;border-bottom:1px solid #999;display:inline-block;margin-bottom:2mm}
.addr{min-height:30mm;margin-top:5mm}
.meta{text-align:right;margin:2mm 0 6mm}
h1{font-size:13pt;margin:0 0 4mm}
h2{font-size:11pt;margin:5mm 0 2mm}
table{width:100%;border-collapse:collapse;margin:3mm 0;font-size:9.5pt}
th,td{padding:1.2mm 1.3mm;border-bottom:.3pt solid #bbb;text-align:left;vertical-align:top}
th{font-size:8.5pt;color:#444;border-bottom:.8pt solid #333}
.n{text-align:right;white-space:nowrap}
.sum td{font-weight:700;border-top:.8pt solid #333;border-bottom:0}
.box{border:1px solid #999;padding:3mm;margin-top:5mm}
.small{font-size:8.5pt;color:#333}
tr{page-break-inside:avoid}
@media print{body{background:#fff}.toolbar,.draft{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  use App\Support\Rent\Erhoehung\Regeln;
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $de = fn ($d) => $d ? Regeln::de(is_string($d) ? $d : $d->format('Y-m-d')) : '–';
  $kind = $inc->kind;
  $tl = $timeline;
  $diff = (float) $inc->new_cold - (float) $inc->old_cold;
  $pct = (float) $inc->old_cold > 0 ? $diff / (float) $inc->old_cold * 100 : 0;
  $num = fn ($v, $dec = 2) => number_format((float) $v, $dec, ',', '.');
  $name = $tenant?->type === 'firma' ? $tenant->company_name : trim(($tenant?->first_name ?? '').' '.($tenant?->last_name ?? ''));
@endphp
<div class="toolbar">
  <button type="button" onclick="window.print()">Drucken / als PDF speichern</button>
  <span>{{ \App\Support\Rent\Erhoehung\Schreiben::title($inc) }}</span>
</div>
@if ($inc->isDraft())<div class="draft">Entwurf: Das Schreiben ist noch nicht als versendet vermerkt. Bitte nach dem Versand den Zugang im Verfahren eintragen.</div>@endif

<div class="page">
  @include('partials.briefkopf', ['bk' => \App\Support\Briefkopf::daten($owner)])
  <div class="sender">{{ $owner?->name }} · {{ $owner?->street }} · {{ trim(($owner?->zip ?? '').' '.($owner?->city ?? '')) }}</div>
  <div class="addr">
    {{ $name }}<br>
    @if ($contact) z. Hd. {{ $contact->name }}<br>@endif
    {{ $tenant?->street ?: $property?->street }}<br>
    {{ trim(($tenant?->zip ?: $property?->zip).' '.($tenant?->city ?: $property?->city)) }}
  </div>
  <div class="meta">{{ $owner?->city ? $owner->city.', ' : '' }}{{ $inc->notice_on->format('d.m.Y') }}</div>

  <h1>{{ \App\Support\Rent\Erhoehung\Schreiben::title($inc) }}</h1>
  <p>Mietverhältnis: {{ $unit?->label }}, {{ $property?->addressLine() }}</p>
  <p>{{ $salutation }}</p>

  @if ($kind === 'vergleich')
    <p>hiermit bitten wir Sie, einer Erhöhung der monatlichen Nettokaltmiete ab dem <strong>{{ $de($inc->effective_from) }}</strong> von {{ $eur($inc->old_cold) }} auf <strong>{{ $eur($inc->new_cold) }}</strong> zuzustimmen. Die Erhöhung beträgt {{ $eur($diff) }} ({{ $num($pct) }} %).</p>
  @elseif ($kind === 'modernisierung')
    <p>wir haben in Ihrer Wohnung bzw. im Gebäude folgende Modernisierungsmaßnahme durchgeführt: {{ $data['measure'] ?? '' }}@if (! empty($data['completed_on'])) (abgeschlossen am {{ $de($data['completed_on']) }})@endif.@if (! empty($data['announced_on'])) Wir hatten sie Ihnen am {{ $de($data['announced_on']) }} angekündigt.@endif Deshalb erhöhen wir die monatliche Nettokaltmiete ab dem <strong>{{ $de($inc->effective_from) }}</strong> von {{ $eur($inc->old_cold) }} auf <strong>{{ $eur($inc->new_cold) }}</strong> (Erhöhung {{ $eur($diff) }}).</p>
  @elseif ($kind === 'index')
    <p>Ihr Mietvertrag enthält eine Indexmiete. Wir erhöhen die monatliche Nettokaltmiete deshalb ab dem <strong>{{ $de($inc->effective_from) }}</strong> von {{ $eur($inc->old_cold) }} auf <strong>{{ $eur($inc->new_cold) }}</strong> (Erhöhung {{ $eur($diff) }}).</p>
  @elseif ($kind === 'staffel')
    <p>wir erinnern Sie an die im Mietvertrag vereinbarte Staffelmiete: Ab dem <strong>{{ $de($inc->effective_from) }}</strong> beträgt die monatliche Nettokaltmiete <strong>{{ $eur($inc->new_cold) }}</strong> (bisher {{ $eur($inc->old_cold) }}, Erhöhung {{ $eur($diff) }}). Dafür ist keine weitere Erklärung von Ihnen nötig.</p>
  @else
    <p>wir schlagen Ihnen vor, die monatliche Nettokaltmiete ab dem <strong>{{ $de($inc->effective_from) }}</strong> von {{ $eur($inc->old_cold) }} auf <strong>{{ $eur($inc->new_cold) }}</strong> anzuheben (Erhöhung {{ $eur($diff) }}). Die übrigen Bestandteile der Miete bleiben unverändert. Die Änderung wird wirksam, wenn Sie zustimmen.</p>
  @endif

  <h2>Ihre monatliche Miete</h2>
  <table>
    <thead><tr><th>Bestandteil</th><th class="n">bisher</th><th class="n">ab {{ $de($inc->effective_from) }}</th></tr></thead>
    <tbody>
      @foreach ($components as $col => $label)
        @continue((float) $old_parts[$col] == 0 && (float) $new_parts[$col] == 0)
        <tr><td>{{ $label }}</td><td class="n">{{ $eur($old_parts[$col]) }}</td><td class="n">{{ $eur($new_parts[$col]) }}</td></tr>
      @endforeach
      @if ($vat > 0)
        <tr><td>Summe netto</td><td class="n">{{ $eur($old_net) }}</td><td class="n">{{ $eur($new_net) }}</td></tr>
        <tr><td>Umsatzsteuer {{ $num($vat, 0) }} %</td><td class="n">{{ $eur($old_net * $vat / 100) }}</td><td class="n">{{ $eur($new_net * $vat / 100) }}</td></tr>
        <tr class="sum"><td>Summe brutto</td><td class="n">{{ $eur($old_net * (1 + $vat / 100)) }}</td><td class="n">{{ $eur($new_gross) }}</td></tr>
      @else
        <tr class="sum"><td>Summe</td><td class="n">{{ $eur($old_net) }}</td><td class="n">{{ $eur($new_net) }}</td></tr>
      @endif
    </tbody>
  </table>
  @if ($area)<p class="small">Wohnfläche {{ $num($area) }} m²: Kaltmiete je m² bisher {{ $eur($old_sqm) }}, künftig {{ $eur($new_sqm) }}.</p>@endif

  {{-- ============ Begründung ============ --}}
  @if ($kind === 'vergleich')
    @php
      $basis = $data['basis_type'] ?? 'mietspiegel';
      $cap = $data['cap'] ?? null;
      $last = $data['last_change'] ?? null;
      $modDelta = (int) ($cap['mod_delta'] ?? 0);
    @endphp
    <h2>Begründung</h2>
    @if (in_array($basis, ['mietspiegel', 'qualifiziert'], true))
      <p>Wir begründen das Verlangen mit dem {{ $basis === 'qualifiziert' ? 'qualifizierten ' : '' }}Mietspiegel {{ $data['source_name'] ?? '' }}.@if (! empty($data['source_detail'])) Ihre Wohnung gehört danach zu: {{ $data['source_detail'] }}.@endif @if (! empty($data['range_from']) && ! empty($data['range_to'])) Die Spanne der ortsüblichen Vergleichsmiete liegt dort bei {{ $num($data['range_from']) }} € bis {{ $num($data['range_to']) }} € je m² und Monat.@endif @if (! empty($data['comparable_sqm'])) Für Ihre Wohnung ist nach Lage und Ausstattung ein Wert von {{ $num($data['comparable_sqm']) }} € je m² und Monat angemessen.@endif @if ($area) Die neue Nettokaltmiete beträgt {{ $eur($new_sqm) }} je m² und Monat.@endif</p>
    @elseif ($basis === 'mietdatenbank')
      <p>Wir begründen das Verlangen mit einer Auskunft aus der Mietdatenbank {{ $data['source_name'] ?? '' }}.@if (! empty($data['source_detail'])) Merkmale der Wohnung: {{ $data['source_detail'] }}.@endif @if (! empty($data['comparable_sqm'])) Die ortsübliche Vergleichsmiete beträgt danach {{ $num($data['comparable_sqm']) }} € je m² und Monat.@endif</p>
    @elseif ($basis === 'gutachten')
      <p>Wir begründen das Verlangen mit einem Gutachten: {{ $data['source_name'] ?? '' }}.@if (! empty($data['comparable_sqm'])) Danach beträgt die ortsübliche Vergleichsmiete {{ $num($data['comparable_sqm']) }} € je m² und Monat.@endif</p>
    @else
      <p>Wir begründen das Verlangen mit folgenden Vergleichswohnungen ({{ $data['source_name'] ?? '' }}):</p>
    @endif
    @if (! empty($data['comparables']))<p style="white-space:pre-line">{{ $data['comparables'] }}</p>@endif
    @if ($last)<p>Die Kaltmiete ist seit dem {{ $de($last['from']) }} unverändert.</p>@endif
    @if ($cap)<p>Die Kappungsgrenze von {{ $cap['percent'] }} % innerhalb von drei Jahren (§ 558 Abs. 3 BGB) wird eingehalten: Vor drei Jahren betrug die Kaltmiete {{ $eur($cap['base'] / 100) }}, sie darf höchstens auf {{ $eur($cap['limit'] / 100) }} steigen @if ($modDelta > 0) (einschließlich {{ $eur($cap['mod_delta'] / 100) }} Modernisierungszuschlag, der nicht mitzählt)@endif.</p>@endif

  @elseif ($kind === 'modernisierung')
    @php
      $mc = $data['calc'] ?? [];
      $hasSubsidies = (int) ($mc['subsidies'] ?? 0) > 0;
      $wasLimited = ! empty($mc['limited']);
    @endphp
    <h2>Berechnung</h2>
    <table>
      <tbody>
        <tr><td>Für Ihre Wohnung aufgewendete Kosten</td><td class="n">{{ $eur(($mc['costs'] ?? 0) / 100) }}</td></tr>
        @if (! empty($mc['halved']))<tr><td>abzüglich der Hälfte der Kosten, weil für die Wärmepumpe kein Nachweis einer Jahresarbeitszahl von mindestens 2,5 vorliegt (§ 559f Abs. 2 BGB)</td><td class="n">− {{ $eur(($mc['halved_amount'] ?? 0) / 100) }}</td></tr>@endif
        <tr><td>abzüglich Anteil für ohnehin fällige Instandhaltung ({{ $num($mc['upkeep_percent'] ?? 0, 0) }} %)</td><td class="n">− {{ $eur(($mc['upkeep'] ?? 0) / 100) }}</td></tr>
        @if ($hasSubsidies)<tr><td>abzüglich Fördermittel und Zuschüsse (§ 559a BGB)</td><td class="n">− {{ $eur($mc['subsidies'] / 100) }}</td></tr>@endif
        <tr><td>umlagefähige Kosten</td><td class="n">{{ $eur(($mc['base'] ?? 0) / 100) }}</td></tr>
        <tr><td>jährliche Erhöhung: {{ $mc['percent'] ?? 8 }} % davon</td><td class="n">{{ $eur(($mc['yearly'] ?? 0) / 100) }}</td></tr>
        <tr class="sum"><td>monatliche Erhöhung (Jahresbetrag geteilt durch 12)</td><td class="n">{{ $eur(($mc['monthly'] ?? 0) / 100) }}</td></tr>
      </tbody>
    </table>
    @if ($wasLimited)<p>Wegen der gesetzlichen Kappungsgrenze beschränken wir die Erhöhung auf {{ $eur(($mc['allowed'] ?? 0) / 100) }} im Monat.</p>@endif
    @if (! empty($mc['cap_sqm']))<p>Die Kappungsgrenze (§ 559 Abs. 3a BGB) von {{ $eur($mc['cap_sqm'] / 100) }} je m² Wohnfläche innerhalb von sechs Jahren wird eingehalten.@if (! empty($data['heating'])) Für den Heizungstausch gilt zusätzlich höchstens 0,50 € je m² (§ 559e BGB).@endif</p>@endif
    @if (! empty($data['simplified']))<p>Wir haben das vereinfachte Verfahren nach § 559c BGB gewählt. Der Erhaltungsanteil ist deshalb pauschal mit 30 % berücksichtigt.</p>@endif
    @if (! empty($data['delayed']))<p>Da die Maßnahme nicht oder nicht rechtzeitig angekündigt wurde bzw. die Erhöhung die Ankündigung um mehr als 10 % übersteigt, gilt die Erhöhung erst sechs Monate später (§ 559b Abs. 2 BGB).</p>@endif

  @elseif ($kind === 'index')
    @php
      $wasCapped = isset($data['applied_percent'], $data['percent']) && $data['applied_percent'] < $data['percent'] - 0.0001;
    @endphp
    <h2>Berechnung</h2>
    <p>Der vom Statistischen Bundesamt ermittelte Verbraucherpreisindex für Deutschland betrug {{ ! empty($data['base_month']) ? 'im '.substr($data['base_month'], 5, 2).'/'.substr($data['base_month'], 0, 4).' ' : '' }}{{ isset($data['base_index']) ? $num($data['base_index'], 1) : '–' }}, im {{ ! empty($data['index_month']) ? substr($data['index_month'], 5, 2).'/'.substr($data['index_month'], 0, 4) : '–' }} beträgt er {{ isset($data['new_index']) ? $num($data['new_index'], 1) : '–' }}. Er hat sich damit um {{ isset($data['percent']) ? $num($data['percent']) : '–' }} % verändert.@if ($wasCapped) Berücksichtigt wird davon nach der gesetzlichen Begrenzung eine Veränderung von {{ $num($data['applied_percent']) }} %.@endif</p>
  @endif

  {{-- ============ Fristen und Hinweise ============ --}}
  @if ($kind === 'vergleich')
    <h2>Zustimmung und Fristen</h2>
    <p>Bitte stimmen Sie der Erhöhung bis zum Ablauf des <strong>{{ $de($tl['consent_until']) }}</strong> zu. Stimmen Sie zu, schulden Sie die erhöhte Miete ab dem {{ $de($inc->effective_from) }}. Stimmen Sie nicht zu, können wir innerhalb von drei weiteren Monaten (bis {{ $de($tl['suit_until']) }}) Klage auf Zustimmung erheben (§ 558b Abs. 2 BGB).</p>
    <p class="small">Hinweis: Sie können das Mietverhältnis bis zum Ablauf des {{ $de($tl['tenant_terminates_by']) }} außerordentlich zum Ablauf des {{ $de($tl['tenant_terminates_to']) }} kündigen (§ 561 BGB). Die Erhöhung tritt dann nicht ein.</p>
  @elseif ($kind === 'modernisierung')
    <h2>Wirkung und Hinweise</h2>
    <p>Die erhöhte Miete ist ab dem {{ $de($inc->effective_from) }} zu zahlen (§ 559b Abs. 2 BGB). Eine Zustimmung ist nicht erforderlich.</p>
    <p class="small">Hinweise: Sie können das Mietverhältnis bis zum Ablauf des {{ $de($tl['tenant_terminates_by']) }} außerordentlich zum Ablauf des {{ $de($tl['tenant_terminates_to']) }} kündigen (§ 561 BGB). Die Erhöhung tritt dann nicht ein.@if (empty($data['simplified'])) Bedeutet die Erhöhung für Sie oder Ihre Familie eine nicht zu rechtfertigende Härte, teilen Sie uns die Gründe bitte in Textform bis zum Ablauf des Monats mit, der auf den Zugang dieses Schreibens folgt.@endif</p>
  @elseif ($kind === 'index')
    <h2>Wirkung</h2>
    <p>Die neue Miete ist ab dem {{ $de($inc->effective_from) }} zu zahlen, das ist der Beginn des übernächsten Monats nach Zugang dieser Erklärung (§ 557b Abs. 3 BGB). Eine Zustimmung ist nicht erforderlich.</p>
  @endif

  @if ($inc->note)<div class="box" style="white-space:pre-line">{{ $inc->note }}</div>@endif

  @if (in_array($kind, ['vergleich', 'vereinbarung'], true))
    <div class="box">
      <strong>Zustimmung des Mieters</strong>
      <p>Ich/Wir stimme(n) der Erhöhung der Nettokaltmiete für {{ $unit?->label }}, {{ $property?->addressLine() }} auf {{ $eur($inc->new_cold) }} ab dem {{ $de($inc->effective_from) }} zu.</p>
      <p style="margin-top:12mm">Ort, Datum: ______________________ &nbsp;&nbsp; Unterschrift: ______________________________</p>
    </div>
  @endif

  <p style="margin-top:8mm">Mit freundlichen Grüßen<br>{{ $owner?->name }}</p>
  @if ($kind !== 'vereinbarung')<p class="small">Dieses Schreiben wurde maschinell erstellt und gilt ohne Unterschrift (Textform, § 126b BGB).</p>@endif
  @include('partials.brieffuss', ['bk' => \App\Support\Briefkopf::daten($owner)])
</div>
</body>
</html>
