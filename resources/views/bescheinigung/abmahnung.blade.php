@php
  $bsTitel = 'Abmahnung';
  $bsDe = fn (?string $d) => \App\Support\Bescheinigung\Bescheinigung::de($d);
@endphp
@include('bescheinigung._druck')
<div class="page">
  @include('bescheinigung._brief')

  <h1>Abmahnung</h1>
  <p class="sub">Mietverhältnis über {{ $lage ? $lage.', ' : '' }}{{ $wohnung }} – {{ $grundText }}</p>

  <p>{{ $anrede }}</p>
  <p>leider muss ich Sie wegen eines Verstoßes gegen Ihre Pflichten aus dem Mietvertrag abmahnen. Folgendes ist vorgefallen:</p>
  <div class="box" style="margin-top:0;white-space:pre-line">{{ $sachverhalt }}</div>
  <p style="margin-top:5mm">Dieses Verhalten verletzt Ihre Pflichten aus dem Mietvertrag. Ich fordere Sie hiermit auf, {{ rtrim($aufforderung, '.') }}@if ($frist), spätestens bis zum <strong>{{ $bsDe($frist) }}</strong>@endif.</p>
  <p>Sollten Sie dieser Aufforderung nicht nachkommen oder es erneut zu einem gleichartigen Verstoß kommen, behalte ich mir vor, das Mietverhältnis {{ $gewerbe ? 'außerordentlich fristlos, hilfsweise ordentlich' : 'fristlos, hilfsweise ordentlich' }} zu kündigen.</p>
  <p>Ich hoffe, dass dies nicht nötig sein wird, und setze auf ein gutes Miteinander im Haus.</p>
  <p>Mit freundlichen Grüßen</p>

  <div class="sign">
    <div>{{ $owner?->name }} (Vermieter)</div>
    <div style="border-top:0"></div>
  </div>
  @include('partials.brieffuss', ['bk' => \App\Support\Briefkopf::daten($owner)])
</div>
</body>
</html>
