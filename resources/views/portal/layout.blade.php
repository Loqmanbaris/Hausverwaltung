<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>@yield('title', 'Mieterportal') – {{ $siteName }}</title>
@include('partials.styles')
@include('partials.sd-styles')
<style>
.pt{max-width:760px;margin:0 auto;padding:1rem 1rem 3rem}
.pt h1{font-size:1.4rem;margin:.4rem 0 .6rem}
.pt-top{display:flex;justify-content:space-between;align-items:center;gap:.6rem;flex-wrap:wrap;padding:.6rem 0;border-bottom:1px solid var(--line);margin-bottom:.8rem}
.pt-nav{display:flex;flex-wrap:wrap;gap:.4rem;margin:.2rem 0 1rem}
.pt-nav a{padding:.4rem .8rem;border-radius:.5rem;background:var(--surface-2);color:var(--ink);text-decoration:none;font-size:.92rem}
.pt-nav a.on{background:#1f4e79;color:#fff}
.pt .ok{background:var(--ok-bg);border:1px solid var(--ok-line);color:var(--ok);padding:.6rem .8rem;border-radius:8px;margin:.4rem 0}
.pt input,.pt select,.pt textarea{padding:.5rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;width:100%}
.pt .row{display:grid;gap:.8rem;margin:.5rem 0}
.pt table{width:100%;border-collapse:collapse}
.pt td,.pt th{padding:.4rem .3rem;border-bottom:1px solid var(--line);text-align:left;font-size:.92rem}
.pt .n{text-align:right;white-space:nowrap}
</style>
</head>
<body>
<main class="pt">
  <div class="pt-top">
    <strong>{{ $siteName }} · Mieterportal</strong>
    @isset($pu)
      <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="sd-btn sec small" type="submit">Abmelden</button></form>
    @endisset
  </div>
  @isset($pu)
    @php($cur = request()->route()?->getName())
    <nav class="pt-nav" aria-label="Bereiche">
      <a href="{{ route('portal.home') }}" class="{{ $cur === 'portal.home' ? 'on' : '' }}">Start</a>
      <a href="{{ route('portal.account') }}" class="{{ $cur === 'portal.account' ? 'on' : '' }}">Konto</a>
      <a href="{{ route('portal.documents') }}" class="{{ $cur === 'portal.documents' ? 'on' : '' }}">Dokumente</a>
      <a href="{{ route('portal.meters') }}" class="{{ $cur === 'portal.meters' ? 'on' : '' }}">Zählerstände</a>
      <a href="{{ route('portal.damage') }}" class="{{ $cur === 'portal.damage' ? 'on' : '' }}">Schaden melden</a>
    </nav>
  @endisset
  @if (session('portal_ok'))<div class="ok" role="status">{{ session('portal_ok') }}</div>@endif
  @if ($errors->any())<div class="sd-err">{{ implode(' ', $errors->all()) }}</div>@endif
  @yield('content')
</main>
</body>
</html>
