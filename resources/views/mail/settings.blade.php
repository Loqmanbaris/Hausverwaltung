@extends('layouts.app')
@section('title', 'E-Mail einrichten')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>E-Mail einrichten</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('mail.index') }}">Gesendete E-Mails</a></div>
</div>

@if ($configured)
  <div class="sd-card" style="border-left:4px solid #067647"><strong style="color:#067647">Der E-Mail-Versand ist eingerichtet.</strong> Mit einer Test-E-Mail prüfst du, ob die Verbindung klappt.</div>
@else
  <div class="sd-card" style="border-left:4px solid #b54708"><strong>Noch nicht eingerichtet.</strong> Trage hier die Zugangsdaten deines Postfachs ein. Bei IONOS findest du sie unter „E-Mail“ → Postfach → „Zugangsdaten“.</div>
@endif

<form method="POST" action="{{ route('mail.settings.update') }}">
  @csrf
  @method('PUT')
  <div class="sd-card">
    <h2>Postfach</h2>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'mail_from_address', 'label' => 'Absender-Adresse', 'type' => 'email', 'value' => $v['mail_from_address'], 'required' => true, 'hint' => 'z. B. info@baris-verwaltung.de'])
      @include('partials.sd-field', ['name' => 'mail_from_name', 'label' => 'Absender-Name', 'value' => $v['mail_from_name'], 'hint' => 'So erscheint der Absender beim Mieter, z. B. „Baris Hausverwaltung“.'])
      @include('partials.sd-field', ['name' => 'mail_username', 'label' => 'Benutzername', 'value' => $v['mail_username'], 'required' => true, 'autocomplete' => 'off', 'hint' => 'Bei IONOS die vollständige E-Mail-Adresse.'])
      <div class="sd-field">
        <label for="f-mail_password">Passwort des Postfachs{{ $hasPassword ? '' : ' *' }}</label>
        <input id="f-mail_password" name="mail_password" type="password" autocomplete="new-password" @if (! $hasPassword) required @endif placeholder="{{ $hasPassword ? 'gespeichert – nur zum Ändern ausfüllen' : '' }}">
        <small class="sd-muted">Wird verschlüsselt gespeichert und nie wieder angezeigt.</small>
      </div>
      @include('partials.sd-field', ['name' => 'mail_host', 'label' => 'Postausgangsserver (SMTP)', 'value' => $v['mail_host'], 'required' => true, 'hint' => 'IONOS: smtp.ionos.de'])
      <div class="sd-field">
        <label for="f-mail_port">Port *</label>
        <select id="f-mail_port" name="mail_port">
          @foreach (['587' => '587 (STARTTLS, empfohlen)', '465' => '465 (SSL/TLS)', '25' => '25', '2525' => '2525'] as $p => $pl)
            <option value="{{ $p }}" @selected(old('mail_port', $v['mail_port']) == $p)>{{ $pl }}</option>
          @endforeach
        </select>
      </div>
    </div>
  </div>

  <div class="sd-card">
    <h2>Kopie und Unterschrift</h2>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'mail_bcc', 'label' => 'Blindkopie jeder E-Mail an', 'type' => 'email', 'value' => $v['mail_bcc'], 'hint' => 'Leer lassen = an die Absender-Adresse. So liegt jede gesendete E-Mail auch in deinem Postfach.'])
      <div class="sd-field" style="grid-column:1/-1">
        <label for="f-mail_signature">Zusatz unter jeder E-Mail (Signatur)</label>
        <textarea id="f-mail_signature" name="mail_signature" rows="4" style="width:100%;font:inherit;padding:.55rem .7rem;border:1px solid var(--line-strong);border-radius:8px" placeholder="z. B. Telefon, Anschrift, Webseite">{{ old('mail_signature', $v['mail_signature']) }}</textarea>
      </div>
      <label style="display:flex;gap:.5rem;align-items:center;font-weight:500;grid-column:1/-1">
        <input type="checkbox" name="mail_notify" value="1" @checked(old('mail_notify', $v['mail_notify']) === '1')>
        Bei neuen Anfragen über die Webseite eine Nachricht an die Kopie-Adresse schicken
      </label>
    </div>
  </div>
  <div class="sd-actions"><button class="sd-btn" type="submit">Speichern</button></div>
</form>

@if ($configured)
  <form method="POST" action="{{ route('mail.test') }}" class="sd-card" style="margin-top:1rem">
    @csrf
    <h2>Test-E-Mail</h2>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:flex-end">
      <div class="sd-field" style="margin:0;flex:1 1 16rem"><label for="f-test_to">An</label><input id="f-test_to" name="test_to" type="email" required value="{{ old('test_to', $v['mail_bcc'] ?: $v['mail_from_address']) }}"></div>
      <button class="sd-btn sec" type="submit">Test-E-Mail senden</button>
    </div>
  </form>
@endif
@endsection
