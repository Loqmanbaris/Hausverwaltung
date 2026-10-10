@extends('layouts.public')
@section('title', 'Unsere Objekte – '.\App\Support\Site\SiteSettings::name())
@section('content')
<style>
.ob-item{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(0,1fr);gap:2rem;border-top:2px solid var(--ink);padding:1.6rem 0 2.2rem;align-items:start}
@media (max-width:860px){.ob-item{grid-template-columns:1fr;gap:1rem}}
.ob-main{width:100%;aspect-ratio:4/3;object-fit:cover;display:block;background:var(--soft)}
.ob-thumbs{display:flex;gap:.4rem;margin-top:.4rem;overflow-x:auto}
.ob-thumbs img{width:92px;height:69px;object-fit:cover;cursor:pointer;opacity:.8;border:2px solid transparent}
.ob-thumbs img.on{opacity:1;border-color:var(--accent)}
.ob-item h2{font-size:1.5rem;margin:0 0 .3rem}
.ob-place{color:var(--muted);margin:0 0 1rem}
.ob-item p.t{white-space:pre-line}
</style>
<p class="pub-kicker">Unsere Objekte</p>
<h1 class="pub-h1">Häuser, die wir vermieten</h1>
@if ($intro)<p class="pub-lead">{{ $intro }}</p>@endif
@forelse ($objects as $o)
  <section class="ob-item" id="o{{ $o->id }}">
    <div>
      @if ($o->photos->isNotEmpty())
        <img class="ob-main" src="{{ $o->photos->first()->url('l') }}" alt="{{ $o->title ?: $o->property->name }}" loading="lazy">
        @if ($o->photos->count() > 1)
          <div class="ob-thumbs">@foreach ($o->photos as $i => $ph)<img src="{{ $ph->url('s') }}" data-l="{{ $ph->url('l') }}" alt="Foto {{ $i + 1 }}" @class(['on' => $i === 0]) loading="lazy">@endforeach</div>
        @endif
      @else
        <div class="ob-main"></div>
      @endif
    </div>
    <div>
      <h2>{{ $o->title ?: $o->property->name }}</h2>
      <p class="ob-place">{{ trim(($o->property->zip ?? '').' '.($o->property->city ?? '')) }}</p>
      @if ($o->text)<p class="t">{{ $o->text }}</p>@endif
    </div>
  </section>
@empty
  <p>Hier stellen wir bald unsere Häuser vor.</p>
@endforelse
<script>
document.querySelectorAll('.ob-thumbs img').forEach(function (t) {
  t.addEventListener('click', function () {
    var box = t.closest('section'); box.querySelector('.ob-main').src = t.dataset.l;
    box.querySelectorAll('.ob-thumbs img').forEach(function (x) { x.classList.toggle('on', x === t); });
  });
});
</script>
@endsection
