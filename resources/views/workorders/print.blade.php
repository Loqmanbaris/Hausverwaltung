<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Auftrag {{ $o->number() }}</title>
<style>
body{font:10.5pt/1.45 Arial,sans-serif;color:#111;margin:0}
.page{padding:18mm}
h1{font-size:15pt;margin:0 0 4mm}
table{border-collapse:collapse;margin:3mm 0}
th{text-align:left;font-weight:400;color:#555;padding:1mm 4mm 1mm 0;vertical-align:top}
td{padding:1mm 0;vertical-align:top}
.box{border:.6pt solid #999;padding:3mm;margin-top:4mm}
.hb-photos img{width:31%}
</style>
</head>
<body>
<div class="page">
  <p class="sender">{{ \App\Support\Mail\MailSettings::fromName() }}{{ \App\Support\Mail\MailSettings::get('mail_from_address') ? ' · '.\App\Support\Mail\MailSettings::get('mail_from_address') : '' }}</p>
  <h1>Auftrag {{ $o->number() }}</h1>
  <table class="hb-meta">
    <tr><th>Datum</th><td>{{ now()->format('d.m.Y') }}</td></tr>
    @if ($o->vendor)<tr><th>An</th><td>{{ $o->vendor->name }}</td></tr>@endif
    <tr><th>Arbeit</th><td><strong>{{ $o->title }}</strong></td></tr>
    <tr><th>Ort</th><td>{{ \App\Support\Technik\Auftraege::address($o) }}</td></tr>
    @if ($o->priority !== 'normal')<tr><th>Dringlichkeit</th><td><strong>{{ \App\Models\WorkOrder::PRIORITY[$o->priority] }}</strong></td></tr>@endif
    @if ($contact)<tr><th>Vor Ort</th><td>{{ $contact['name'] }}{{ $contact['phone'] ? ', Tel. '.$contact['phone'] : '' }}</td></tr>@endif
    @if ($o->appointment_at)<tr><th>Termin</th><td>{{ $o->appointment_at->format('d.m.Y H:i') }} Uhr</td></tr>@endif
    @if ($o->cost_estimate !== null)<tr><th>Kostenrahmen</th><td>{{ \App\Support\Money::format($o->cost_estimate) }}</td></tr>@endif
  </table>
  @if ($o->description)
    <h2>Beschreibung</h2>
    <p class="hb-note">{{ $o->description }}</p>
  @endif
  @if (count($thumbs) > 0)
    <h2>Fotos</h2>
    <div class="hb-photos">@foreach ($thumbs as $src)<img src="{{ $src }}" alt="Foto">@endforeach</div>
  @endif
  <div class="box small">Bitte die Rechnung mit der Auftragsnummer {{ $o->number() }} an {{ \App\Support\Mail\MailSettings::fromName() }} senden.@if ($o->owner_id) Rechnungsempfänger: {{ \App\Models\Owner::find($o->owner_id)?->name }}.@endif</div>
</div>
</body>
</html>
