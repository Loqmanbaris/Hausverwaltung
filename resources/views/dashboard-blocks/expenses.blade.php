@php($M = fn ($v) => \App\Support\Money::format($v))
@php($ex = $ck['expenses'])
<div class="sd-card">
  <h2>Ausgaben {{ $ex['year'] ?? date('Y') }}</h2>
  @if (! $ex || $ex['count'] === 0)
    <p class="sd-muted" style="margin:0">Dieses Jahr sind noch keine Belege erfasst. <a href="{{ route('receipts.create') }}">Beleg erfassen</a></p>
  @else
    <div class="ck-quote"><strong>{{ $M($ex['total']) }}</strong><span class="sd-muted">aus {{ $ex['count'] }} {{ $ex['count'] === 1 ? 'Beleg' : 'Belegen' }} (brutto)</span></div>
    @php($mx = max(array_column($ex['months'], 'sum')) ?: 1)
    <div class="ck-mini" role="img" aria-label="Ausgaben je Monat">
      @foreach ($ex['months'] as $m)
        <div class="ck-mcol" title="{{ $m['label'] }}: {{ $M($m['sum']) }}">
          <div class="ck-mbar"><i style="height:{{ $m['sum'] > 0 ? max(3, round($m['sum'] / $mx * 100)) : 0 }}%;{{ $m['future'] ? 'opacity:.25' : '' }}"></i></div>
          <span @if ($m['current']) style="color:var(--ink);font-weight:700" @endif>{{ mb_substr($m['label'], 0, 1) }}</span>
        </div>
      @endforeach
    </div>
    @php($cmax = max(array_column($ex['categories'], 'sum')) ?: 1)
    <ul class="ck-cats">
      @foreach ($ex['categories'] as $c)
        <li>
          <div class="t"><span>{{ $c['label'] }}</span><strong>{{ $M($c['sum']) }}</strong></div>
          <div class="ck-bar" style="margin-top:.25rem"><i style="width:{{ max(1, round($c['sum'] / $cmax * 100)) }}%"></i></div>
        </li>
      @endforeach
    </ul>
    <p style="margin:.6rem 0 0;font-size:.9rem"><a href="{{ route('receipts.index') }}">Alle Belege</a></p>
  @endif
</div>
