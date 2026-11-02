{{-- Fußzeile des Briefs: Anschrift, Kontakt, Bank, Steuer des Eigentümers. Erwartet $bk = \App\Support\Briefkopf::daten(...) --}}
<style>
.bk-foot{display:flex;flex-wrap:wrap;gap:2mm 8mm;margin-top:10mm;padding-top:2.5mm;border-top:.5pt solid #bbb;font-size:7.4pt;line-height:1.4;color:#555}
.bk-foot div{flex:1 1 40mm;min-width:0}
.bk-foot b{color:#333;font-weight:600}
</style>
<div class="bk-foot">
  <div><b>{{ $bk['name'] }}</b><br>{{ $bk['street'] }}<br>{{ trim($bk['zip'].' '.$bk['city']) }}</div>
  <div>@if ($bk['phone'] !== '')Tel. {{ $bk['phone'] }}<br>@endif @if ($bk['email'] !== ''){{ $bk['email'] }}@endif</div>
  @if ($bk['iban'] !== '')
    <div><b>Bankverbindung</b><br>@if ($bk['bank'] !== ''){{ $bk['bank'] }}<br>@endif IBAN {{ \App\Support\Briefkopf::iban($bk['iban']) }}@if ($bk['bic'] !== '')<br>BIC {{ $bk['bic'] }}@endif</div>
  @endif
  @if ($bk['tax_number'].$bk['vat_id'] !== '')
    <div>@if ($bk['tax_number'] !== '')Steuernummer {{ $bk['tax_number'] }}<br>@endif @if ($bk['vat_id'] !== '')USt-IdNr. {{ $bk['vat_id'] }}@endif</div>
  @endif
</div>
