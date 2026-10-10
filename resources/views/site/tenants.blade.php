@extends('layouts.public')
@section('title', 'Für Mieter – '.\App\Support\Site\SiteSettings::name())
@section('content')
<style>
.tn-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:2rem;margin-top:2rem}
.tn-box{border-top:2px solid var(--ink);padding-top:1rem}
.tn-box h2{font-size:1.3rem;margin:0 0 .6rem}
.tn-box p{white-space:pre-line}
.tn-em{list-style:none;padding:0;margin:0}
.tn-em li{display:flex;justify-content:space-between;gap:1rem;padding:.55rem 0;border-bottom:1px solid var(--line)}
.tn-em a{font-weight:750;text-decoration:none;white-space:nowrap}
.tn-dl{list-style:none;padding:0;margin:0}
.tn-dl li{padding:.5rem 0;border-bottom:1px solid var(--line)}
.tn-dl a{font-weight:650}
</style>
<p class="pub-kicker">Für Mieter</p>
<h1 class="pub-h1">Service für unsere Mieter</h1>
@if ($intro)<p class="pub-lead">{{ $intro }}</p>@endif
<div class="tn-grid">
  <div class="tn-box">
    <h2>Schaden melden</h2>
    <p>{{ $damage }}</p>
    <a class="pub-btn" href="{{ route('public.contact', ['thema' => 'schaden']) }}">Schaden melden</a>
  </div>
  @if ($emergency)
    <div class="tn-box">
      <h2>Notfallnummern</h2>
      <ul class="tn-em">
        @foreach ($emergency as $e)
          <li><span>{{ $e['label'] }}</span>@if ($e['number'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $e['number']) }}">{{ $e['number'] }}</a>@endif</li>
        @endforeach
      </ul>
    </div>
  @endif
  @if ($downloads->isNotEmpty())
    <div class="tn-box">
      <h2>Downloads</h2>
      <ul class="tn-dl">
        @foreach ($downloads as $d)<li><a href="{{ $d->url() }}" target="_blank" rel="noopener">{{ $d->title ?: 'Dokument' }}</a> <span style="color:var(--muted)">(PDF)</span></li>@endforeach
      </ul>
    </div>
  @endif
</div>
@endsection
