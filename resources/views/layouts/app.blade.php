<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#202c34">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="apple-touch-icon" href="/app-icon-192.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Verwaltung">
<title>@yield('title', 'Übersicht') – {{ config('app.name') }}</title>
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
:root{--brand:{{ $brand }};--focus:{{ $brand }};--hv-dark:#202c34;--hv-dark2:#2b3a46;--paper:#f4f5f7}
/* Aufbau wie im Telemaster-Portal: Kopfleiste und Menü in Anthrazit, aktiver Punkt in der Farbe der Hausverwaltung */
@@font-face{font-family:"Roboto";font-style:normal;font-weight:400;font-display:swap;src:url("/fonts/roboto-latin-400-normal.woff2") format("woff2")}
@@font-face{font-family:"Roboto";font-style:normal;font-weight:500;font-display:swap;src:url("/fonts/roboto-latin-500-normal.woff2") format("woff2")}
@@font-face{font-family:"Roboto";font-style:normal;font-weight:700;font-display:swap;src:url("/fonts/roboto-latin-700-normal.woff2") format("woff2")}
body{min-height:100vh;font-family:"Roboto",system-ui,-apple-system,"Segoe UI","Helvetica Neue",Arial,sans-serif;background:var(--paper)}
.shell{display:grid;grid-template-columns:256px minmax(0,1fr);min-height:100vh}
.sb{background:var(--hv-dark);color:#fff;position:sticky;top:0;height:100vh;overflow-y:auto;padding:0 .75rem 1.2rem;display:flex;flex-direction:column;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.18) transparent}
.sb-brand{display:flex;align-items:center;gap:.6rem;height:60px;margin:0 -.75rem .9rem;padding:0 1.2rem;text-decoration:none;color:#fff;font-weight:700;letter-spacing:.03em;text-transform:uppercase;font-size:.95rem;border-bottom:1px solid rgba(255,255,255,.06);flex:none}
.sb-brand:hover{color:#fff}
.sb-brand img{max-height:34px;max-width:170px;width:auto;background:#fff;border-radius:7px;padding:3px 7px}
.sb-brand .dot{width:.7rem;height:.7rem;border-radius:3px;background:var(--brand);flex:none}
.sb-group{margin:.2rem 0 .7rem}
.sb-title{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.45);padding:.45rem .7rem .3rem}
.sb-top{margin-bottom:.5rem}
.sb-fold{margin:.15rem 0}
.sb-fold>summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:.5rem;border-radius:8px;padding:.5rem .7rem}
.sb-fold>summary::-webkit-details-marker{display:none}
.sb-fold>summary::after{content:"\203A";font-size:1rem;line-height:1;color:rgba(255,255,255,.4);margin-left:auto;transition:transform .15s}
.sb-fold[open]>summary::after{transform:rotate(90deg)}
.sb-fold>summary:hover{background:rgba(255,255,255,.06);color:rgba(255,255,255,.7)}
.sb-fold[open]>summary{color:rgba(255,255,255,.6)}
.sb-fold>summary .sb-sum{order:2;margin-left:auto}
.sb-fold[data-on]>summary{color:#fff}
.sb-link{display:flex;align-items:center;gap:.7rem;padding:.5rem .7rem;margin:1px 0;border-radius:8px;color:rgba(255,255,255,.82);text-decoration:none;font-size:.93rem;position:relative;transition:background .12s}
.sb-link>.sb-ic+span{flex:1;min-width:0}
.sb-link:hover{background:rgba(255,255,255,.07);color:#fff}
.sb-ic{display:inline-flex;width:20px;height:20px;flex:none;color:rgba(255,255,255,.55)}
.sb-ic svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}
.sb-link.on{background:linear-gradient(135deg,var(--brand),color-mix(in srgb,var(--brand) 72%,#000));color:#fff;font-weight:600;box-shadow:0 2px 8px color-mix(in srgb,var(--brand) 35%,transparent)}
.sb-link.on .sb-ic{color:#fff}
.sb-badge{background:var(--brand);color:#fff;border-radius:1rem;font-size:.72rem;font-weight:700;padding:.05rem .45rem;line-height:1.4}
.sb-link.on .sb-badge{background:#fff;color:var(--brand)}
.sb-foot{margin-top:auto;padding:.9rem .7rem 0;border-top:1px solid rgba(255,255,255,.08);font-size:.85rem;color:rgba(255,255,255,.55)}
.sb-foot a{color:rgba(255,255,255,.65);text-decoration:none}
.sb-foot a:hover{color:#fff}
.mainwrap{min-width:0}
.container.wide{max-width:none}
.mainwrap > main{min-width:0;overflow-x:auto}
.mainwrap > main > table{display:block;overflow-x:auto}
.topbar{background:var(--hv-dark);border-bottom:1px solid rgba(255,255,255,.06);position:sticky;top:0;z-index:15;color:#fff}
.topbar-inner{padding:0 1.25rem;height:60px;display:flex;align-items:center;gap:.8rem}
.topsearch{flex:1;max-width:460px;margin:0 0 0 auto}
.topsearch input{padding:.5rem .8rem;border-radius:8px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);color:#fff}
.topsearch input::placeholder{color:rgba(255,255,255,.55)}
.topsearch input:focus{background:rgba(255,255,255,.14);border-color:rgba(255,255,255,.3);outline:none}
.usermenu{margin-left:0;display:flex;align-items:center;gap:.9rem;font-size:.92rem;white-space:nowrap}
.usermenu .who{display:flex;align-items:center;gap:.5rem;color:#fff;font-weight:500}
.usermenu .av{width:2rem;height:2rem;border-radius:50%;background:var(--brand);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700}
.usermenu form{margin:0}
.link{background:none;border:0;color:inherit;font:inherit;text-decoration:underline;cursor:pointer;padding:0}
.pm{position:relative}
.pm>summary{list-style:none;cursor:pointer;border-radius:2rem;padding:.2rem .6rem .2rem .2rem}
.pm>summary::-webkit-details-marker{display:none}
.pm>summary:hover,.pm[open]>summary{background:rgba(255,255,255,.1)}
.pm .av{position:relative}
.pm-dot{position:absolute;top:-2px;right:-2px;width:.62rem;height:.62rem;border-radius:50%;background:#facc15;border:2px solid var(--hv-dark)}
.pm-caret{color:rgba(255,255,255,.6);font-size:.75rem}
.pm-box{position:absolute;right:0;top:calc(100% + .45rem);min-width:15rem;background:#fff;color:var(--ink);border:1px solid var(--line);border-radius:12px;box-shadow:0 12px 32px rgba(0,0,0,.18);padding:.35rem;z-index:30;white-space:normal}
.pm-head{display:flex;flex-direction:column;padding:.5rem .65rem .6rem;border-bottom:1px solid var(--line);margin-bottom:.3rem}
.pm-head small{color:var(--muted);font-weight:500}
.pm-box a,.pm-box button{display:flex;align-items:center;gap:.6rem;width:100%;padding:.55rem .65rem;border-radius:8px;color:var(--ink);text-decoration:none;background:none;border:0;font:inherit;text-align:left;cursor:pointer}
.pm-box a:hover,.pm-box button:hover{background:#f3f4f6;color:var(--ink)}
.pm-box a .sb-badge{margin-left:auto}
.pm-sep{height:1px;background:var(--line);margin:.3rem 0}
.menu-btn{display:none;border:1px solid rgba(255,255,255,.2);background:rgba(255,255,255,.06);color:#fff;border-radius:8px;padding:.42rem .7rem;font:inherit;font-weight:500;cursor:pointer}
.sb-close{display:none}
.sb-shade{display:none}
/* Karten wie im Portal: weiß, runde Ecken, feiner Rahmen */
.panel,.sd-card,.card{border-radius:12px!important;box-shadow:0 1px 2px rgba(15,23,42,.05),0 0 0 1px rgba(15,23,42,.04)}
@@media (max-width:960px){
  .shell{grid-template-columns:minmax(0,1fr)}
  .sb{position:fixed;left:0;top:0;bottom:0;width:276px;z-index:40;transform:translateX(-105%);transition:transform .18s ease;box-shadow:0 10px 40px rgba(0,0,0,.35)}
  body.nav-open .sb{transform:none}
  body.nav-open .sb-shade{display:block}
  .sb-shade{display:none;position:fixed;inset:0;background:rgba(17,24,39,.45);z-index:35}
  .menu-btn{display:inline-block}
  .usermenu .who span.n,.usermenu .pm-caret{display:none}
  .sb-close{display:block;margin:.8rem 0 .2rem auto}
  .sb-brand{margin-top:0}
}
@@media (max-width:560px){.topsearch{max-width:none}.topbar-inner{padding:0 .8rem;gap:.55rem}}
</style>
</head>
<body>
@if (str_contains((string) config('app.url'), '://test.'))
<div class="test-banner">Testumgebung – bitte nur Beispieldaten verwenden</div>
@endif
<div class="shell">
  <aside class="sb" id="sb" aria-label="Navigation">
    <button class="menu-btn sb-close" type="button" onclick="document.body.classList.remove('nav-open')">Schließen ✕</button>
    <a class="sb-brand" href="{{ route('dashboard') }}">@if ($logo)<img src="{{ $logo->url() }}" alt="{{ config('app.name') }}">@else<span class="dot"></span>{{ config('app.name') }}@endif</a>
    <nav>@include('partials.nav-main')</nav>
    <div class="sb-foot"><a href="{{ route('profile.settings') }}">Einstellungen</a><br><a href="{{ route('home') }}" target="_blank" rel="noopener">Webseite ansehen ↗</a></div>
  </aside>
  <div class="sb-shade" onclick="document.body.classList.remove('nav-open')"></div>
  <div class="mainwrap">
    <header class="topbar">
      <div class="topbar-inner">
        <button class="menu-btn" type="button" onclick="document.body.classList.add('nav-open')" aria-controls="sb">☰ Menü</button>
        <form class="topsearch" method="GET" action="{{ route('search') }}" role="search">
          <input type="text" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" maxlength="100" placeholder="Suchen: Mieter, Objekt, Beleg …" aria-label="Suche">
        </form>
        {{-- Profilmenü (rundes Zeichen): wie im Portal und bei Freytag nur das Nötigste, alles andere unter „Einstellungen“ als Kacheln --}}
        @php($pmBadge = (function () { try { return \App\Http\Controllers\ProfileSettingsController::badge(request()); } catch (\Throwable) { return 0; } })())
        <details class="usermenu pm" id="pm">
          <summary class="who" aria-label="Profilmenü"><span class="av">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}@if ($pmBadge > 0)<i class="pm-dot"></i>@endif</span><span class="n">{{ auth()->user()->name }}</span><span class="pm-caret" aria-hidden="true">▾</span></summary>
          <div class="pm-box">
            <div class="pm-head"><b>{{ auth()->user()->name }}</b><small>{{ auth()->user()->roleLabel() }}</small></div>
            <a href="{{ route('profile.settings') }}"><span>⚙️</span><span>Einstellungen</span>@if ($pmBadge > 0)<span class="sb-badge">{{ $pmBadge }}</span>@endif</a>
            <a href="{{ route('password.change') }}"><span>🔑</span><span>Passwort ändern</span></a>
            <a href="#" data-install><span>📱</span><span>App installieren</span></a>
            <div class="pm-sep"></div>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit"><span>↩</span><span>Abmelden</span></button>
            </form>
          </div>
        </details>
      </div>
    </header>
    <main class="container @yield('main_class')">
      @if (session('status'))
        <div class="alert ok" role="status">{{ session('status') }}</div>
      @endif
      @yield('content')
    </main>
  </div>
</div>
<script>
document.addEventListener('click',function(e){var m=document.getElementById('pm');if(m&&m.open&&!m.contains(e.target)){m.open=false;}});
document.addEventListener('keydown',function(e){var m=document.getElementById('pm');if(e.key==='Escape'&&m){m.open=false;}});
</script>
<script>
(function(){
  var K='sbOpen',st={};
  try{st=JSON.parse(localStorage.getItem(K)||'{}')||{};}catch(e){}
  document.querySelectorAll('#sb details.sb-fold').forEach(function(d){
    var g=d.dataset.g;
    if(!d.hasAttribute('data-on')&&st[g]===1){d.open=true;}
    d.addEventListener('toggle',function(){
      st[g]=d.open?1:0;
      try{localStorage.setItem(K,JSON.stringify(st));}catch(e){}
    });
  });
})();
</script>
<script>
(function(){
  if('serviceWorker' in navigator){window.addEventListener('load',function(){navigator.serviceWorker.register('/sw.js',{scope:'/'}).catch(function(){});});}
  var standalone=(window.matchMedia&&window.matchMedia('(display-mode: standalone)').matches)||navigator.standalone===true;
  var deferred=null;
  var hide=function(){document.querySelectorAll('[data-install]').forEach(function(el){el.hidden=true;});};
  window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferred=e;});
  window.addEventListener('appinstalled',function(){deferred=null;hide();});
  if(standalone){hide();return;}
  document.querySelectorAll('[data-install]').forEach(function(el){
    el.addEventListener('click',function(ev){
      ev.preventDefault();
      if(deferred){deferred.prompt();deferred.userChoice.then(function(){deferred=null;});return;}
      var ios=/iphone|ipad|ipod/i.test(navigator.userAgent);
      alert(ios?'So installierst du die App: Tippe unten auf „Teilen“ und dann auf „Zum Home-Bildschirm“.':'So installierst du die App: Öffne das Browser-Menü (⋮ oben rechts) und wähle „App installieren“ oder „Zum Startbildschirm hinzufügen“.');
    });
  });
})();
</script>
</body>
</html>
