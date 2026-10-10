@extends('layouts.app')
@section('title', 'Passwort ändern')
@section('content')
<div class="narrow">
  <h1>Passwort ändern</h1>
  @if ($forced)
    <div class="alert warn">Dein Passwort ist vorläufig. Bitte wähle jetzt ein eigenes, bevor du weiterarbeitest.</div>
  @endif
  <form method="POST" action="{{ route('password.update') }}" class="panel">
    @csrf
    @method('PUT')
    <div class="field">
      <label for="current_password">Aktuelles Passwort</label>
      <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
      @error('current_password')<p class="error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="field">
      <label for="password">Neues Passwort</label>
      <input id="password" name="password" type="password" autocomplete="new-password" required>
      <p class="hint">Mindestens 12 Zeichen, mit Groß- und Kleinbuchstaben und einer Zahl.</p>
      @error('password')<p class="error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="field">
      <label for="password_confirmation">Neues Passwort wiederholen</label>
      <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
    </div>
    <button class="btn primary" type="submit">Passwort speichern</button>
  </form>
</div>
@endsection
