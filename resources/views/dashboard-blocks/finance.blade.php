@php($M = fn ($v) => \App\Support\Money::format($v))
@php($fi = $ck['finance'])
<div class="sd-card">
  <h2>Finanzierung</h2>
  @if (! $fi || $fi['count'] === 0)
    <p class="sd-muted" style="margin:0">Noch kein Darlehen angelegt. <a href="{{ route('loans.create') }}">Darlehen anlegen</a></p>
  @else
    <div class="ck-quote"><strong>{{ $M($fi['debt']) }}</strong><span class="sd-muted">Restschuld · {{ $fi['count'] }} {{ $fi['count'] === 1 ? 'Darlehen' : 'Darlehen' }}</span></div>
    <div class="ck-bar" title="{{ $fi['paid_pct'] }} % getilgt"><i style="width:{{ max(0, min(100, $fi['paid_pct'])) }}%"></i></div>
    <div class="sd-muted" style="font-size:.85rem;margin:.3rem 0 .6rem">{{ $fi['paid_pct'] }} % von {{ $M($fi['principal']) }} getilgt</div>
    <ul class="ck-cats">
      <li><div class="t"><span>Raten pro Monat</span><strong>{{ $M($fi['monthly']) }}</strong></div></li>
      <li><div class="t"><span>Zinsen {{ $fi['year'] }} (Plan)</span><strong>{{ $M($fi['interest_year']) }}</strong></div></li>
      <li><div class="t"><span>Tilgung {{ $fi['year'] }} (Plan)</span><strong>{{ $M($fi['repay_year']) }}</strong></div></li>
      @if ($fi['fixed'])
        <li><div class="t"><span>Nächste Zinsbindung: <a href="{{ route('loans.show', $fi['fixed']) }}">{{ $fi['fixed']->name }}</a></span><strong>{{ $fi['fixed']->fixed_until->format('m/Y') }}</strong></div>
          <div class="sd-muted" style="font-size:.82rem">Restschuld dann etwa {{ $M($fi['fixed_balance']) }}</div></li>
      @endif
    </ul>
    <p style="margin:.6rem 0 0;font-size:.9rem"><a href="{{ route('finance.index') }}">Cashflow und Rendite je Objekt</a></p>
  @endif
</div>
