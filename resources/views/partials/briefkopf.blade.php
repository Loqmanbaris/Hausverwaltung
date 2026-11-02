{{-- Briefkopf: links immer das Logo, rechts die Angaben des Eigentümers. Erwartet $bk = \App\Support\Briefkopf::daten(...) --}}
<style>
.bk-head{display:flex;justify-content:space-between;align-items:flex-start;gap:8mm;padding-bottom:4mm;margin-bottom:6mm;border-bottom:.8pt solid #c2410c}
.bk-logo{display:flex;align-items:center;gap:3mm}
.bk-word{line-height:1.05;color:#202c34}
.bk-word b{display:block;font-size:20pt;font-weight:700;letter-spacing:.01em}
.bk-word span{display:block;font-size:7.8pt;font-weight:600;letter-spacing:.32em;color:#5b6472;margin-top:1mm}
.bk-from{text-align:right;font-size:8.6pt;line-height:1.45;color:#333}
.bk-from b{color:#111;font-size:9.6pt}
</style>
<div class="bk-head">
  <div class="bk-logo">
    @include('partials.logo-mark', ['lmHouse' => '#202c34', 'lmAccent' => '#c2410c', 'lmSize' => 56])
    <div class="bk-word"><b>Baris</b><span>HAUSVERWALTUNG</span></div>
  </div>
  <div class="bk-from">
    @if ($bk['name'] !== '')<b>{{ $bk['name'] }}</b><br>@endif
    @if ($bk['street'] !== ''){{ $bk['street'] }}<br>@endif
    @if ($bk['zip'].$bk['city'] !== ''){{ trim($bk['zip'].' '.$bk['city']) }}<br>@endif
    @if ($bk['phone'] !== '')Tel. {{ $bk['phone'] }}<br>@endif
    @if ($bk['email'] !== ''){{ $bk['email'] }}@endif
  </div>
</div>
