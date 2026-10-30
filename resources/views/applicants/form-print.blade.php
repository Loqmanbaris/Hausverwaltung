<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Selbstauskunft</title>
<style>
@page{size:A4;margin:16mm 18mm 14mm 20mm}
*{box-sizing:border-box}
body{font:10pt/1.4 "Segoe UI",Arial,sans-serif;color:#111;margin:0;background:#e9ecef}
.page{background:#fff;width:210mm;min-height:297mm;margin:10mm auto;padding:16mm 18mm 14mm 20mm}
.toolbar{position:sticky;top:0;background:#1f4e79;color:#fff;padding:.6rem 1rem;display:flex;gap:1rem;align-items:center}
.toolbar button{font:inherit;padding:.4rem .9rem;border:0;border-radius:.4rem;background:#fff;color:#1f4e79;cursor:pointer}
h1{font-size:14pt;margin:0 0 3mm}
.row{display:flex;gap:6mm;margin:4.5mm 0 0}
.f{flex:1}.f span{display:block;font-size:8.5pt;color:#444}.f i{display:block;border-bottom:.6pt solid #333;height:6mm}
.small{font-size:8.5pt;color:#333}
@media print{body{background:#fff}.toolbar{display:none}.page{margin:0;width:auto;min-height:auto;padding:0}}
@media (max-width:820px){.page{width:auto;margin:0;padding:6mm}}
</style>
</head>
<body>
@php use App\Support\Interessenten\Interessenten; @endphp
<div class="toolbar"><button type="button" onclick="window.print()">Drucken</button><span>Selbstauskunft für Mietinteressenten</span></div>
<div class="page">
  <h1>Selbstauskunft für Mietinteressenten</h1>
  <p>Wohnung: <strong>{{ $a->unitLabel() }}</strong>{{ $a->unit?->property ? ', '.$a->unit->property->addressLine() : '' }}<br>Vermieter: {{ $a->owner?->name }}</p>
  <p class="small">Alle Angaben sind freiwillig. Bitte geben Sie nur an, was Sie mitteilen möchten. Nachweise verlangen wir erst bei einer Zusage.</p>
  <div class="row"><div class="f"><span>Name</span><i>{{ $a->name() }}</i></div><div class="f"><span>Telefon</span><i>{{ $a->phone }}</i></div></div>
  <div class="row"><div class="f"><span>E-Mail</span><i>{{ $a->email }}</i></div></div>
  <div class="row"><div class="f"><span>{{ Interessenten::FIELDS['adults'][0] }}</span><i></i></div><div class="f"><span>{{ Interessenten::FIELDS['children'][0] }}</span><i></i></div></div>
  <div class="row"><div class="f"><span>{{ Interessenten::FIELDS['occupation'][0] }}</span><i></i></div><div class="f"><span>{{ Interessenten::FIELDS['employer'][0] }}</span><i></i></div></div>
  <div class="row"><div class="f"><span>{{ Interessenten::FIELDS['net_income'][0] }}</span><i></i></div><div class="f"><span>{{ Interessenten::FIELDS['pets'][0] }}</span><i></i></div></div>
  <div class="row"><div class="f"><span>{{ Interessenten::FIELDS['move_in'][0] }}</span><i></i></div><div class="f"><span>{{ Interessenten::FIELDS['credit_ok'][0] }}</span><i>&#9744; Ja &nbsp; &#9744; Nein</i></div></div>
  <div class="row"><div class="f"><span>{{ Interessenten::FIELDS['remark'][0] }}</span><i></i><i></i><i></i></div></div>
  @include('applicants._privacy', ['owner' => $a->owner])
  <div class="row" style="margin-top:12mm"><div class="f"><span>Ort, Datum</span><i></i></div><div class="f"><span>Unterschrift (Kenntnisnahme der Datenschutzhinweise)</span><i></i></div></div>
</div>
</body>
</html>
