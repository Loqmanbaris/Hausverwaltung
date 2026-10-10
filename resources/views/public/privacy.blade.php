@extends('layouts.public')
@section('title', 'Datenschutz – '.config('app.name'))
@section('content')
<div class="pub-text">
  <h1>Datenschutzerklärung</h1>

  @if (! $complete && auth()->check() && auth()->user()->isAdmin())
    <div class="alert warn">Die Angaben zum Verantwortlichen sind noch nicht vollständig. Bitte unter „Einstellungen“ ergänzen. Diesen Hinweis sehen nur Administratoren.</div>
  @endif

  <h2>1. Verantwortlicher</h2>
  <p>
    {{ $s['imprint_name'] ?? 'Angaben werden ergänzt' }}<br>
    @if ($s['imprint_street']){{ $s['imprint_street'] }}<br>@endif
    @if ($s['imprint_zip'] || $s['imprint_city']){{ trim(($s['imprint_zip'] ?? '').' '.($s['imprint_city'] ?? '')) }}<br>@endif
    @if ($s['imprint_email'])E-Mail: {{ $s['imprint_email'] }}<br>@endif
    @if ($s['imprint_phone'])Telefon: {{ $s['imprint_phone'] }}@endif
  </p>

  <h2>2. Hosting und Server-Protokolle</h2>
  <p>Diese Webseite wird bei IONOS SE, Elgendorfer Str. 57, 56410 Montabaur betrieben. Beim Aufruf der Seite verarbeitet der Server technisch notwendige Daten, nämlich die IP-Adresse, Datum und Uhrzeit, die aufgerufene Seite und Angaben zum Browser. Diese Daten dienen dem sicheren und stabilen Betrieb und der Abwehr von Angriffen. Sie werden nicht mit anderen Daten zusammengeführt und vom Hosting-Anbieter nur für begrenzte Zeit gespeichert.</p>
  <p>Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse am sicheren Betrieb der Webseite).</p>

  <h2>3. Cookies</h2>
  <p>Diese Webseite setzt nur technisch notwendige Cookies. Sie dienen dazu, die Anmeldung im geschützten Bereich zu ermöglichen und Anfragen vor Fälschung zu schützen. Ohne diese Cookies funktioniert die Anmeldung nicht. Eine Einwilligung ist dafür nicht erforderlich (§ 25 Abs. 2 Nr. 2 TDDDG, Art. 6 Abs. 1 lit. f DSGVO). Es gibt keine Cookies zu Werbe- oder Analysezwecken.</p>

  <h2>4. Keine Analyse, kein Tracking, keine fremden Inhalte</h2>
  <p>Wir setzen keine Analyse-, Tracking- oder Werbedienste ein und binden keine Inhalte externer Anbieter ein (zum Beispiel Karten, Videos oder Schriftarten von fremden Servern).</p>

  <h2>5. Kontaktformular und Anfragen</h2>
  <p>Wenn Sie uns über das Kontaktformular oder per E-Mail anfragen, speichern wir Ihre Angaben (Name, gegebenenfalls Firma, E-Mail-Adresse, Telefonnummer, gewünschter Zeitraum, Personenzahl und Ihre Nachricht), um die Anfrage zu bearbeiten und Ihnen zu antworten. Rechtsgrundlage ist Art. 6 Abs. 1 lit. b DSGVO (Anbahnung eines Miet- oder Beherbergungsvertrags) sowie Ihre Einwilligung nach Art. 6 Abs. 1 lit. a DSGVO, die Sie jederzeit für die Zukunft widerrufen können. Die Daten werden nicht an Dritte weitergegeben. Kommt kein Vertrag zustande, löschen wir die Anfrage spätestens sechs Monate nach ihrer Erledigung.</p>
  <p>Zum Schutz vor missbräuchlichen Massenanfragen wird beim Absenden kurzzeitig ein anonymisierter Wert Ihrer IP-Adresse verwendet (höchstens eine Stunde).</p>

  <h2>6. Geschützter Bereich</h2>
  <p>Der Bereich hinter der Anmeldung ist nicht öffentlich und nur für berechtigte Personen zugänglich. Dort werden Daten zur Verwaltung von Immobilien verarbeitet. Über die Verarbeitung der Daten von Mietern und Interessenten informieren wir gesondert, zum Beispiel zusammen mit dem Mietvertrag oder der Selbstauskunft.</p>

  <h2>7. Ihre Rechte</h2>
  <p>Sie haben das Recht auf Auskunft über die zu Ihrer Person gespeicherten Daten (Art. 15 DSGVO), auf Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch gegen die Verarbeitung (Art. 21). Wenden Sie sich dazu an die oben genannte Adresse.</p>
  <p>Außerdem können Sie sich bei einer Datenschutz-Aufsichtsbehörde beschweren, zum Beispiel bei der Behörde, die für Ihren Wohnort oder für den Sitz des Verantwortlichen zuständig ist.</p>

  <p class="muted" style="margin-top:1.6rem">Stand: Oktober 2026</p>
</div>
@endsection
