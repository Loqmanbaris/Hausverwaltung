@php($M = fn ($v) => \App\Support\Money::format($v))
@php($mo = $ck['money'])
@php($tileColors = $b['color'] === 'bunt' ? array_combine(['soll', 'ist', 'offen', 'rueck'], array_merge(array_slice(\App\Support\Dashboard\Layout::MONEY_TILES, 0, 3), [$mo['overdue'] > 0.004 ? 'rot' : 'gruen'])) : array_fill_keys(['soll', 'ist', 'offen', 'rueck'], $b['color']))
@php($ts = fn ($k) => \App\Support\Dashboard\Layout::style($tileColors[$k]))
<div class="ck-money">
  <a class="ck-tile" @if ($ts('soll') !== '') style="{{ $ts('soll') }}" @endif href="{{ route('rent.month') }}">
    <span class="k">Miete {{ $monthNames[(int) $mo['month']->format('n')] }}</span>
    <span class="v">{{ $M($mo['soll']) }}</span>
    <span class="s">Soll laut Verträgen</span>
  </a>
  <a class="ck-tile" @if ($ts('ist') !== '') style="{{ $ts('ist') }}" @endif href="{{ route('rent.month') }}">
    <span class="k">Davon bezahlt</span>
    <span class="v">{{ $M($mo['ist']) }}</span>
    <span class="s">{{ $mo['quote'] !== null ? $mo['quote'].' %' : '–' }} · Kontoeingang {{ $M($mo['received']) }}</span>
    @if ($mo['quote'] !== null)<div class="ck-bar"><i style="width:{{ min(100, $mo['quote']) }}%"></i></div>@endif
  </a>
  <a class="ck-tile" @if ($ts('offen') !== '') style="{{ $ts('offen') }}" @endif href="{{ route('rent.open') }}">
    <span class="k">Noch offen im Monat</span>
    <span class="v">{{ $M($mo['open']) }}</span>
    <span class="s">noch nicht eingegangen</span>
  </a>
  <a class="ck-tile" @if ($ts('rueck') !== '') style="{{ $ts('rueck') }}" @endif href="{{ route('rent.open') }}">
    <span class="k">Rückstand gesamt</span>
    <span class="v {{ $mo['overdue'] > 0.004 ? 'ck-red' : 'ck-green' }}">{{ $M($mo['overdue']) }}</span>
    <span class="s">{{ $mo['overdue_leases'] > 0 ? 'überfällig bei '.$mo['overdue_leases'].' '.($mo['overdue_leases'] === 1 ? 'Vertrag' : 'Verträgen') : 'nichts überfällig' }}</span>
  </a>
</div>
