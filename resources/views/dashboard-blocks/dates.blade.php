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
