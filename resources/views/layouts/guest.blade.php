<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Anmelden') – {{ config('app.name') }}</title>
@include('partials.styles')
@php
  try {
      $brand = \App\Support\Site\SiteSettings::accent();
      $logo = \App\Support\Site\SiteFiles::first('logo');
  } catch (\Throwable) {
      $brand = '#c2410c';
      $logo = null;
  }
@endphp
<style>
:root{--brand:{{ $brand }};--focus:{{ $brand }}}
.gbrand{display:flex;align-items:center;justify-content:center;gap:.55rem;margin:2.5rem 0 1.2rem;font-weight:800;letter-spacing:.04em;text-transform:uppercase;text-decoration:none;color:var(--ink)}
.gbrand img{max-height:40px;width:auto}
.gbrand .dot{width:.65rem;height:.65rem;background:var(--brand)}
.panel h1{font-size:1.35rem}
.btn.primary{width:100%}
</style>
</head>
<body>
@if (str_contains((string) config('app.url'), '://test.'))
<div class="test-banner">Testumgebung – bitte nur Beispieldaten verwenden</div>
@endif
<main class="container narrow">
  <a class="gbrand" href="{{ route('home') }}">@if ($logo)<img src="{{ $logo->url() }}" alt="{{ config('app.name') }}">@else<span class="dot"></span>{{ config('app.name') }}@endif</a>
  <div class="panel">
    @yield('content')
  </div>
  <p class="center muted" style="margin-top:1rem;font-size:.88rem"><a href="{{ route('home') }}" style="color:var(--muted)">← Zur Webseite</a></p>
</main>
</body>
</html>
