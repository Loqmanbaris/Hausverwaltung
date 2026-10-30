@php
  $colors = ['ok' => ['#ecfdf5', '#a7f3d0', '#065f46', '✓'], 'warn' => ['#fffbeb', '#fde68a', '#92400e', '!'], 'fehler' => ['#fef2f2', '#fecaca', '#b91c1c', '✕']];
@endphp
<ul style="list-style:none;margin:.4rem 0 0;padding:0;display:grid;gap:.4rem">
  @foreach ($checks as [$level, $text])
    @php([$bg, $bd, $fg, $sym] = $colors[$level] ?? $colors['warn'])
    <li style="background:{{ $bg }};border:1px solid {{ $bd }};color:{{ $fg }};padding:.5rem .7rem;border-radius:8px;display:flex;gap:.5rem"><strong aria-hidden="true">{{ $sym }}</strong><span>{{ $text }}</span></li>
  @endforeach
</ul>
