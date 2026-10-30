@extends('portal.layout')
@section('title', 'Link nicht mehr gültig')
@section('content')
<h1>Dieser Link ist nicht mehr gültig</h1>
<p>Er ist abgelaufen oder wurde schon benutzt. Bitte fordern Sie einen neuen an.</p>
<p><a class="sd-btn" href="{{ route('portal.login') }}">Neuen Link anfordern</a></p>
@endsection
