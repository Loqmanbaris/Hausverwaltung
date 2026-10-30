<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
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
:root{--brand:{{ $brand }};--focus:{{ $brand }}}
body{min-height:100vh}
.shell{display:grid;grid-template-columns:248px minmax(0,1fr);min-height:100vh}
.sb{background:#fff;border-right:1px solid var(--line);position:sticky;top:0;height:100vh;overflow-y:auto;padding:1rem .75rem 2rem;display:flex;flex-direction:column}
.sb-brand{display:flex;align-items:center;gap:.55rem;padding:.35rem .6rem 1rem;text-decoration:none;color:var(--ink);font-weight:800;letter-spacing:.03em;text-transform:uppercase;font-size:.95rem}
.sb-brand img{max-height:30px;max-width:150px;width:auto}
.sb-brand .dot{width:.6rem;height:.6rem;background:var(--brand);flex:none}
.sb-group{margin:.2rem 0 .7rem}
.sb-title{font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:#9ca3af;padding:.4rem .6rem .25rem}
.sb-top{margin-bottom:.4rem}
.sb-fold{margin:.1rem 0}
.sb-fold>summary{list-style:none;cursor:pointer;display:flex;justify-content:space-between;align-items:center;gap:.5rem;border-radius:7px;padding:.45rem .6rem}
.sb-fold>summary::-webkit-details-marker{display:none}
.sb-fold>summary::after{content:"\203A";font-size:1rem;line-height:1;color:#9ca3af;margin-left:auto;transition:transform .15s}
.sb-fold[open]>summary::after{transform:rotate(90deg)}
.sb-fold>summary:hover{background:#f3f4f6;color:#6b7280}
.sb-fold[open]>summary{color:#6b7280}
.sb-fold>summary .sb-sum{order:2;margin-left:auto}
.sb-fold[data-on]>summary{color:var(--ink)}
.sb-fold .sb-link{margin-left:.25rem}
.sb-link{display:flex;justify-content:space-between;align-items:center;gap:.5rem;padding:.42rem .6rem;border-radius:7px;color:#374151;text-decoration:none;font-size:.93rem;position:relative}
.sb-link:hover{background:#f3f4f6;color:var(--ink)}
.sb-link.on{background:color-mix(in srgb,var(--brand) 10%,#fff);color:var(--ink);font-weight:650}
.sb-link.on::before{content:"";position:absolute;left:-.75rem;top:.35rem;bottom:.35rem;width:3px;background:var(--brand);border-radius:0 3px 3px 0}
.sb-badge{background:var(--brand);color:#fff;border-radius:1rem;font-size:.72rem;font-weight:700;padding:.05rem .45rem;line-height:1.4}
.sb-foot{margin-top:auto;padding:.8rem .6rem 0;border-top:1px solid var(--line);font-size:.85rem;color:var(--muted)}
.sb-foot a{color:var(--muted)}
.mainwrap{min-width:0}
.mainwrap > main{min-width:0;overflow-x:auto}
.mainwrap > main > table{display:block;overflow-x:auto}
.topbar{background:rgba(255,255,255,.85);backdrop-filter:saturate(1.4) blur(6px);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:15}
.topbar-inner{max-width:1180px;margin:0 auto;padding:.6rem 1.25rem;display:flex;align-items:center;gap:.8rem}
.topsearch{flex:1;max-width:420px;margin:0}
.topsearch input{padding:.45rem .75rem;border-radius:8px;background:#f3f4f6;border:1px solid transparent}
.topsearch input:focus{background:#fff}
.usermenu{margin-left:auto;display:flex;align-items:center;gap:.9rem;font-size:.92rem;white-space:nowrap}
.usermenu .who{display:flex;align-items:center;gap:.45rem;color:var(--ink);font-weight:600}
.usermenu .av{width:1.9rem;height:1.9rem;border-radius:50%;background:var(--ink);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700}
.usermenu a,.usermenu .link{color:var(--muted)}
.usermenu form{margin:0}
.link{background:none;border:0;color:inherit;font:inherit;text-decoration:underline;cursor:pointer;padding:0}
.menu-btn{display:none;border:1px solid var(--line-strong);background:#fff;border-radius:8px;padding:.4rem .65rem;font:inherit;font-weight:600;cursor:pointer}
.sb-close{display:none}
.sb-shade{display:none}
@@media (max-width:960px){
  .shell{grid-template-columns:minmax(0,1fr)}
  .sb{position:fixed;left:0;top:0;bottom:0;width:270px;z-index:40;transform:translateX(-105%);transition:transform .18s ease;box-shadow:0 10px 40px rgba(0,0,0,.18)}
  body.nav-open .sb{transform:none}
  body.nav-open .sb-shade{display:block}
  .sb-shade{display:none;position:fixed;inset:0;background:rgba(17,24,39,.35);z-index:35}
  .menu-btn{display:inline-block}
  .usermenu .who span.n,.usermenu a.pw{display:none}
  .sb-close{display:block;margin:0 0 .6rem auto}
}
@@media (max-width:560px){.topsearch{max-width:none}}
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
    <div class="sb-foot"><a href="{{ route('home') }}" target="_blank" rel="noopener">Webseite ansehen ↗</a></div>
  </aside>
  <div class="sb-shade" onclick="document.body.classList.remove('nav-open')"></div>
  <div class="mainwrap">
    <header class="topbar">
      <div class="topbar-inner">
        <button class="menu-btn" type="button" onclick="document.body.classList.add('nav-open')" aria-controls="sb">☰ Menü</button>
        <form class="topsearch" method="GET" action="{{ route('search') }}" role="search">
          <input type="text" name="q" value="{{ request()->routeIs('search') ? request('q') : '' }}" maxlength="100" placeholder="Suchen: Mieter, Objekt, Beleg …" aria-label="Suche">
        </form>
        <div class="usermenu">
          <span class="who"><span class="av">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</span><span class="n">{{ auth()->user()->name }}</span></span>
          <a class="pw" href="{{ route('password.change') }}">Passwort</a>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="link" type="submit">Abmelden</button>
          </form>
        </div>
      </div>
    </header>
    <main class="container">
      @if (session('status'))
        <div class="alert ok" role="status">{{ session('status') }}</div>
      @endif
      @yield('content')
    </main>
  </div>
</div>
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
</body>
</html>
