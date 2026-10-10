@extends('layouts.guest')
@section('title', 'Wiederherstellungscodes')
@section('content')
<h1>Wiederherstellungscodes sichern</h1>
<div class="alert warn">Diese Codes werden nur jetzt angezeigt. Jeder Code funktioniert einmal und ersetzt den App-Code, falls das Handy verloren geht oder kaputt ist.</div>
<div class="codes mono">
  @foreach ($codes as $code)
    <span>{{ $code }}</span>
  @endforeach
</div>
<p class="muted">Schreibe die Codes auf oder speichere sie im Passwortmanager, getrennt vom Handy.</p>
<a class="btn primary block" href="{{ route('dashboard') }}">Codes gesichert, weiter</a>
@endsection
