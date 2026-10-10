<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>{{ $p->title() }} – {{ $p->lease?->tenant?->display_name }}</title>
@include('handover.body-styles')
<style>
@page{size:A4;margin:16mm 16mm 14mm 20mm}
body{font:10pt/1.4 "Segoe UI",Arial,sans-serif;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;margin:10mm auto;padding:16mm 16mm 14mm 20mm;box-sizing:border-box}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center;flex-wrap:wrap}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
<div class="toolbar"><button type="button" onclick="window.print()">Drucken / als PDF speichern</button><span>{{ $p->isDraft() ? 'Entwurf' : 'Abgeschlossen' }}</span></div>
<div class="page">@include('handover.body')</div>
</body>
</html>
