<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<link rel="icon" type="image/png" href="/app-icon-192.png">
<meta name="viewport" content="width=device-width, initial-scale=1">
@if (str_contains((string) config('app.url'), '://test.'))
<meta name="robots" content="noindex, nofollow">
@else
<meta name="robots" content="index, follow">
@endif
@php
  use App\Support\Site\SiteSettings;
  use App\Support\Site\SiteFiles;
  $siteName = SiteSettings::name();
  $accent = SiteSettings::accent();
  $logo = SiteFiles::first('logo');
  try {
      $pubKinds = \App\Models\Listing::online()->distinct()->pluck('kind')->all();
      $hasObjects = \App\Models\SiteObject::query()->where('visible', true)->exists();
  } catch (\Throwable) {
      $pubKinds = [];
      $hasObjects = false;
  }
  $kindNames = ['wohnen' => 'Wohnungen', 'montage' => 'Monteurwohnungen', 'gewerbe' => 'Gewerbe'];
  $nav = [];
  foreach (\App\Support\Listings\Listings::PAGES as $k => [$path, $heading]) {
      if (in_array($k, $pubKinds, true)) {
          $nav[] = [route('public.'.$k), $kindNames[$k], request()->is($path) || request()->is('angebote/*')];
      }
  }
  $nav[] = [route('site.services'), 'Leistungen', request()->is('leistungen')];
  if ($hasObjects) {
      $nav[] = [route('site.objects'), 'Unsere Objekte', request()->is('unsere-objekte')];
  }
  $nav[] = [route('site.tenants'), 'Für Mieter', request()->is('fuer-mieter')];
  $nav[] = [route('public.contact'), 'Kontakt', request()->is('kontakt*')];
  $imp = [
      'name' => \App\Support\Settings::get('imprint_name'),
      'street' => \App\Support\Settings::get('imprint_street'),
      'city' => trim((\App\Support\Settings::get('imprint_zip') ?? '').' '.(\App\Support\Settings::get('imprint_city') ?? '')),
      'email' => \App\Support\Settings::get('imprint_email'),
      'phone' => \App\Support\Settings::get('imprint_phone'),
  ];
@endphp
<title>@yield('title', $siteName)</title>
@include('partials.styles')
<style>
:root{--accent:{{ $accent }};--accent-dark:{{ SiteSettings::darken($accent) }};--primary:#111827;--ink:#111827;--muted:#5b6472;--line:#e5e7eb;--paper:#ffffff;--surface:#ffffff;--soft:#f6f6f4}
body{background:var(--paper);color:var(--ink);font:17px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif}
a{color:var(--ink);text-decoration-color:var(--accent);text-underline-offset:3px}
a:hover{color:var(--accent-dark)}
h1,h2,h3{letter-spacing:-.02em;color:var(--ink)}
.btn,.pub-btn{display:inline-block;background:var(--accent);color:#fff;border:2px solid var(--accent);border-radius:0;padding:.7rem 1.3rem;font-weight:650;text-decoration:none;cursor:pointer;font:inherit;font-weight:650}
.btn:hover,.pub-btn:hover{background:var(--accent-dark);border-color:var(--accent-dark);color:#fff}
.pub-btn.ghost{background:transparent;color:var(--ink);border-color:var(--ink)}
.pub-btn.ghost:hover{background:var(--primary);color:var(--on-primary)}
.pub-head{border-bottom:1px solid var(--line);background:var(--surface);position:sticky;top:0;z-index:20}
.pub-head-inner{max-width:1180px;margin:0 auto;padding:.9rem 1.25rem;display:flex;align-items:center;gap:1.5rem}
.pub-brand{display:flex;align-items:center;gap:.6rem;text-decoration:none;color:var(--ink);font-weight:800;font-size:1.15rem;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap}
.pub-brand img{height:38px;width:auto;display:block}
.pub-brand .dot{display:inline-block;width:.55rem;height:.55rem;background:var(--accent)}
.pub-nav{display:flex;flex-wrap:wrap;gap:.2rem 1.4rem;align-items:center;margin-left:auto}
.pub-nav a{text-decoration:none;color:var(--ink);font-size:.97rem;padding:.25rem 0;border-bottom:2px solid transparent}
.pub-nav a.on,.pub-nav a:hover{border-bottom-color:var(--accent);color:var(--ink)}
.pub-nav .login{font-size:.9rem;color:var(--muted)}
.pub-menu{display:none;margin-left:auto}
.pub-menu summary{list-style:none;cursor:pointer;font-weight:650;padding:.3rem .6rem;border:1.5px solid var(--ink)}
.pub-menu summary::-webkit-details-marker{display:none}
.pub-menu[open] .pub-nav{display:flex;position:absolute;left:0;right:0;top:100%;background:var(--surface);border-bottom:1px solid var(--line);flex-direction:column;align-items:flex-start;padding:1rem 1.25rem;gap:.6rem}
@media (max-width:860px){.pub-head-inner>.pub-nav{display:none}.pub-menu{display:block}.pub-head-inner{position:relative}}
.pub-main{max-width:1180px;margin:0 auto;padding:2.2rem 1.25rem 4rem}
.pub-text{max-width:46rem}
.pub-text h2{margin-top:1.8rem;font-size:1.25rem}
.pub-text h3{margin-top:1.1rem;font-size:1.05rem}
.pub-kicker{text-transform:uppercase;letter-spacing:.14em;font-size:.8rem;font-weight:700;color:var(--accent);margin:0 0 .5rem}
.pub-h1{font-size:clamp(2rem,4.5vw,3.2rem);line-height:1.05;margin:0 0 1rem}
.pub-lead{font-size:1.15rem;color:var(--muted);max-width:44rem}
.pub-foot{background:#111827;color:#d1d5db}
.pub-foot-inner{max-width:1180px;margin:0 auto;padding:2.5rem 1.25rem;display:grid;grid-template-columns:repeat(auto-fit,minmax(14rem,1fr));gap:1.5rem;font-size:.95rem}
.pub-foot a{color:#fff;text-decoration:none}
.pub-foot a:hover{color:#fff;text-decoration:underline}
.pub-foot strong{color:#fff;display:block;margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.08em;font-size:.82rem}
.pub-foot .bar{border-top:1px solid #374151;grid-column:1/-1;padding-top:1rem;color:var(--muted);font-size:.85rem}
.test-banner{position:relative;z-index:30}
</style>
</head>
<body>
@if (str_contains((string) config('app.url'), '://test.'))
<div class="test-banner">Testumgebung – bitte nur Beispieldaten verwenden</div>
@endif
<header class="pub-head">
  <div class="pub-head-inner">
    <a class="pub-brand" href="{{ route('home') }}">@if ($logo)<img src="{{ $logo->url() }}" alt="{{ $siteName }}">@else @include('partials.logo-mark', ['lmHouse' => 'var(--ink)', 'lmAccent' => 'var(--accent)', 'lmSize' => 38])<span>{{ $siteName }}</span>@endif</a>
    <nav class="pub-nav" aria-label="Hauptmenü">
      @foreach ($nav as [$href, $label, $on])<a href="{{ $href }}" @class(['on' => $on])>{{ $label }}</a>@endforeach
      @auth<a class="login" href="{{ route('dashboard') }}">Zum System</a>@else<a class="login" href="{{ route('login') }}">Anmelden</a>@endauth
    </nav>
    <details class="pub-menu">
      <summary>Menü</summary>
      <nav class="pub-nav" aria-label="Hauptmenü (mobil)">
        @foreach ($nav as [$href, $label, $on])<a href="{{ $href }}" @class(['on' => $on])>{{ $label }}</a>@endforeach
        @auth<a class="login" href="{{ route('dashboard') }}">Zum System</a>@else<a class="login" href="{{ route('login') }}">Anmelden</a>@endauth
      </nav>
    </details>
  </div>
</header>
<main class="pub-main">
  @yield('content')
</main>
<footer class="pub-foot">
  <div class="pub-foot-inner">
    <div><strong>{{ $siteName }}</strong>@if ($imp['name']){{ $imp['name'] }}<br>@endif @if ($imp['street']){{ $imp['street'] }}<br>@endif {{ $imp['city'] }}</div>
    <div><strong>Kontakt</strong>@if ($imp['phone'])<a href="tel:{{ preg_replace('/[^0-9+]/', '', $imp['phone']) }}">{{ $imp['phone'] }}</a><br>@endif @if ($imp['email'])<a href="mailto:{{ $imp['email'] }}">{{ $imp['email'] }}</a><br>@endif <a href="{{ route('public.contact') }}">Kontaktformular</a></div>
    <div><strong>Seiten</strong>@foreach ($nav as [$href, $label])<a href="{{ $href }}">{{ $label }}</a><br>@endforeach</div>
    <div class="bar"><a href="{{ route('imprint') }}">Impressum</a> · <a href="{{ route('privacy') }}">Datenschutz</a> · © {{ date('Y') }} {{ $siteName }}</div>
  </div>
</footer>
</body>
</html>
