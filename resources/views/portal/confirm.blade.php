@extends('portal.layout')
@section('title', 'Anmelden')
@section('content')
<h1>Jetzt anmelden</h1>
<p>Tippen Sie auf den Knopf, um sich im Mieterportal anzumelden.</p>
<form method="POST" action="{{ route('portal.enter', $token) }}">@csrf<button class="sd-btn" type="submit">Anmelden</button></form>
@endsection
