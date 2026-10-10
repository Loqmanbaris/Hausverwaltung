@extends('layouts.guest')
@section('title', 'Bestätigungscode')
@section('content')
<h1>Bestätigungscode eingeben</h1>
<p class="muted">Öffne die Authenticator-App auf dem Handy und gib den sechsstelligen Code ein. Ohne Handy geht auch ein Wiederherstellungscode.</p>
<form method="POST" action="{{ route('two-factor.verify') }}">
  @csrf
  <div class="field">
    <label for="code">Code</label>
    <input id="code" name="code" type="text" autocomplete="one-time-code" autocapitalize="off" autocorrect="off" spellcheck="false" maxlength="32" autofocus required>
    @error('code')<p class="error" role="alert">{{ $message }}</p>@enderror
  </div>
  <button class="btn primary block" type="submit">Anmelden</button>
</form>
<form method="POST" action="{{ route('logout') }}" class="center mt">
  @csrf
  <button class="link muted" type="submit">Abbrechen und abmelden</button>
</form>
@endsection
