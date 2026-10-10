@php($M = fn ($v) => \App\Support\Money::format($v))
@if ($ck && $ck['money'])
  @php($mo = $ck['money'])
  <div class="ck-money">
    <a class="ck-tile" href="{{ route('rent.month') }}">
      <span class="k">Miete {{ $monthNames[(int) $mo['month']->format('n')] }}</span>
      <span class="v">{{ $M($mo['soll']) }}</span>
      <span class="s">Soll laut Verträgen</span>
    </a>
    <a class="ck-tile" href="{{ route('rent.month') }}">
      <span class="k">Davon bezahlt</span>
      <span class="v">{{ $M($mo['ist']) }}</span>
      <span class="s">{{ $mo['quote'] !== null ? $mo['quote'].' %' : '–' }} · Kontoeingang {{ $M($mo['received']) }}</span>
      @if ($mo['quote'] !== null)<div class="ck-bar"><i style="width:{{ min(100, $mo['quote']) }}%"></i></div>@endif
    </a>
    <a class="ck-tile" href="{{ route('rent.open') }}">
      <span class="k">Noch offen im Monat</span>
      <span class="v">{{ $M($mo['open']) }}</span>
      <span class="s">noch nicht eingegangen</span>
    </a>
    <a class="ck-tile" href="{{ route('rent.open') }}">
      <span class="k">Rückstand gesamt</span>
      <span class="v {{ $mo['overdue'] > 0.004 ? 'ck-red' : 'ck-green' }}">{{ $M($mo['overdue']) }}</span>
      <span class="s">{{ $mo['overdue_leases'] > 0 ? 'überfällig bei '.$mo['overdue_leases'].' '.($mo['overdue_leases'] === 1 ? 'Vertrag' : 'Verträgen') : 'nichts überfällig' }}</span>
    </a>
  </div>
@endif

@if ($ck)
<div class="ck-grid">
  <div class="sd-card">
    <h2>Zu erledigen</h2>
    @if ($ck['todo'] === [])
      <p class="ck-done">✓ Alles erledigt.</p>
    @else
      <ul class="ck-todo">
        @foreach ($ck['todo'] as $t)
          <li><a href="{{ $t['url'] }}"><span class="ck-num {{ $t['tone'] }}">{{ $t['count'] }}</span><span>{{ $t['label'] }}</span><span class="go">›</span></a></li>
        @endforeach
      </ul>
    @endif
  </div>

  <div class="sd-card">
    <h2>Termine und Fristen</h2>
    @if ($ck['dates'] === [])
      <p class="sd-muted" style="margin:0">In den nächsten {{ $ck['days'] }} Tagen steht nichts an.</p>
    @else
      <ul class="ck-dates">
        @foreach (array_slice($ck['dates'], 0, 10) as $d)
          <li>
            <div class="ck-day {{ $d['tone'] === 'warn' ? 'warn' : '' }}"><b>{{ $d['date']->format('d') }}</b><span>{{ ['', 'Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'][(int) $d['date']->format('n')] }}</span></div>
            <div>
              <a href="{{ $d['url'] }}">{{ $d['label'] }}</a>
              <div class="d">{{ $d['date']->format('Y-m-d') === date('Y-m-d') ? 'heute · ' : '' }}{{ $d['detail'] }}</div>
            </div>
          </li>
        @endforeach
      </ul>
      @if (count($ck['dates']) > 10)
        <p class="sd-muted" style="margin:.5rem 0 0;font-size:.9rem">und {{ count($ck['dates']) - 10 }} weitere in den nächsten {{ $ck['days'] }} Tagen</p>
      @endif
    @endif
  </div>

  <div class="sd-card">
    <h2>Mieteingang der letzten 12 Monate</h2>
    @if ($ck['chart'] === [])
      <p class="sd-muted" style="margin:0">Noch keine Mieten erfasst.</p>
    @else
      @include('partials.cockpit-chart', ['chart' => $ck['chart']])
    @endif
  </div>

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
</div>
@endif
