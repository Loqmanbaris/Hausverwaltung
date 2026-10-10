@php($M = fn ($v) => \App\Support\Money::format($v))
<div class="sd-card">
  <h2>Vermietung</h2>
  @if (! $ck['letting'])
    <p class="sd-muted" style="margin:0">Noch keine Einheiten angelegt.</p>
  @else
    @php($lt = $ck['letting'])
    <div class="ck-quote"><strong>{{ $lt['quote'] }} %</strong><span class="sd-muted">vermietet · {{ $lt['let'] }} von {{ $lt['units'] }} Einheiten{{ $lt['area_quote'] !== null ? ' · '.$lt['area_quote'].' % der Fläche' : '' }}</span></div>
    <div class="ck-bar"><i style="width:{{ $lt['quote'] }}%"></i></div>

    <div class="ck-sub">Leer ({{ $lt['vacant']->count() }})</div>
    @if ($lt['vacant']->isEmpty())
      <p class="ck-done" style="margin:.3rem 0">✓ Keine Einheit leer.</p>
    @else
      <ul class="ck-vac">
        @foreach ($lt['vacant']->take(8) as $v)
          <li>
            <span><a href="{{ route('properties.show', $v['unit']->property_id) }}">{{ $v['unit']->label }}</a>, {{ $v['unit']->property?->name }}
              <span class="sd-muted">· {{ $v['since'] ? 'leer seit '.$v['since']->format('d.m.Y') : ($v['unit']->usage_type === 'montage' ? 'heute frei' : 'noch nie vermietet') }}</span></span>
            @if ($v['next'])
              <span class="ck-tag next">vermietet ab {{ $v['next']->format('d.m.') }}</span>
            @elseif ($v['listing'])
              <a class="ck-tag on" href="{{ route('listings.edit', $v['listing']) }}">Inserat online</a>
            @else
              <a class="ck-tag off" href="{{ route('listings.index') }}">kein Inserat</a>
            @endif
          </li>
        @endforeach
      </ul>
      @if ($lt['vacant']->count() > 8)<p class="sd-muted" style="font-size:.9rem;margin:.4rem 0 0">und {{ $lt['vacant']->count() - 8 }} weitere</p>@endif
    @endif

    @if ($lt['ending']->isNotEmpty() || count($lt['index_due']) > 0)
      <div class="ck-sub">Verträge</div>
      <ul class="ck-vac">
        @foreach ($lt['ending'] as $l)
          <li><span><a href="{{ route('leases.show', $l) }}">{{ $l->unit?->label }} – {{ $l->tenant?->display_name }}</a></span><span class="ck-tag off">endet {{ $l->end_date->format('d.m.Y') }}</span></li>
        @endforeach
        @foreach ($lt['index_due'] as $i)
          <li><span><a href="{{ route('leases.show', $i['lease']) }}">{{ $i['lease']->unit?->label }} – {{ $i['lease']->tenant?->display_name }}</a></span><span class="ck-tag next">Index-Erhöhung möglich</span></li>
        @endforeach
      </ul>
    @endif
  @endif
</div>
