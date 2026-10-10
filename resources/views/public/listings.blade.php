@extends('layouts.public')
@section('title', $heading.' – '.config('app.name'))
@section('content')
@include('public.partials-listing-styles')
@php use App\Support\Listings\Listings; @endphp
<h1>{{ $heading }}</h1>
@if ($listings->isEmpty())
  <p>Zurzeit ist hier nichts frei. Gerne nehmen wir Ihre Anfrage trotzdem auf: <a href="{{ route('public.contact') }}">Kontakt</a>.</p>
@else
  <div class="pl-cards">
    @foreach ($listings as $l)
      @php($photo = $l->photos->first())
      <a class="pl-card" href="{{ route('public.listing', $l->slug) }}">
        @if ($photo)<img src="{{ $photo->url('s') }}" alt="{{ $photo->caption ?: $l->title }}" loading="lazy" width="640" height="480">@else<div class="pl-noimg">Foto folgt</div>@endif
        <div class="pl-body">
          <h2>{{ $l->title }}</h2>
          <p class="pl-meta">{{ $l->publicPlace() }}@if ($l->area_sqm) · {{ Listings::num($l->area_sqm) }} m²@endif @if ($l->rooms) · {{ Listings::num($l->rooms, 1) }} Zimmer @endif @if ($l->beds) · {{ $l->beds }} Schlafplätze @endif</p>
          <div class="pl-price">
            @if ($l->kind === 'montage')
              @if ($l->price_night !== null) ab {{ Listings::eur($l->price_night) }} pro Nacht @elseif ($l->price_week !== null) {{ Listings::eur($l->price_week) }} pro Woche @elseif ($l->price_month !== null) {{ Listings::eur($l->price_month) }} pro Monat @endif
              {{ $l->price_per_person ? '(pro Person)' : '' }}
            @elseif ($l->rent_cold !== null)
              {{ Listings::eur($l->rent_cold) }} Kaltmiete{{ $l->plus_vat ? ' zzgl. USt.' : '' }}
            @endif
          </div>
          @if ($l->kind === 'montage')
            @php($nf = $free[$l->id] ?? null)
            @if ($nf)<span class="pl-free">{{ $nf <= date('Y-m-d') ? 'jetzt frei' : 'frei ab '.\Carbon\Carbon::parse($nf)->format('d.m.Y') }}</span>@endif
          @elseif ($l->available_from && $l->available_from->format('Y-m-d') > date('Y-m-d'))
            <span class="pl-free">frei ab {{ $l->available_from->format('d.m.Y') }}</span>
          @else
            <span class="pl-free">sofort frei</span>
          @endif
        </div>
      </a>
    @endforeach
  </div>
@endif
@endsection
