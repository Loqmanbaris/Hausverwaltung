@extends('layouts.public')
@section('title', $listing->title.' – '.config('app.name'))
@section('content')
@include('public.partials-listing-styles')
@php
  use App\Support\Listings\Listings;
  use App\Support\Listings\PortalText;
  $photos = $listing->photos;
  $facts = PortalText::facts($listing);
  unset($facts['Art'], $facts['Energieausweis']);
  $energy = Listings::energyLine($listing);
@endphp
<style>
.ld-top{display:grid;grid-template-columns:minmax(0,3fr) minmax(0,2fr);gap:1.4rem;align-items:start}
@media (max-width:860px){.ld-top{grid-template-columns:1fr}}
.ld-main{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;border-radius:8px;background:#dfe5ec}
.ld-thumbs{display:flex;gap:.4rem;overflow-x:auto;margin-top:.5rem;padding-bottom:.2rem}
.ld-thumbs button{flex:none;border:2px solid transparent;border-radius:6px;padding:0;background:none;cursor:pointer}
.ld-thumbs button.on{border-color:var(--primary)}
.ld-thumbs img{display:block;width:84px;height:63px;object-fit:cover;border-radius:4px}
.ld-cap{color:var(--muted);font-size:.9rem;margin:.3rem 0 0;min-height:1.2em}
.ld-facts{background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:1rem 1.1rem}
.ld-facts dl{display:grid;grid-template-columns:max-content 1fr;gap:.35rem 1rem;margin:0}
.ld-facts dt{color:var(--muted)}
.ld-facts dd{margin:0;font-weight:600}
.ld-text{max-width:48rem}
.ld-text h2{font-size:1.15rem;margin:1.5rem 0 .4rem}
.ld-text p{white-space:pre-line}
.ld-feat{columns:2 14rem;padding-left:1.1rem}
.ld-box{background:var(--surface);border:1px solid var(--line);border-radius:8px;padding:1rem 1.1rem;margin-top:1.5rem;max-width:48rem}
</style>
<p><a href="{{ route('public.'.$listing->kind) }}">← {{ Listings::PAGES[$listing->kind][1] }}</a></p>
<h1>{{ $listing->title }}</h1>
<p class="muted" style="margin-top:-.4rem">{{ $listing->kindLabel() }} in {{ $listing->publicPlace() }}</p>

<div class="ld-top">
  <div>
    @if ($photos->isNotEmpty())
      <img class="ld-main" id="ld-main" src="{{ $photos->first()->url('l') }}" alt="{{ $photos->first()->caption ?: $listing->title }}" width="{{ $photos->first()->width }}" height="{{ $photos->first()->height }}">
      <p class="ld-cap" id="ld-cap">{{ $photos->first()->caption }}</p>
      @if ($photos->count() > 1)
        <div class="ld-thumbs">
          @foreach ($photos as $i => $p)
            <button type="button" @class(['on' => $i === 0]) data-src="{{ $p->url('l') }}" data-cap="{{ $p->caption }}" aria-label="Foto {{ $i + 1 }}"><img src="{{ $p->url('s') }}" alt="" loading="lazy"></button>
          @endforeach
        </div>
      @endif
    @else
      <div class="pl-noimg ld-main">Fotos folgen</div>
    @endif
  </div>
  <div class="ld-facts">
    <dl>
      @foreach ($facts as $k => $v)
        <dt>{{ $k }}</dt><dd>{{ $v }}</dd>
      @endforeach
    </dl>
    <p style="margin:1rem 0 0"><a class="btn" href="#anfrage">Jetzt anfragen</a></p>
  </div>
</div>

<div class="ld-text">
  @if ($listing->description)<h2>Beschreibung</h2><p>{{ $listing->description }}</p>@endif
  @if ($listing->featureLabels() || $listing->equipment_text)
    <h2>Ausstattung</h2>
    @if ($listing->featureLabels())
      <ul class="ld-feat">@foreach ($listing->featureLabels() as $f)<li>{{ $f }}</li>@endforeach</ul>
    @endif
    @if ($listing->equipment_text)<p>{{ $listing->equipment_text }}</p>@endif
  @endif
  @if ($listing->location_text)<h2>Lage</h2><p>{{ $listing->location_text }}</p>@endif
  @if ($listing->other_text)<h2>Sonstiges</h2><p>{{ $listing->other_text }}</p>@endif
  @if ($energy !== '')<h2>Energie</h2><p>{{ $energy }}</p>@endif
</div>

@if ($listing->kind === 'montage')
  <div class="ld-box" style="max-width:none">
    <h2 style="margin-top:0;font-size:1.15rem">Belegung</h2>
    @include('partials.occupancy-calendar', ['calendar' => $calendar, 'public' => true])
  </div>
@endif

<div class="ld-box" id="anfrage">
  <h2 style="margin-top:0;font-size:1.15rem">Anfrage</h2>
  @include('public.contact-form', ['listing' => $listing])
</div>

@if ($photos->count() > 1)
<script>
document.querySelectorAll('.ld-thumbs button').forEach(function (b) {
  b.addEventListener('click', function () {
    document.getElementById('ld-main').src = b.dataset.src;
    document.getElementById('ld-cap').textContent = b.dataset.cap || '';
    document.querySelectorAll('.ld-thumbs button').forEach(function (x) { x.classList.toggle('on', x === b); });
  });
});
</script>
@endif
@endsection
