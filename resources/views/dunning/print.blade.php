<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $notices->count() === 1 ? $notices->first()->levelLabel() : 'Mahnungen' }}</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
*{box-sizing:border-box}
body{font:10.5pt/1.45 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm;page-break-after:always}
.page:last-child{page-break-after:auto}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
.sender{font-size:8pt;border-bottom:1px solid #999;display:inline-block;margin-bottom:2mm}
.addr{min-height:30mm;margin-top:5mm}
.meta{text-align:right;margin:2mm 0 6mm}
h1{font-size:13pt;margin:0 0 4mm}
table{width:100%;border-collapse:collapse;margin:3mm 0;font-size:9.5pt}
th,td{padding:1.2mm 1.3mm;border-bottom:.3pt solid #bbb;text-align:left;vertical-align:top}
th{font-size:8.5pt;color:#444;border-bottom:.8pt solid #333}
.n{text-align:right;white-space:nowrap}
.sum td{font-weight:700;border-top:.8pt solid #333;border-bottom:0;font-size:11pt}
.storno{border:2px solid #b42318;color:#b42318;font-weight:700;padding:2mm 3mm;margin-bottom:4mm;display:inline-block}
.small{font-size:8.5pt;color:#333}
tr{page-break-inside:avoid}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
@endphp
<div class="toolbar">
  <button type="button" onclick="window.print()">Drucken / als PDF speichern</button>
  <span>{{ $notices->count() }} Schreiben</span>
</div>

@foreach ($notices as $n)
  @php
    $lease = $n->lease;
    $tenant = $lease?->tenant;
    $unit = $lease?->unit;
    $property = $unit?->property;
    $owner = $n->owner;
    $account = $owner?->bankAccounts()->orderByDesc('is_primary')->first();
    $contact = $tenant?->type === 'firma' ? $tenant->contacts->firstWhere('receives_invoices', true) : null;
    $prev = $previous[$n->id] ?? null;
    $items = collect($n->items ?? []);
  @endphp
  <div class="page">
    @if ($n->isCancelled())<div class="storno">STORNIERT – nicht versenden</div>@endif
    <div class="sender">{{ $owner?->name }} · {{ $owner?->street }} · {{ trim(($owner?->zip ?? '').' '.($owner?->city ?? '')) }}</div>
    <div class="addr">
      {{ $tenant?->type === 'firma' ? $tenant->company_name : trim(($tenant?->first_name ?? '').' '.($tenant?->last_name ?? '')) }}<br>
      @if ($contact) z. Hd. {{ $contact->name }}<br>@endif
      {{ $tenant?->street ?: $property?->street }}<br>
      {{ trim(($tenant?->zip ?: $property?->zip).' '.($tenant?->city ?: $property?->city)) }}
    </div>
    <div class="meta">{{ $owner?->city ? $owner->city.', ' : '' }}{{ $n->issued_on->format('d.m.Y') }}</div>

    <h1>{{ $n->levelLabel() }}</h1>
    <p>Mietverhältnis: {{ $unit?->label }}, {{ $property?->addressLine() }}</p>

    @if ($tenant?->type === 'firma')
      <p>Sehr geehrte Damen und Herren,</p>
    @else
      <p>Guten Tag {{ trim(($tenant?->first_name ?? '').' '.($tenant?->last_name ?? '')) }},</p>
    @endif

    @if ($n->level === 1)
      <p>bei der Durchsicht unserer Unterlagen haben wir festgestellt, dass folgende Beträge noch nicht bei uns eingegangen sind. Sicher ist das nur übersehen worden.</p>
    @elseif ($n->level === 2)
      <p>trotz unserer Zahlungserinnerung{{ $prev ? ' vom '.$prev->issued_on->format('d.m.Y') : '' }} konnten wir bis heute keinen vollständigen Zahlungseingang feststellen. Folgende Beträge sind weiterhin offen:</p>
    @else
      <p>trotz {{ $prev ? 'unserer '.$prev->levelLabel().' vom '.$prev->issued_on->format('d.m.Y') : 'unserer bisherigen Schreiben' }} sind folgende Beträge weiterhin offen. Dies ist unsere letzte Mahnung.</p>
    @endif

    <table>
      <thead><tr><th>Posten</th><th class="n">fällig am</th><th class="n">Betrag</th><th class="n">noch offen</th></tr></thead>
      <tbody>
        @foreach ($items as $it)
          <tr>
            <td>{{ $it['label'] }}</td>
            <td class="n">{{ \Carbon\Carbon::parse($it['due'])->format('d.m.Y') }}</td>
            <td class="n">{{ $eur($it['total']) }}</td>
            <td class="n">{{ $eur($it['open']) }}</td>
          </tr>
        @endforeach
        @if ((float) $n->fee > 0)
          <tr><td>Mahngebühr</td><td class="n"></td><td class="n"></td><td class="n">{{ $eur($n->fee) }}</td></tr>
        @endif
        <tr class="sum"><td colspan="3">Zu zahlen</td><td class="n">{{ $eur($n->totalAmount()) }}</td></tr>
      </tbody>
    </table>

    <p>Bitte überweisen Sie den Betrag von <strong>{{ $eur($n->totalAmount()) }}</strong> bis spätestens <strong>{{ $n->pay_by->format('d.m.Y') }}</strong>@if ($account) auf das Konto {{ $account->account_holder }}, IBAN {{ $account->formattedIban() }}{{ $account->bank_name ? ' ('.$account->bank_name.')' : '' }}@endif. Verwendungszweck: Miete {{ $unit?->label }}.</p>

    @if ($n->level === 3)
      <p>Sollte der Betrag bis zu diesem Datum nicht vollständig eingegangen sein, werden wir ohne weitere Ankündigung weitere Schritte einleiten. Dazu gehören insbesondere das gerichtliche Mahnverfahren und, sofern die gesetzlichen Voraussetzungen vorliegen, die Kündigung des Mietverhältnisses. Die Geltendmachung von Verzugszinsen und weiteren Kosten behalten wir uns vor.</p>
    @elseif ($n->level === 2)
      <p>Wir bitten Sie, die Zahlung umgehend vorzunehmen. Die Geltendmachung von Verzugszinsen behalten wir uns vor.</p>
    @endif

    @if ($n->note)
      <p>{!! nl2br(e($n->note)) !!}</p>
    @endif

    <p>Sollten Sie die Zahlung inzwischen geleistet haben, betrachten Sie dieses Schreiben bitte als gegenstandslos. Bei Fragen oder wenn Sie eine Ratenzahlung vereinbaren möchten, melden Sie sich gerne bei uns.</p>

    <p>Mit freundlichen Grüßen<br><br>{{ $owner?->name }}</p>
  </div>
@endforeach
</body>
</html>
