@extends('layouts.app')
@section('title', 'Benutzer anlegen')
@section('content')
<div class="narrow">
  <h1>Benutzer anlegen</h1>
  <form method="POST" action="{{ route('users.store') }}" class="panel">
    @csrf
    <div class="field">
      <label for="name">Name</label>
      <input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="off" required>
      @error('name')<p class="error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="field">
      <label for="email">E-Mail-Adresse</label>
      <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="off" required>
      @error('email')<p class="error" role="alert">{{ $message }}</p>@enderror
    </div>
    <div class="field">
      <label for="role">Rolle</label>
      <select id="role" name="role" required>
        <option value="staff" @selected(old('role', 'staff') === 'staff')>Mitarbeiter</option>
        <option value="admin" @selected(old('role') === 'admin')>Administrator</option>
      </select>
      <p class="hint">Mitarbeiter dürfen alles außer Einstellungen und Benutzerverwaltung. Administratoren dürfen alles.</p>
      @error('role')<p class="error" role="alert">{{ $message }}</p>@enderror
    </div>
    <p class="hint">Das System vergibt ein vorläufiges Passwort und zeigt es danach einmal an.</p>
    <button class="btn primary" type="submit">Benutzer anlegen</button>
    <a class="btn" href="{{ route('users.index') }}">Abbrechen</a>
  </form>
</div>
@endsection
