@extends('layouts.app')
@section('title', 'Einstellungen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.ps{display:flex;flex-direction:column;gap:22px;max-width:1100px}
.ps-g h2{font-size:.78rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--muted,#6b7280);margin:0 0 8px}
.ps-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px}
.ps-grid form{margin:0;display:flex}
.ps-i{display:flex;align-items:center;gap:12px;background:var(--surface);border:1px solid var(--line);border-radius:14px;padding:12px 14px;text-align:left;color:var(--ink,#111827);text-decoration:none;width:100%;font:inherit;cursor:pointer;transition:border-color .15s,box-shadow .15s}
.ps-i:hover{border-color:var(--brand);box-shadow:0 4px 14px rgba(0,0,0,.06);color:var(--ink,#111827)}
.ps-ic{font-size:22px;width:44px;height:44px;flex:none;border-radius:12px;display:flex;align-items:center;justify-content:center;background:color-mix(in srgb,var(--brand) 10%,#fff)}
.ps-t{display:flex;flex-direction:column;min-width:0;flex:1;gap:2px}
.ps-t b{font-size:15px}
.ps-t span{font-size:12.5px;color:var(--muted,#6b7280);line-height:1.35}
.ps-go{color:var(--muted);font-size:18px}
.ps-badge{background:var(--brand);color:#fff;border-radius:1rem;font-size:.72rem;font-weight:700;padding:.05rem .45rem}
@media (max-width:640px){.ps-grid{grid-template-columns:1fr 1fr}.ps-i{flex-direction:column;align-items:flex-start;gap:8px}.ps-go{display:none}}
</style>
<div class="sd-head"><h1>Einstellungen</h1></div>
<div class="ps">
  @foreach ($groups as [$title, $items])
    <section class="ps-g">
      <h2>{{ $title }}</h2>
      <div class="ps-grid">
        @foreach ($items as $item)
          @php([$label, $desc, $icon, $target] = $item)
          @php($badge = (int) ($item[4] ?? 0))
          @php($inner = '<span class="ps-ic" aria-hidden="true">'.e($icon).'</span><span class="ps-t"><b>'.e($label).($badge > 0 ? ' <span class="ps-badge">'.$badge.'</span>' : '').'</b><span>'.e($desc).'</span></span>')
          @if (isset($target['url']))
            <a class="ps-i" href="{{ $target['url'] }}" @if ($target['blank']) target="_blank" rel="noopener" @endif>{!! $inner !!}<span class="ps-go" aria-hidden="true">{{ $target['blank'] ? '↗' : '›' }}</span></a>
          @elseif (isset($target['post']))
            <form method="POST" action="{{ route($target['post']) }}">
              @csrf
              @foreach ($target['fields'] as $k => $v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endforeach
              <button type="submit" class="ps-i">{!! $inner !!}</button>
            </form>
          @elseif (! empty($target['push']))
            <a class="ps-i" href="#" data-push>{!! $inner !!}</a>
          @else
            <a class="ps-i" href="#" data-install>{!! $inner !!}</a>
          @endif
        @endforeach
      </div>
    </section>
  @endforeach
</div>
@endsection
