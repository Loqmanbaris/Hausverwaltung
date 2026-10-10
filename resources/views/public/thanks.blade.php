@extends('layouts.public')
@section('title', 'Vielen Dank – '.config('app.name'))
@section('content')
<div class="pub-text">
  <p class="pub-kicker">Vielen Dank</p>
  <h1 class="pub-h1">{{ ($topic ?? null) === 'schaden' ? 'Ihre Schadensmeldung ist angekommen' : 'Ihre Nachricht ist angekommen' }}</h1>
  <p class="pub-lead">Wir melden uns so bald wie möglich bei Ihnen.</p>
  <p><a href="{{ route('home') }}">Zur Startseite</a></p>
</div>
@endsection
