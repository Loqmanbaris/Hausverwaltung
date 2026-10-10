@extends('layouts.guest')
@section('title', 'Anmelden')
@section('content')
<h1>Anmelden</h1>
<form method="POST" action="{{ route('login.store') }}">
  @csrf
  @if ($errors->any())
    <div class="alert error" role="alert">{{ $errors->first() }}</div>
  @endif
  <div class="field">
    <label for="email">E-Mail-Adresse</label>
    <input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" autofocus required>
  </div>
  <div class="field">
    <label for="password">Passwort</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
  </div>
  <button class="btn primary block" type="submit">Weiter</button>
</form>
@endsection
