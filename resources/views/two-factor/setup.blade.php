@extends('layouts.guest')
@section('title', 'Authenticator-App einrichten')
@section('content')
<h1>Authenticator-App einrichten</h1>
<p class="muted">Zur Anmeldung gehört ein Code aus einer App auf dem Handy. Das schützt den Zugang auch dann, wenn jemand das Passwort kennt.</p>

<p><strong>1.</strong> Öffne die Authenticator-App (z. B. Microsoft Authenticator oder Google Authenticator) und füge ein neues Konto hinzu.</p>

@if ($qrSvg)
  <p><strong>2.</strong> Scanne diesen QR-Code mit der App:</p>
  <div class="qr" aria-label="QR-Code zum Einscannen">{!! $qrSvg !!}</div>
  <p class="hint">Der Scan geht nicht? Wähle in der App „Schlüssel manuell eingeben“ und tippe diesen Schlüssel ein:</p>
@else
  <p><strong>2.</strong> Wähle in der App „Schlüssel manuell eingeben“ und tippe diesen Schlüssel ein:</p>
@endif
<p class="secret mono">{{ $secretGrouped }}</p>

<form method="POST" action="{{ route('two-factor.confirm') }}" class="mt">
  @csrf
  <div class="field">
    <label for="code"><strong>3.</strong> Sechsstelligen Code aus der App eingeben</label>
    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="10" autofocus required>
    @error('code')<p class="error" role="alert">{{ $message }}</p>@enderror
  </div>
  <button class="btn primary block" type="submit">Bestätigen und einrichten</button>
</form>
<form method="POST" action="{{ route('logout') }}" class="center mt">
  @csrf
  <button class="link muted" type="submit">Abbrechen und abmelden</button>
</form>
@endsection
