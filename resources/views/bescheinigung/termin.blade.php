@php
  $bsTitel = 'Ankündigung: '.$artText;
  $bsDe = fn (?string $d) => \App\Support\Bescheinigung\Bescheinigung::de($d);
  $tage = ['', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
  $wochentag = $tage[(int) (new DateTimeImmutable($tag))->format('N')];
  $zeit = $von && $bis ? ' zwischen '.$von.' und '.$bis.' Uhr' : ($von ? ' ab '.$von.' Uhr' : '');
  $satz = [
      'reparatur' => 'in Ihrer Wohnung müssen Arbeiten ausgeführt werden.',
      'wartung' => 'in Ihrer Wohnung steht eine Wartung an.',
      'ablesung' => 'die Zähler und Heizkostenverteiler in Ihrer Wohnung werden abgelesen.',
      'besichtigung' => 'ich möchte Ihre Wohnung mit Interessenten besichtigen.',
      'sonstiges' => 'ich muss Ihre Wohnung zu folgendem Anlass betreten.',
  ][$art];
@endphp
@include('bescheinigung._druck')
@foreach ($briefe as $b)
<div class="page" @if (! $loop->last) style="page-break-after:always" @endif>
  @include('bescheinigung._brief', $b)

  <h1>Ankündigung: {{ $artText }}</h1>
  <p class="sub">Mietverhältnis über {{ $b['lage'] ? $b['lage'].', ' : '' }}{{ $b['wohnung'] }}</p>

  <p>{{ $b['anrede'] }}</p>
  <p>{{ $satz }}@if ($beschreibung) {{ $beschreibung }}@endif</p>
  <table class="f">
    <tr><th>Termin</th><td><strong>{{ $wochentag }}, {{ $bsDe($tag) }}{{ $zeit }}</strong></td></tr>
    @if ($wer)<tr><th>Es kommt</th><td>{{ $wer }}</td></tr>@endif
  </table>
  <p>Bitte ermöglichen Sie an diesem Termin den Zugang zur Wohnung{{ $art === 'ablesung' || $art === 'wartung' ? ' und machen Sie die Zähler, Heizkörper bzw. Geräte frei zugänglich' : '' }}. Wenn Sie nicht selbst anwesend sein können, geben Sie den Schlüssel bitte einer Person Ihres Vertrauens oder sprechen Sie mich an.</p>
  <p>Passt der Termin nicht, melden Sie sich bitte @if ($meldenBis) bis zum <strong>{{ $bsDe($meldenBis) }}</strong>@else möglichst bald @endif{{ $kontakt ? ' ('.$kontakt.')' : '' }}, damit wir einen anderen Termin vereinbaren können.</p>
  <p>Vielen Dank für Ihre Mithilfe.</p>
  <p>Mit freundlichen Grüßen<br><br>{{ $b['owner']?->name }}</p>
  @include('partials.brieffuss', ['bk' => \App\Support\Briefkopf::daten($b['owner'])])
</div>
@endforeach
</body>
</html>
