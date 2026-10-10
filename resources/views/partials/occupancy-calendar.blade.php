{{-- Belegungskalender. Erwartet $calendar (Occupancy::calendar) und optional $public (keine Unterscheidung reserviert/gesperrt). --}}
<style>
.oc-wrap{display:grid;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));gap:1rem}
.oc-month h3{font-size:1rem;margin:0 0 .3rem}
.oc-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;font-size:.85rem;text-align:center}
.oc-grid .h{color:#667085;font-size:.75rem;padding:.15rem 0}
.oc-grid .d{padding:.3rem 0;border-radius:.25rem;background:#ecfdf3;color:#067647}
.oc-grid .d.belegt{background:#fdecec;color:#b42318;text-decoration:line-through}
.oc-grid .d.reserviert{background:#fff4e5;color:#8a5a12}
.oc-grid .d.gesperrt{background:#e4e7ec;color:#475467;text-decoration:line-through}
.oc-grid .d.past{opacity:.4}
.oc-legend{display:flex;flex-wrap:wrap;gap:.8rem;font-size:.85rem;margin:.6rem 0 0}
.oc-legend span::before{content:"";display:inline-block;width:.8rem;height:.8rem;border-radius:.2rem;margin-right:.3rem;vertical-align:-.1rem}
.oc-legend .f::before{background:#ecfdf3;border:1px solid #abefc6}.oc-legend .b::before{background:#fdecec}.oc-legend .r::before{background:#fff4e5}.oc-legend .g::before{background:#e4e7ec}
</style>
<div class="oc-wrap">
  @foreach ($calendar as $m)
    <div class="oc-month">
      <h3>{{ $m['label'] }}</h3>
      <div class="oc-grid">
        @foreach (['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'] as $wd)<div class="h">{{ $wd }}</div>@endforeach
        @for ($i = 0; $i < $m['offset']; $i++)<div></div>@endfor
        @foreach ($m['days'] as $d)
          @php($st = ! empty($public) && $d['state'] !== 'frei' ? 'belegt' : $d['state'])
          <div class="d {{ $st }} {{ $d['past'] ? 'past' : '' }}" title="{{ \Carbon\Carbon::parse($d['date'])->format('d.m.Y') }}: {{ $st === 'frei' ? 'frei' : ($st === 'reserviert' ? 'reserviert' : 'belegt') }}">{{ $d['day'] }}</div>
        @endforeach
      </div>
    </div>
  @endforeach
</div>
<div class="oc-legend">
  <span class="f">frei</span><span class="b">belegt</span>
  @if (empty($public))<span class="r">reserviert</span><span class="g">gesperrt</span>@endif
</div>
