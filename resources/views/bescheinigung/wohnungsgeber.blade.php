@php
  $bsTitel = 'Wohnungsgeberbestätigung';
  $bsDe = fn (?string $d) => \App\Support\Bescheinigung\Bescheinigung::de($d);
  $bsOwnerAddr = trim(($owner?->street ?? '').', '.trim(($owner?->zip ?? '').' '.($owner?->city ?? '')), ', ');
@endphp
@include('bescheinigung._druck')
<div class="page">
  <h1>Wohnungsgeberbestätigung</h1>
  <p class="sub">nach § 19 Bundesmeldegesetz (BMG)</p>

  <table class="f">
    <tr><th>Wohnungsgeber</th><td>{{ $owner?->name }}<br>{{ $bsOwnerAddr }}</td></tr>
    <tr><th>Der Wohnungsgeber ist</th><td><span class="x">✓</span>Eigentümer der Wohnung</td></tr>
    <tr><th>Meldepflichtiger Vorgang</th><td>
      <span class="x">{{ $vorgang === 'einzug' ? '✓' : '' }}</span>Einzug &nbsp;&nbsp;
      <span class="x">{{ $vorgang === 'auszug' ? '✓' : '' }}</span>Auszug
      <br>am <strong>{{ $bsDe($datum) }}</strong>
    </td></tr>
    <tr><th>Anschrift der Wohnung</th><td>{{ $wohnung }}@if ($lage)<br>Lage: {{ $lage }}@endif</td></tr>
    <tr><th>{{ $vorgang === 'einzug' ? 'Eingezogene' : 'Ausgezogene' }} Person{{ count($personen) === 1 ? '' : 'en' }}</th><td>
      @foreach ($personen as $p){{ $p }}@if (! $loop->last)<br>@endif @endforeach
    </td></tr>
  </table>

  <p>Hiermit bestätige ich als Wohnungsgeber, dass die oben genannte{{ count($personen) === 1 ? '' : 'n' }} Person{{ count($personen) === 1 ? '' : 'en' }} am {{ $bsDe($datum) }} in die oben genannte Wohnung {{ $vorgang === 'einzug' ? 'eingezogen' : 'ausgezogen' }} {{ count($personen) === 1 ? 'ist' : 'sind' }}.</p>

  <div class="sign">
    <div>Ort, Datum: {{ $owner?->city ? $owner->city.', ' : '' }}{{ $bsDe($briefdatum) }}</div>
    <div>Unterschrift des Wohnungsgebers</div>
  </div>

  <div class="box small">
    <strong>Hinweis:</strong> @if ($vorgang === 'einzug')Die meldepflichtige Person muss sich innerhalb von zwei Wochen nach dem Einzug bei der Meldebehörde anmelden (§ 17 Abs. 1 BMG) und legt dafür diese Bestätigung vor.@else Eine Abmeldung ist nur nötig, wenn keine neue Wohnung im Inland bezogen wird (§ 17 Abs. 2 BMG); sonst genügt die Anmeldung am neuen Wohnort.@endif
    Es ist verboten, eine Wohnanschrift für eine Anmeldung einem Dritten anzubieten oder zur Verfügung zu stellen, obwohl ein tatsächlicher Bezug der Wohnung durch diesen weder stattfindet noch beabsichtigt ist (§ 19 Abs. 6 BMG). Ein Verstoß ist eine Ordnungswidrigkeit und kann mit einer Geldbuße bis zu 50.000 Euro geahndet werden (§ 54 BMG).
  </div>
</div>
</body>
</html>
