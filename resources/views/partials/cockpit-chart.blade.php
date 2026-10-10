@php
  $max = max(array_column($chart, 'soll')) ?: 1;
  $step = $max > 20000 ? 10000 : ($max > 8000 ? 5000 : ($max > 4000 ? 2000 : ($max > 2000 ? 1000 : 500)));
  $top = (int) (ceil($max / $step) * $step);
  $W = 520; $H = 220; $L = 50; $B = 26; $T = 12;
  $plotH = $H - $B - $T; $plotW = $W - $L - 6;
  $slot = $plotW / count($chart); $bw = min(30, $slot * .58);
  $y = fn ($v) => $T + $plotH - ($v / $top) * $plotH;
  $fmt = fn ($v) => $v >= 1000 ? number_format($v / 1000, $v % 1000 ? 1 : 0, ',', '.').' T€' : number_format($v, 0, ',', '.').' €';
@endphp
<svg class="ck-chart" viewBox="0 0 {{ $W }} {{ $H }}" role="img" aria-label="Mieteingang der letzten 12 Monate: Soll und bezahlt">
  @for ($g = 0; $g <= $top; $g += $step)
    <line x1="{{ $L }}" x2="{{ $W - 4 }}" y1="{{ $y($g) }}" y2="{{ $y($g) }}" stroke="#eef0f3" />
    <text x="{{ $L - 8 }}" y="{{ $y($g) + 4 }}" text-anchor="end" font-size="12.5" fill="#9ca3af">{{ $fmt($g) }}</text>
  @endfor
  @foreach ($chart as $i => $m)
    @php($cx = $L + $slot * $i + $slot / 2)
    @php($last = $i === count($chart) - 1)
    <g>
      <title>{{ $m['label'] }} {{ $m['year'] }}: Soll {{ \App\Support\Money::format($m['soll']) }}, bezahlt {{ \App\Support\Money::format($m['ist']) }}</title>
      <rect x="{{ $cx - $bw / 2 }}" y="{{ $y($m['soll']) }}" width="{{ $bw }}" height="{{ max(0, $T + $plotH - $y($m['soll'])) }}" rx="3" fill="#e5e7eb" />
      <rect x="{{ $cx - $bw / 2 }}" y="{{ $y($m['ist']) }}" width="{{ $bw }}" height="{{ max(0, $T + $plotH - $y($m['ist'])) }}" rx="3" fill="var(--brand)" @if ($m['soll'] > 0 && $m['ist'] + 0.01 < $m['soll'] && ! $last) opacity=".75" @endif />
      @if ($m['soll'] > 0 && $m['ist'] + 0.01 < $m['soll'] && ! $last)
        <circle cx="{{ $cx }}" cy="{{ $y($m['soll']) - 7 }}" r="3" fill="#b42318" />
      @endif
      <text x="{{ $cx }}" y="{{ $H - 8 }}" text-anchor="middle" font-size="12.5" fill="{{ $last ? '#111827' : '#6b7280' }}" @if ($last) font-weight="700" @endif>{{ $m['label'] }}</text>
    </g>
  @endforeach
</svg>
<div class="ck-legend">
  <span><i style="background:var(--brand)"></i>bezahlt</span>
  <span><i style="background:#e5e7eb"></i>Soll</span>
  <span><i style="background:#b42318;border-radius:50%"></i>Monat nicht voll bezahlt</span>
</div>
