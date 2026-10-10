@extends('layouts.public')
@section('title', 'Kontakt – '.config('app.name'))
@section('content')
@include('public.partials-listing-styles')
@php($email = \App\Support\Settings::get('imprint_email'))
@php($phone = \App\Support\Settings::get('imprint_phone'))
<div class="pub-text">
  <p class="pub-kicker">{{ $topic === 'schaden' ? 'Für Mieter' : 'Kontakt' }}</p>
  <h1 class="pub-h1">{{ $topic === 'schaden' ? 'Schaden melden' : 'Schreiben Sie uns' }}</h1>
  @if ($topic === 'schaden')
    <p class="pub-lead">Beschreiben Sie den Schaden möglichst genau. Bei Gefahr (Wasserrohrbruch, Gasgeruch) bitte sofort die <a href="{{ route('site.tenants') }}">Notfallnummern</a> anrufen.</p>
  @else
    <p class="pub-lead">Sie suchen eine Wohnung, eine Unterkunft für Ihre Monteure oder eine Gewerbefläche? Schreiben Sie uns, wir melden uns zeitnah.</p>
  @endif
  @if ($email || $phone)
    <p>@if ($email)E-Mail: <a href="mailto:{{ $email }}">{{ $email }}</a><br>@endif @if ($phone)Telefon: <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}">{{ $phone }}</a>@endif</p>
  @endif
  @include('public.contact-form', ['listing' => null, 'topic' => $topic])
</div>
@endsection
