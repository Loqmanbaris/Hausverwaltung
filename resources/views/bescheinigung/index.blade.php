@extends('layouts.app')
@section('title', 'Schreiben an Mieter')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  $bsDe = fn (?string $d) => \App\Support\Bescheinigung\Bescheinigung::de($d);
  $bsAuszug = $ende && $ende <= date('Y-m-d', strtotime('+3 months'));
@endphp
<style>
.bs-date{padding:.55rem .7rem;border:1px solid var(--line-strong);border-radius:8px;font:inherit;background:var(--surface);width:100%;box-sizing:border-box}
.bs-radio{display:flex;gap:1.2rem;flex-wrap:wrap;margin-top:.3rem}
.bs-radio label{font-weight:400;display:flex;gap:.4rem;align-items:center}
</style>
<div class="sd-head">
  <h1>Schreiben an Mieter</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('leases.show', $lease) }}">Zurück zum Mietvertrag</a></div>
</div>
<p class="sd-muted">{{ $mieter }} · {{ $lage ?: '–' }} · {{ $wohnung }} · Mietbeginn {{ $bsDe($beginn) }}@if ($ende), Ende {{ $bsDe($ende) }}@endif</p>

<div class="sd-card">
  <h2>Wohnungsgeberbestätigung</h2>
  <p class="sd-muted" style="margin-top:0">Pflicht nach § 19 Bundesmeldegesetz: Wer ein- oder auszieht, braucht die Bestätigung für die Anmeldung beim Bürgeramt. Sie muss innerhalb von zwei Wochen nach dem Ein- bzw. Auszug vorliegen. Bitte alle Personen eintragen, die ein- oder ausziehen (auch Kinder).</p>
  <form method="GET" action="{{ route('bescheinigung.wohnungsgeber', $lease) }}" target="_blank" rel="noopener">
    <div class="sd-field">
      <label>Vorgang *</label>
      <div class="bs-radio">
        @foreach ($vorgaenge as $k => $label)
          <label><input type="radio" name="vorgang" value="{{ $k }}" @checked(old('vorgang', $bsAuszug ? 'auszug' : 'einzug') === $k) required> {{ $label }}</label>
        @endforeach
      </div>
    </div>
    <div class="sd-grid">
      <div class="sd-field">
        <label for="bs-datum">Datum des Ein- bzw. Auszugs *</label>
        <input id="bs-datum" class="bs-date" type="date" name="datum" value="{{ old('datum', $bsAuszug ? $ende : $beginn) }}" required>
        <small class="sd-muted">Vorschlag: Mietbeginn bzw. Vertragsende.</small>
      </div>
      <div class="sd-field">
        <label for="bs-brief">Datum der Bestätigung *</label>
        <input id="bs-brief" class="bs-date" type="date" name="briefdatum" value="{{ old('briefdatum', $today) }}" required>
      </div>
    </div>
    <div class="sd-field">
      <label for="bs-personen">Meldepflichtige Personen * <span class="sd-muted" style="font-weight:400">(Vor- und Nachname, eine Person je Zeile)</span></label>
      <textarea id="bs-personen" name="personen" rows="4" required>{{ old('personen', $personen) }}</textarea>
    </div>
    <button class="sd-btn" type="submit">Bestätigung erstellen</button>
  </form>
</div>

<div class="sd-card">
  <h2>Mietschuldenfreiheitsbescheinigung</h2>
  <p class="sd-muted" style="margin-top:0">Bestätigt dem Mieter, dass bis zum Stichtag keine Mietschulden bestehen, etwa für einen neuen Vermieter. Das System prüft das Mieterkonto: Sind bis zum Stichtag noch Beträge offen, wird keine Bescheinigung erstellt.</p>
  @if ($offen > 0.004)
    <div class="rt-warn">Stand heute sind {{ \App\Support\Money::format($offen) }} fällig und offen. Erst nach Zahlung ist die Bescheinigung möglich.</div>
  @else
    <div class="rt-ok" style="margin-bottom:.8rem">Stand heute ist im Mieterkonto nichts offen.</div>
  @endif
  <p class="sd-muted">Hinweis: Mieten werden im System erst ab {{ $bsDe($erfasstAb) }} erfasst. Für die Zeit davor bitte selbst prüfen, ob alles bezahlt ist.</p>
  <form method="GET" action="{{ route('bescheinigung.mietschuldenfrei', $lease) }}" target="_blank" rel="noopener">
    <div class="sd-grid">
      <div class="sd-field">
        <label for="bs-stichtag">Stichtag *</label>
        <input id="bs-stichtag" class="bs-date" type="date" name="stichtag" value="{{ old('stichtag', $today) }}" max="{{ $today }}" required>
      </div>
      <div class="sd-field">
        <label for="bs-zweck">Zweck (freiwillig)</label>
        <input id="bs-zweck" type="text" name="zweck" maxlength="190" value="{{ old('zweck') }}" placeholder="z. B. zur Vorlage beim neuen Vermieter">
      </div>
    </div>
    <label class="sd-check" style="font-weight:400"><input type="checkbox" name="puenktlich" value="1" @checked(old('puenktlich'))> Zusätzlich bestätigen, dass die Miete stets pünktlich gezahlt wurde</label>
    <div style="margin-top:.8rem"><button class="sd-btn" type="submit">Bescheinigung erstellen</button></div>
  </form>
</div>
<div class="sd-card" id="abmahnung">
  <h2>Abmahnung</h2>
  <p class="sd-muted" style="margin-top:0">Eine Abmahnung muss den Verstoß genau benennen (was, wann, wer), zur Unterlassung auffordern und für den Wiederholungsfall die Kündigung androhen. Sie ist meist Voraussetzung für eine spätere Kündigung wegen Vertragsverletzung.</p>
  <form method="GET" action="{{ route('bescheinigung.abmahnung', $lease) }}" target="_blank" rel="noopener">
    <div class="sd-grid">
      <div class="sd-field">
        <label for="ab-grund">Grund *</label>
        <select id="ab-grund" name="grund" required>
          @foreach ($abmahnGruende as $k => $label)<option value="{{ $k }}" @selected(old('grund') === $k)>{{ $label }}</option>@endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="ab-frist">Frist zur Abhilfe (freiwillig)</label>
        <input id="ab-frist" class="bs-date" type="date" name="frist" value="{{ old('frist') }}">
      </div>
      <div class="sd-field">
        <label for="ab-brief">Datum des Schreibens *</label>
        <input id="ab-brief" class="bs-date" type="date" name="briefdatum" value="{{ old('briefdatum', $today) }}" required>
      </div>
    </div>
    <div class="sd-field">
      <label for="ab-sach">Was ist vorgefallen? * <span class="sd-muted" style="font-weight:400">(konkret mit Datum und Uhrzeit, ggf. Zeugen)</span></label>
      <textarea id="ab-sach" name="sachverhalt" rows="5" maxlength="4000" required placeholder="z. B. Am 24.10.2026 und am 31.10.2026 lief in Ihrer Wohnung jeweils von ca. 23:00 bis 2:30 Uhr laute Musik. Mehrere Nachbarn haben sich beschwert.">{{ old('sachverhalt') }}</textarea>
    </div>
    <div class="sd-field">
      <label for="ab-auf">Aufforderung (freiwillig, sonst passender Standardtext)</label>
      <textarea id="ab-auf" name="aufforderung" rows="2" maxlength="2000" placeholder="z. B. die Ruhezeiten künftig einzuhalten">{{ old('aufforderung') }}</textarea>
    </div>
    <button class="sd-btn" type="submit">Abmahnung erstellen</button>
  </form>
</div>

<div class="sd-card" id="kuendigung">
  <h2>Kündigung durch den Vermieter</h2>
  <div class="rt-warn">Eine Kündigung ist rechtlich heikel. Fehler bei Grund, Frist oder Form machen sie unwirksam. Bitte im Zweifel vorher von Haus &amp; Grund oder einem Anwalt prüfen lassen. Die Kündigung muss <strong>auf Papier, eigenhändig unterschrieben</strong> und an <strong>alle Mieter</strong> gerichtet sein. E-Mail oder Fax genügen nicht. Den Zugang nachweisbar machen, z. B. per Bote mit Zeuge oder Einwurf-Einschreiben.</div>
  @if ($frist)
    <p><strong>Ordentliche Kündigung bei Zugang heute:</strong> Ende am <strong>{{ $bsDe($frist['ende']) }}</strong>. {{ $frist['regel'] }}. Für dieses Ende muss das Schreiben spätestens am {{ $bsDe($frist['spaetestens']) }} beim Mieter sein.@if ($gewerbe) Gilt nur, wenn der Gewerbemietvertrag nichts anderes regelt (feste Laufzeit, eigene Fristen) – bitte im Vertrag nachsehen.@endif</p>
  @endif
  @if ($lease->lease_type === 'montage')<p class="rt-part">Montage-Wohnung: Bei Wohnraum zum nur vorübergehenden Gebrauch gelten Sonderregeln (§ 549 Abs. 2 BGB). Bitte prüfen lassen.</p>@endif
  <p><strong>Mietrückstand:</strong> {{ \App\Support\Money::format($verzug['summe']) }} bei einer Monatsmiete von {{ \App\Support\Money::format($verzug['monatsmiete']) }}. <span @class(['rt-ok' => $verzug['ok'], 'rt-part' => ! $verzug['ok']])>{{ $verzug['text'] }}</span>
    <br><span class="sd-muted">Hinweis: Zahlt der Mieter den Rückstand spätestens zwei Monate nach Zustellung einer Räumungsklage, wird die fristlose Kündigung wegen Zahlungsverzug bei Wohnraum unwirksam (§ 569 Abs. 3 Nr. 2 BGB). Deshalb wird sie üblicherweise mit einer hilfsweisen ordentlichen Kündigung verbunden.</span></p>
  <form method="GET" action="{{ route('bescheinigung.kuendigung', $lease) }}" target="_blank" rel="noopener" id="kd-form">
    <div class="sd-grid">
      <div class="sd-field">
        <label for="kd-art">Art *</label>
        <select id="kd-art" name="art" required>
          @foreach ($arten as $k => $label)<option value="{{ $k }}" @selected(old('art', $verzug['ok'] ? 'fristlos_zahlung' : 'ordentlich') === $k)>{{ $label }}</option>@endforeach
        </select>
      </div>
      @unless ($gewerbe)
        <div class="sd-field" data-kd="ordentlich">
          <label for="kd-grund">Grund (§ 573 BGB) *</label>
          <select id="kd-grund" name="grund">
            <option value="">– bitte wählen –</option>
            @foreach ($gruende as $k => $label)<option value="{{ $k }}" @selected(old('grund') === $k)>{{ $label }}</option>@endforeach
          </select>
        </div>
      @endunless
      <div class="sd-field">
        <label for="kd-zugang">Zugang beim Mieter (voraussichtlich) *</label>
        <input id="kd-zugang" class="bs-date" type="date" name="zugang" value="{{ old('zugang', $today) }}" required>
      </div>
      <div class="sd-field">
        <label for="kd-brief">Datum des Schreibens *</label>
        <input id="kd-brief" class="bs-date" type="date" name="briefdatum" value="{{ old('briefdatum', $today) }}" required>
      </div>
      <div class="sd-field" data-kd="fristlos">
        <label for="kd-raeum">Räumung und Rückgabe bis (fristlos)</label>
        <input id="kd-raeum" class="bs-date" type="date" name="raeumung" value="{{ old('raeumung') }}">
        <small class="sd-muted">Leer = zwei Wochen nach Zugang.</small>
      </div>
    </div>
    <div class="sd-field">
      <label for="kd-begr">Begründung <span class="sd-muted" style="font-weight:400">(bei Eigenbedarf: für wen, Verwandtschaft, warum diese Wohnung; bei Pflichtverletzung: was, wann, Datum der Abmahnung)</span></label>
      <textarea id="kd-begr" name="begruendung" rows="6" maxlength="5000">{{ old('begruendung') }}</textarea>
      <small class="sd-muted">Bei Zahlungsverzug füllt das System die offenen Monate selbst ein; hier kann zusätzlicher Text stehen.</small>
    </div>
    <label class="sd-check" style="font-weight:400" data-kd="fristlos"><input type="checkbox" name="hilfsweise" value="1" @checked(old('hilfsweise', true))> Hilfsweise zugleich ordentlich kündigen (empfohlen)</label>
    <div style="margin-top:.8rem"><button class="sd-btn" type="submit">Kündigung erstellen</button></div>
  </form>
</div>
<div class="sd-card" id="kuendigungsbestaetigung">
  <h2>Kündigung des Mieters bestätigen</h2>
  <p class="sd-muted" style="margin-top:0">Wenn der Mieter kündigt: Eingang und Ende bestätigen, Übergabetermin vorschlagen. Kündigungsfrist des Mieters: @if ($gewerbe) zum Ende des nächsten Quartals (§ 580a BGB, sofern der Vertrag nichts anderes regelt) @else immer 3 Monate (§ 573c BGB) @endif – bei Eingang heute endet das Mietverhältnis am <strong>{{ $bsDe($mieterFrist['ende']) }}</strong>.</p>
  <form method="POST" action="{{ route('bescheinigung.kuendigungsbestaetigung', $lease) }}" target="_blank">
    @csrf
    <div class="sd-grid">
      <div class="sd-field">
        <label for="kb-eingang">Kündigung eingegangen am *</label>
        <input id="kb-eingang" class="bs-date" type="date" name="eingang" value="{{ old('eingang', $today) }}" required>
      </div>
      <div class="sd-field">
        <label for="kb-wunsch">Vom Mieter gewünschtes Ende (freiwillig)</label>
        <input id="kb-wunsch" class="bs-date" type="date" name="gewuenscht" value="{{ old('gewuenscht') }}">
        <small class="sd-muted">Ist es früher als erlaubt, bestätigt das Schreiben das richtige Ende.</small>
      </div>
      <div class="sd-field">
        <label for="kb-tag">Übergabe der Wohnung am (freiwillig)</label>
        <input id="kb-tag" class="bs-date" type="date" name="uebergabe_tag" value="{{ old('uebergabe_tag') }}">
      </div>
      <div class="sd-field">
        <label for="kb-zeit">Uhrzeit</label>
        <input id="kb-zeit" type="text" name="uebergabe_zeit" maxlength="20" value="{{ old('uebergabe_zeit') }}" placeholder="z. B. 10:00 Uhr">
      </div>
      <div class="sd-field">
        <label for="kb-brief">Datum des Schreibens *</label>
        <input id="kb-brief" class="bs-date" type="date" name="briefdatum" value="{{ old('briefdatum', $today) }}" required>
      </div>
    </div>
    <div class="sd-field">
      <label for="kb-zusatz">Zusätzlicher Text (freiwillig)</label>
      <textarea id="kb-zusatz" name="zusatz" rows="2" maxlength="2000" placeholder="z. B. Hinweise zu Schönheitsreparaturen laut Mietvertrag">{{ old('zusatz') }}</textarea>
    </div>
    <label class="sd-check" style="font-weight:400"><input type="checkbox" name="eintragen" value="1" @checked(old('eintragen', true))> Vertragsende gleich im Mietvertrag eintragen (Soll-Mieten enden dann automatisch)</label>
    <div style="margin-top:.8rem"><button class="sd-btn" type="submit">Bestätigung erstellen</button></div>
  </form>
</div>

<div class="sd-card" id="termin">
  <h2>Termin ankündigen</h2>
  <p class="sd-muted" style="margin-top:0">Handwerker, Wartung, Zählerablesung oder Besichtigung in der Wohnung rechtzeitig ankündigen – üblich sind mindestens 1 bis 2 Wochen vorher. Für Modernisierungen gilt eine eigene Ankündigungspflicht (3 Monate, § 555c BGB); dafür bitte das Verfahren unter Mieterhöhungen nutzen.</p>
  <form method="GET" action="{{ route('bescheinigung.termin', $lease) }}" target="_blank" rel="noopener">
    <div class="sd-grid">
      <div class="sd-field">
        <label for="tm-art">Anlass *</label>
        <select id="tm-art" name="art" required>
          @foreach ($terminArten as $k => $label)<option value="{{ $k }}" @selected(old('art') === $k)>{{ $label }}</option>@endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="tm-tag">Tag *</label>
        <input id="tm-tag" class="bs-date" type="date" name="tag" value="{{ old('tag', date('Y-m-d', strtotime('+14 days'))) }}" required>
      </div>
      <div class="sd-field">
        <label>Uhrzeit von – bis</label>
        <div style="display:flex;gap:.4rem"><input class="bs-date" type="time" name="von" value="{{ old('von', '09:00') }}"><input class="bs-date" type="time" name="bis" value="{{ old('bis', '12:00') }}"></div>
      </div>
      <div class="sd-field">
        <label for="tm-wer">Wer kommt? (freiwillig)</label>
        <input id="tm-wer" type="text" name="wer" maxlength="190" value="{{ old('wer') }}" placeholder="z. B. Firma Müller Heizungsbau">
      </div>
      <div class="sd-field">
        <label for="tm-kontakt">Rückfragen an (freiwillig)</label>
        <input id="tm-kontakt" type="text" name="kontakt" maxlength="190" value="{{ old('kontakt') }}" placeholder="z. B. Tel. 0171 1234567">
      </div>
      <div class="sd-field">
        <label for="tm-melden">Bei Verhinderung melden bis (freiwillig)</label>
        <input id="tm-melden" class="bs-date" type="date" name="melden_bis" value="{{ old('melden_bis') }}">
      </div>
      <div class="sd-field">
        <label for="tm-brief">Datum des Schreibens *</label>
        <input id="tm-brief" class="bs-date" type="date" name="briefdatum" value="{{ old('briefdatum', $today) }}" required>
      </div>
    </div>
    <div class="sd-field">
      <label for="tm-beschr">Worum geht es? <span class="sd-muted" style="font-weight:400">(bei „Sonstiger Termin“ Pflicht)</span></label>
      <textarea id="tm-beschr" name="beschreibung" rows="3" maxlength="2000" placeholder="z. B. Austausch des Thermostatventils im Badezimmer">{{ old('beschreibung') }}</textarea>
    </div>
    @if ($hausAnzahl > 1)
      <label class="sd-check" style="font-weight:400"><input type="checkbox" name="alle" value="1" @checked(old('alle'))> An alle {{ $hausAnzahl }} Mietparteien im Haus (je ein Brief, z. B. für Ablesung oder Wartung)</label>
    @endif
    <div style="margin-top:.8rem"><button class="sd-btn" type="submit">Ankündigung erstellen</button></div>
  </form>
</div>
<script>
(function () {
  var art = document.getElementById('kd-art');
  function upd() {
    var f = art.value !== 'ordentlich';
    document.querySelectorAll('[data-kd]').forEach(function (el) { el.style.display = (el.dataset.kd === 'fristlos') === f ? '' : 'none'; });
  }
  art.addEventListener('change', upd); upd();
})();
</script>
@endsection
