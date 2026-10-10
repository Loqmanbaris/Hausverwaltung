<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $c->title() }} – {{ $c->header['mieter']['name'] ?? '' }}</title>
@include('contracts.document-styles')
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
body{font-family:"Segoe UI",Arial,sans-serif;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm;box-sizing:border-box}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap;font-family:"Segoe UI",Arial,sans-serif}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
.ct-doc{font-size:10.5pt}
.ct-sec h2{page-break-after:avoid}
.ct-sec p{orphans:3;widows:3}
.draft{position:fixed;top:40%;left:0;right:0;text-align:center;font-size:72pt;color:rgba(180,35,24,.12);transform:rotate(-30deg);pointer-events:none;font-weight:700}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
<div class="toolbar">
  <button type="button" onclick="window.print()">Drucken / als PDF speichern</button>
  <span>{{ $c->statusLabel() }}@if ($c->isDraft()) · zweimal ausdrucken, je ein Exemplar für Vermieter und Mieter @endif</span>
</div>
@if ($c->status === 'storniert')<div class="draft">STORNIERT</div>@endif
<div class="page">
  @include('contracts.document', ['c' => $c, 'sig' => $sig])
</div>
</body>
</html>
