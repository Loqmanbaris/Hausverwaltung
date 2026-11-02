{{-- Kontoauszüge: eine Seite mit drei Reitern (Eingänge, Ausgaben, Einlesen) --}}
@php
  $kzUser = auth()->user();
  $kzCount = function (callable $f) { try { return (int) $f(); } catch (\Throwable) { return 0; } };
  $kzTabs = [
      ['Eingänge zuordnen', 'bank.transactions', ['bank.transactions'], $kzCount(fn () => \App\Support\Bank\BankService::openCountFor($kzUser))],
      ['Ausgaben zuordnen', 'bank.expenses', ['bank.expenses*'], $kzCount(fn () => \App\Support\Bank\ExpenseMatcher::openCountFor($kzUser))],
      ['Kontoauszug einlesen', 'bank.index', ['bank.index'], 0],
  ];
@endphp
<style>
.kz-tabs{display:flex;gap:.2rem;margin:0 0 1.1rem;border-bottom:1px solid var(--line,#e3e6ea);overflow-x:auto}
.kz-tabs a{display:flex;align-items:center;gap:.45rem;padding:.6rem .9rem;margin-bottom:-1px;border-bottom:3px solid transparent;color:var(--muted,#6b7280);text-decoration:none;font-weight:500;white-space:nowrap}
.kz-tabs a:hover{color:var(--ink,#111827)}
.kz-tabs a.on{color:var(--ink,#111827);border-bottom-color:var(--brand,#f97316);font-weight:600}
.kz-tabs .kz-n{background:var(--brand,#f97316);color:#fff;border-radius:1rem;font-size:.72rem;font-weight:700;padding:.05rem .45rem;line-height:1.4}
</style>
<div class="sd-head">
  <h1>Kontoauszüge</h1>
  @isset($kzActions)<div class="sd-actions">{!! $kzActions !!}</div>@endisset
</div>
<nav class="kz-tabs" aria-label="Kontoauszüge">
  @foreach ($kzTabs as [$kzLabel, $kzRoute, $kzActive, $kzN])
    <a href="{{ route($kzRoute) }}" @class(['on' => request()->routeIs(...$kzActive)])>{{ $kzLabel }}@if ($kzN > 0)<span class="kz-n">{{ $kzN }}</span>@endif</a>
  @endforeach
</nav>
