@extends('portal.layout')
@section('title', 'Start')
@section('content')
@php($t = $pu->tenant)
<h1>Guten Tag, {{ $t->type === 'firma' ? $t->company_name : trim(($t->first_name ?? '').' '.($t->last_name ?? '')) }}</h1>
@foreach ($account as $a)
  <div class="sd-card">
    <strong>{{ $a['lease']->unit?->label }}, {{ $a['lease']->unit?->property?->addressLine() }}</strong>
    <p style="margin:.4rem 0 0">@if ($a['open'] > 0.004)Offen: <strong>{{ number_format($a['open'], 2, ',', '.') }} €</strong>@else Ihr Konto ist ausgeglichen.@endif</p>
  </div>
@endforeach
<div class="sd-card">
  <p style="margin:0 0 .6rem">Was möchten Sie tun?</p>
  <div style="display:flex;flex-wrap:wrap;gap:.5rem">
    <a class="sd-btn" href="{{ route('portal.damage') }}">Schaden melden</a>
    <a class="sd-btn sec" href="{{ route('portal.meters') }}">Zählerstand melden{{ $meters > 0 ? '' : ' (keine Zähler hinterlegt)' }}</a>
    <a class="sd-btn sec" href="{{ route('portal.documents') }}">Dokumente ({{ $docs }})</a>
    <a class="sd-btn sec" href="{{ route('portal.account') }}">Konto ansehen</a>
  </div>
</div>
@endsection
