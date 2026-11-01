{{-- Reiter für den E-Mail-Bereich: Posteingang, Gesendet, Neue E-Mail, Einrichten --}}
@php($unreadTab = $unreadTab ?? \App\Models\ReceivedEmail::unreadCountFor(auth()->user()))
<style>
.ml-tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem}
.ml-tabs a{display:inline-flex;align-items:center;gap:.35rem;padding:.45rem .8rem;border:1px solid var(--line-strong,#d1d5db);border-radius:2rem;text-decoration:none;color:var(--ink,#111827);font-weight:600;font-size:.92rem;background:#fff}
.ml-tabs a.on{background:var(--ink,#111827);color:#fff;border-color:var(--ink,#111827)}
.ml-tabs .n{background:#b42318;color:#fff;border-radius:1rem;font-size:.72rem;padding:.02rem .42rem}
</style>
<nav class="ml-tabs" aria-label="E-Mail">
  <a href="{{ route('inbox.index') }}" class="{{ request()->routeIs('inbox.*') ? 'on' : '' }}">Posteingang @if ($unreadTab > 0)<span class="n">{{ $unreadTab }}</span>@endif</a>
  <a href="{{ route('mail.index') }}" class="{{ request()->routeIs('mail.index') ? 'on' : '' }}">Gesendet</a>
  <a href="{{ route('mail.write') }}" class="{{ request()->routeIs('mail.write') ? 'on' : '' }}">Neue E-Mail</a>
  @if (auth()->user()->isAdmin())<a href="{{ route('mail.settings') }}" class="{{ request()->routeIs('mail.settings') ? 'on' : '' }}">Einrichten</a>@endif
</nav>
