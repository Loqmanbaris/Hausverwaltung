@php
  $bsTitel = 'Bestätigung Ihrer Kündigung';
  $bsDe = fn (?string $d) => \App\Support\Bescheinigung\Bescheinigung::de($d);
  $raum = $gewerbe ? 'die Mieträume' : 'die Wohnung';
@endphp
@include('bescheinigung._druck')
<div class="page">
  @include('bescheinigung._brief')

  <h1>Bestätigung Ihrer Kündigung</h1>
  <p class="sub">Mietverhältnis über {{ $lage ? $lage.', ' : '' }}{{ $wohnung }}, Mietvertrag vom {{ $bsDe($beginn) }}</p>

  <p>{{ $anrede }}</p>
  <p>vielen Dank für Ihr Schreiben. Ihre Kündigung ist am {{ $bsDe($eingang) }} bei mir eingegangen. Hiermit bestätige ich, dass das Mietverhältnis zum <strong>{{ $bsDe($endeNeu) }}</strong> endet.</p>
  @if ($frueher)
    <p>Sie hatten das Ende zum {{ $bsDe($gewuenscht) }} gewünscht. Wegen der gesetzlichen Kündigungsfrist ({{ $gewerbe ? 'zum Ende des nächsten Quartals' : 'drei Monate' }}) ist der nächstmögliche Termin jedoch der {{ $bsDe($endeNeu) }}. Bis dahin ist die Miete weiter zu zahlen. Gern können wir über eine frühere Beendigung sprechen, wenn Sie einen geeigneten Nachmieter vorschlagen; ein Anspruch darauf besteht nicht.</p>
  @endif

  <h2>Rückgabe {{ $gewerbe ? 'der Mieträume' : 'der Wohnung' }}</h2>
  @if ($uebergabeTag)
    <p>Für die Übergabe schlage ich folgenden Termin vor: <strong>{{ $bsDe($uebergabeTag) }}{{ $uebergabeZeit !== '' ? ', '.$uebergabeZeit : '' }}</strong>. Bitte geben Sie mir kurz Bescheid, ob der Termin passt.</p>
  @else
    <p>Für die Übergabe vereinbaren wir rechtzeitig einen Termin. Bitte melden Sie sich dazu bei mir.</p>
  @endif
  <p>Bitte geben Sie {{ $raum }} bis zum Ende des Mietverhältnisses geräumt, besenrein und mit allen Schlüsseln zurück, auch denen, die Sie sich selbst haben anfertigen lassen. Bei der Übergabe erstellen wir gemeinsam ein Protokoll und lesen die Zähler ab. Arbeiten, die Sie nach dem Mietvertrag beim Auszug schulden, erledigen Sie bitte vorher.</p>
  @unless ($gewerbe)
    <p>Bitte melden Sie sich innerhalb von zwei Wochen nach dem Auszug am neuen Wohnort an. Die dafür nötige Wohnungsgeberbestätigung stellt Ihnen Ihr neuer Vermieter aus.</p>
  @endunless
  <p>Bitte teilen Sie mir Ihre neue Anschrift mit. Ich brauche sie für die Abrechnung der Kaution und die letzte Nebenkostenabrechnung.</p>
  @if ($zusatz)<p style="white-space:pre-line">{{ $zusatz }}</p>@endif
  <p>Mit freundlichen Grüßen</p>
  <div class="sign">
    <div>{{ $owner?->name }} (Vermieter)</div>
    <div style="border-top:0"></div>
  </div>
  @include('partials.brieffuss', ['bk' => \App\Support\Briefkopf::daten($owner)])
</div>
</body>
</html>
