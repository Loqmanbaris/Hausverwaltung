@php
  $bsTitel = 'Mietschuldenfreiheitsbescheinigung';
  $bsDe = fn (?string $d) => \App\Support\Bescheinigung\Bescheinigung::de($d);
  $bsEur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $bsOwnerAddr = trim(($owner?->street ?? '').', '.trim(($owner?->zip ?? '').' '.($owner?->city ?? '')), ', ');
  $bsVorbei = $ende && $ende < $stichtag;
@endphp
@include('bescheinigung._druck')
<div class="page">
  <div class="sender">{{ $owner?->name }} · {{ $bsOwnerAddr }}</div>
  <div class="addr">
    {{ $mieter }}<br>
    {{ $tenant?->street ?: $property?->street }}<br>
    {{ trim(($tenant?->zip ?: $property?->zip).' '.($tenant?->city ?: $property?->city)) }}
  </div>
  <div class="meta">{{ $owner?->city ? $owner->city.', ' : '' }}{{ date('d.m.Y') }}</div>

  <h1>Mietschuldenfreiheitsbescheinigung</h1>
  <p class="sub">@if ($zweck){{ $zweck }}@else zur Vorlage bei Dritten @endif</p>

  <table class="f">
    <tr><th>Vermieter</th><td>{{ $owner?->name }}<br>{{ $bsOwnerAddr }}</td></tr>
    <tr><th>Mieter</th><td>{{ $mieter }}</td></tr>
    <tr><th>Mietobjekt</th><td>{{ $wohnung }}@if ($lage)<br>{{ $lage }}@endif</td></tr>
    <tr><th>Mietverhältnis</th><td>seit {{ $bsDe($beginn) }}@if ($ende), {{ $bsVorbei ? 'beendet' : 'endet' }} am {{ $bsDe($ende) }}@endif</td></tr>
    @if ($miete[2] > 0)
      <tr><th>Monatliche Miete</th><td>{{ $bsEur($miete[2]) }} (Kaltmiete {{ $bsEur($miete[0]) }}, Vorauszahlungen und Zuschläge {{ $bsEur($miete[1]) }})</td></tr>
    @endif
  </table>

  <p>Hiermit bestätige ich, dass aus dem oben genannten Mietverhältnis bis zum <strong>{{ $bsDe($stichtag) }}</strong> keine Mietschulden bestehen. Alle bis zu diesem Tag fälligen Mieten, Nebenkostenvorauszahlungen und Nachzahlungen aus Nebenkostenabrechnungen sind bezahlt.</p>
  @if ($puenktlich)
    <p>Die Miete wurde stets pünktlich gezahlt.</p>
  @endif
  <p>Diese Bescheinigung wird auf Wunsch des Mieters ausgestellt.</p>

  <div class="sign">
    <div>Ort, Datum: {{ $owner?->city ? $owner->city.', ' : '' }}{{ date('d.m.Y') }}</div>
    <div>Unterschrift des Vermieters</div>
  </div>
</div>
</body>
</html>
