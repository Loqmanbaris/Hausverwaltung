@extends('layouts.public')
@section('title', 'Angebot nicht mehr verfügbar – '.config('app.name'))
@section('content')
<div class="pub-text">
  <h1>Dieses Angebot ist nicht mehr verfügbar</h1>
  <p>Die Wohnung oder Fläche ist inzwischen vergeben. Vielleicht passt eines unserer anderen Angebote:</p>
  <ul>
    <li><a href="{{ route('public.wohnen') }}">Wohnungen</a></li>
    <li><a href="{{ route('public.montage') }}">Montage-Wohnungen</a></li>
    <li><a href="{{ route('public.gewerbe') }}">Gewerbeflächen</a></li>
    <li><a href="{{ route('public.contact') }}">Kontakt</a></li>
  </ul>
</div>
@endsection
