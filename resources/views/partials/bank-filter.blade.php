{{-- Filter für Kontoauszüge: Monat (mit Pfeilen) und Konto. Erwartet $filterRoute, $state, $accountId, $accounts, $month, $months --}}
@php
  [$bfPrev, $bfNext] = \App\Support\Bank\Monatsfilter::neighbours($months, $month);
  $bfUrl = fn (?string $m) => route($filterRoute, array_filter(['stand' => $state, 'konto' => $accountId ?: null]) + ['monat' => $m ?? '']);
@endphp
<style>
.bf{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:0 0 1rem}
.bf select{width:auto;min-width:0;padding:.45rem .6rem;border:1px solid var(--line-strong);border-radius:8px;font:inherit;background:var(--surface)}
.bf-month{display:flex;align-items:center;gap:.25rem}
.bf-arrow{display:inline-flex;align-items:center;justify-content:center;width:2.2rem;height:2.2rem;border:1px solid var(--line-strong);border-radius:8px;background:var(--surface);color:var(--ink,#111827);text-decoration:none;font-size:1.1rem}
.bf-arrow.off{opacity:.35;pointer-events:none}
</style>
<form class="bf" method="GET" action="{{ route($filterRoute) }}">
  <input type="hidden" name="stand" value="{{ $state }}">
  <div class="bf-month">
    <a class="bf-arrow @if (! $bfPrev) off @endif" href="{{ $bfPrev ? $bfUrl($bfPrev) : '#' }}" aria-label="Monat davor">‹</a>
    <select name="monat" aria-label="Monat" onchange="this.form.submit()">
      <option value="">Alle Monate</option>
      @foreach ($months as $bfKey => $bfLabel)
        <option value="{{ $bfKey }}" @selected($month === $bfKey)>{{ $bfLabel }}</option>
      @endforeach
    </select>
    <a class="bf-arrow @if (! $bfNext) off @endif" href="{{ $bfNext ? $bfUrl($bfNext) : '#' }}" aria-label="Monat danach">›</a>
  </div>
  @if ($accounts->count() > 1)
    <select name="konto" aria-label="Konto" onchange="this.form.submit()">
      <option value="">Alle Konten</option>
      @foreach ($accounts as $a)
        <option value="{{ $a->id }}" @selected($accountId === (int) $a->id)>{{ $a->owner?->name }} – {{ substr($a->formattedIban(), -9) }}</option>
      @endforeach
    </select>
  @else
    <input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
  @endif
</form>
