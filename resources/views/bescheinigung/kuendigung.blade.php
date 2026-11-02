@php
  $bsTitel = $art === 'ordentlich' ? 'Kündigung des Mietverhältnisses' : 'Fristlose Kündigung des Mietverhältnisses';
  $bsDe = fn (?string $d) => \App\Support\Bescheinigung\Bescheinigung::de($d);
  $bsEur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $fristlos = $art !== 'ordentlich';
  $raum = $gewerbe ? 'die Mieträume' : 'die Wohnung';
@endphp
@include('bescheinigung._druck')
<div class="page">
  @include('bescheinigung._brief')

  <h1>{{ $bsTitel }}</h1>
  <p class="sub">Mietverhältnis über {{ $lage ? $lage.', ' : '' }}{{ $wohnung }}, Mietvertrag vom {{ $bsDe($beginn) }}</p>

  <p>{{ $anrede }}</p>

  @if ($art === 'ordentlich')
    <p>hiermit kündige ich das oben genannte Mietverhältnis ordentlich und fristgerecht zum <strong>{{ $bsDe($frist['ende']) }}</strong>, hilfsweise zum nächstmöglichen Termin.</p>
    @if ($grundText)
      <p><strong>Grund der Kündigung:</strong> {{ $grundText }}</p>
    @endif
    @if ($begruendung)<div style="white-space:pre-line">{{ $begruendung }}</div>@endif
  @elseif ($art === 'fristlos_zahlung')
    <p>hiermit kündige ich das oben genannte Mietverhältnis <strong>fristlos aus wichtigem Grund wegen Zahlungsverzugs</strong> (§ 543 Abs. 1, Abs. 2 Satz 1 Nr. 3{{ $gewerbe ? '' : ', § 569 Abs. 3' }} BGB).</p>
    <p>Sie sind mit der Zahlung der Miete wie folgt in Rückstand:</p>
    <table class="f">
      @foreach ($verzug['zeilen'] as $z)
        <tr><th>Miete {{ $z['monat'] }}</th><td>fällig am {{ $bsDe($z['faellig']) }}, offen {{ $bsEur($z['offen']) }}</td></tr>
      @endforeach
      <tr><th>Rückstand gesamt</th><td><strong>{{ $bsEur($verzug['summe']) }}</strong> (Monatsmiete {{ $bsEur($verzug['monatsmiete']) }})</td></tr>
    </table>
    @if ($begruendung)<div style="white-space:pre-line">{{ $begruendung }}</div>@endif
  @else
    <p>hiermit kündige ich das oben genannte Mietverhältnis <strong>fristlos aus wichtigem Grund</strong> (§ 543 Abs. 1{{ $gewerbe ? '' : ', § 569 Abs. 2' }} BGB).</p>
    <p><strong>Begründung:</strong></p>
    <div style="white-space:pre-line">{{ $begruendung }}</div>
  @endif

  @if ($fristlos && $hilfsweise)
    <p style="margin-top:4mm">Hilfsweise, für den Fall, dass die fristlose Kündigung unwirksam sein sollte, kündige ich das Mietverhältnis zugleich ordentlich und fristgerecht zum <strong>{{ $bsDe($frist['ende']) }}</strong>, hilfsweise zum nächstmöglichen Termin.@unless ($gewerbe) Die ordentliche Kündigung stütze ich auf die oben beschriebene schuldhafte, nicht unerhebliche Verletzung Ihrer vertraglichen Pflichten (§ 573 Abs. 2 Nr. 1 BGB).@endunless</p>
  @endif

  @if ($fristlos)
    <p>Ich fordere Sie auf, {{ $raum }} bis spätestens <strong>{{ $bsDe($raeumung) }}</strong> zu räumen und mit allen Schlüsseln an mich zurückzugeben.</p>
  @else
    <p>Ich fordere Sie auf, {{ $raum }} zum Ende des Mietverhältnisses zu räumen und mit allen Schlüsseln an mich zurückzugeben.</p>
  @endif
  <p>Einer stillschweigenden Fortsetzung des Mietverhältnisses über den Beendigungszeitpunkt hinaus widerspreche ich bereits jetzt ausdrücklich (§ 545 BGB).</p>

  @if (! $gewerbe && ($art === 'ordentlich' || $hilfsweise))
    <div class="box small">
      <strong>Hinweis auf Ihr Widerspruchsrecht (§§ 568 Abs. 2, 574, 574b BGB):</strong> Sie können der {{ $fristlos ? 'hilfsweise erklärten ordentlichen ' : '' }}Kündigung widersprechen und die Fortsetzung des Mietverhältnisses verlangen, wenn die Beendigung für Sie, Ihre Familie oder einen anderen Angehörigen Ihres Haushalts eine Härte bedeuten würde, die auch unter Würdigung der berechtigten Interessen des Vermieters nicht zu rechtfertigen ist. Der Widerspruch muss schriftlich erklärt werden und dem Vermieter spätestens zwei Monate vor der Beendigung des Mietverhältnisses, also bis zum {{ $bsDe((new DateTimeImmutable($frist['ende']))->modify('first day of this month')->modify('-2 months')->modify('last day of this month')->format('Y-m-d')) }}, zugehen. Auf Verlangen des Vermieters sollen Sie über die Gründe des Widerspruchs unverzüglich Auskunft erteilen.
    </div>
  @endif

  <p style="margin-top:5mm">Mit freundlichen Grüßen</p>
  <div class="sign">
    <div>{{ $owner?->name }} (Vermieter) – eigenhändige Unterschrift</div>
    <div style="border-top:0"></div>
  </div>
  @include('partials.brieffuss', ['bk' => \App\Support\Briefkopf::daten($owner)])
</div>
</body>
</html>
