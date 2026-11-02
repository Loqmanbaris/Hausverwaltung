{{-- Gemeinsamer Kopf der Druckseiten (wie bei der Mieterhöhung). Erwartet $bsTitel --}}
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $bsTitel }}</title>
<style>
@page{size:A4;margin:18mm 18mm 16mm 22mm}
*{box-sizing:border-box}
body{font:10.5pt/1.5 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:18mm 18mm 16mm 22mm}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
.sender{font-size:8pt;border-bottom:1px solid #999;display:inline-block;margin-bottom:2mm}
.addr{min-height:30mm;margin-top:5mm}
.meta{text-align:right;margin:2mm 0 6mm}
h1{font-size:14pt;margin:0 0 1mm}
.sub{color:#444;margin:0 0 6mm}
table.f{width:100%;border-collapse:collapse;margin:3mm 0 5mm}
table.f th{width:52mm;text-align:left;vertical-align:top;font-weight:600;padding:2mm 3mm 2mm 0;border-bottom:.3pt solid #bbb}
table.f td{vertical-align:top;padding:2mm 0;border-bottom:.3pt solid #bbb}
.box{border:1px solid #999;padding:3mm 4mm;margin-top:5mm}
.small{font-size:8.5pt;color:#333}
.sign{display:flex;gap:12mm;margin-top:18mm}
.sign div{flex:1;border-top:.6pt solid #333;padding-top:1.5mm;font-size:8.5pt;color:#333}
.x{display:inline-block;width:3.6mm;height:3.6mm;border:.6pt solid #333;margin-right:1.5mm;vertical-align:-.5mm;text-align:center;line-height:3.2mm;font-size:8pt}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
<div class="toolbar">
  <button type="button" onclick="window.print()">Drucken / als PDF speichern</button>
  <span>{{ $bsTitel }}</span>
</div>
