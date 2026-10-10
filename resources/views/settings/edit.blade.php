@extends('layouts.app')
@section('title', 'Einstellungen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Einstellungen</h1>
  <div class="sd-actions"><a class="sd-btn" href="{{ route('site.edit') }}">Webseite gestalten</a></div>
</div>

<form method="POST" action="{{ route('settings.update') }}">
  @csrf
  @method('PUT')

  <div class="sd-card">
    <h2>Impressum und Datenschutz</h2>
    <p class="sd-muted" style="margin-top:0">Diese Angaben erscheinen öffentlich im Impressum und in der Datenschutzerklärung. Die Texte selbst sollte vor dem Livegang noch eine fachkundige Person prüfen.</p>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'imprint_name', 'label' => 'Name (Vor- und Nachname)', 'value' => $values['imprint_name'] ?? '', 'required' => true, 'autocomplete' => 'name'])
      @include('partials.sd-field', ['name' => 'imprint_street', 'label' => 'Straße und Hausnummer', 'value' => $values['imprint_street'] ?? '', 'required' => true, 'autocomplete' => 'street-address'])
      @include('partials.sd-field', ['name' => 'imprint_zip', 'label' => 'PLZ', 'value' => $values['imprint_zip'] ?? '', 'required' => true, 'inputmode' => 'numeric', 'autocomplete' => 'postal-code'])
      @include('partials.sd-field', ['name' => 'imprint_city', 'label' => 'Ort', 'value' => $values['imprint_city'] ?? '', 'required' => true, 'autocomplete' => 'address-level2'])
      @include('partials.sd-field', ['name' => 'imprint_email', 'label' => 'E-Mail-Adresse', 'type' => 'email', 'value' => $values['imprint_email'] ?? '', 'required' => true, 'hint' => 'Zum Beispiel info@baris-verwaltung.de'])
      @include('partials.sd-field', ['name' => 'imprint_phone', 'label' => 'Telefon', 'type' => 'tel', 'value' => $values['imprint_phone'] ?? '', 'hint' => 'Empfohlen, damit eine schnelle Kontaktaufnahme möglich ist.'])
      @include('partials.sd-field', ['name' => 'imprint_vat_id', 'label' => 'Umsatzsteuer-ID (falls vorhanden)', 'value' => $values['imprint_vat_id'] ?? '', 'hint' => 'Nur ausfüllen, wenn du eine eigene USt-ID hast.'])
    </div>
  </div>

  <div class="sd-card">
    <h2>Startseite</h2>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'home_tagline', 'label' => 'Kurztext unter dem Namen', 'value' => $values['home_tagline'] ?? '', 'hint' => 'Leer lassen für: „Verwaltung von Wohn- und Gewerbeimmobilien.“'])
    </div>
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ route('home') }}" target="_blank" rel="noopener">Startseite ansehen</a>
    <a class="sd-btn sec" href="{{ route('imprint') }}" target="_blank" rel="noopener">Impressum ansehen</a>
  </div>
</form>
@endsection
