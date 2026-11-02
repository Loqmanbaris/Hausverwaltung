@extends('layouts.public')
@section('title', \App\Support\Site\SiteSettings::name())
@section('content')
@php
  use App\Support\Site\SiteSettings;
  use App\Support\Site\SiteFiles;
  $lines = SiteSettings::headlineLines();
  $hero = SiteFiles::first('hero');
  $offerText = [
      'wohnen' => ['Wohnungen', 'Freie Mietwohnungen', 'public.wohnen'],
      'montage' => ['Monteurwohnungen', 'Möbliert, tage-, wochen- oder monatsweise', 'public.montage'],
      'gewerbe' => ['Gewerbe', 'Läden, Büros und Lager', 'public.gewerbe'],
  ];
  $services = array_slice(SiteSettings::services(), 0, 4);
  try {
      $objects = \App\Models\SiteObject::query()->where('visible', true)->with(['property', 'photos'])->orderBy('sort')->orderBy('id')->limit(3)->get()->filter(fn ($o) => $o->property);
  } catch (\Throwable) {
      $objects = collect();
  }
  $n = 0;
@endphp
<style>
.hm-hero{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,1fr);gap:2.5rem;align-items:center;min-height:56vh;padding:1rem 0 2.5rem}
@media (max-width:860px){.hm-hero{grid-template-columns:1fr;min-height:0;gap:1.5rem}}
.hm-title{font-size:clamp(2.4rem,5.6vw,4.6rem);line-height:.98;font-weight:850;letter-spacing:-.045em;margin:0 0 1.4rem;text-transform:uppercase}
.hm-title span{display:block}
.hm-title span:last-child{color:var(--accent)}
.hm-intro{font-size:1.2rem;color:var(--muted);max-width:32rem;margin:0 0 1.8rem}
.hm-cta{display:flex;flex-wrap:wrap;gap:.8rem}
.hm-photo{position:relative;aspect-ratio:4/5;background:var(--soft);overflow:hidden}
.hm-photo img{width:100%;height:100%;object-fit:cover;display:block}
.hm-photo::after{content:"";position:absolute;left:0;bottom:0;width:38%;height:10px;background:var(--accent)}
.hm-photo.empty{display:flex;align-items:flex-end;padding:1.5rem;background:repeating-linear-gradient(135deg,#f3f4f6 0 14px,#fafafa 14px 28px)}
.hm-sec{border-top:2px solid var(--ink);margin-top:3.5rem;padding-top:1.4rem}
.hm-sec-head{display:flex;justify-content:space-between;align-items:baseline;gap:1rem;flex-wrap:wrap;margin-bottom:1.2rem}
.hm-sec-head h2{font-size:1.6rem;margin:0}
.hm-offers{display:grid;grid-template-columns:repeat(auto-fit,minmax(15rem,1fr));gap:0;border-left:1px solid var(--line)}
.hm-offer{display:block;padding:1.3rem 1.4rem 1.5rem;border-right:1px solid var(--line);border-bottom:1px solid var(--line);text-decoration:none;color:var(--ink)}
.hm-offer:hover{background:var(--soft);color:var(--ink)}
.hm-num{font-size:.85rem;font-weight:800;color:var(--accent);letter-spacing:.1em}
.hm-offer strong{display:block;font-size:1.35rem;margin:.3rem 0 .2rem;letter-spacing:-.02em}
.hm-offer .c{color:var(--muted);font-size:.95rem}
.hm-offer .arrow{display:inline-block;margin-top:.8rem;font-weight:700}
.hm-services{display:grid;grid-template-columns:repeat(auto-fill,minmax(15rem,1fr));gap:1.6rem}
.hm-services h3{font-size:1.1rem;margin:.2rem 0 .3rem}
.hm-services p{color:var(--muted);margin:0}
.hm-objects{display:grid;grid-template-columns:repeat(auto-fill,minmax(17rem,1fr));gap:1.4rem}
.hm-objects a{text-decoration:none;color:var(--ink)}
.hm-objects img,.hm-objects .ph{width:100%;aspect-ratio:4/3;object-fit:cover;display:block;background:var(--soft)}
.hm-objects strong{display:block;margin-top:.6rem;font-size:1.05rem}
.hm-objects span{color:var(--muted);font-size:.93rem}
.hm-contact{background:var(--primary);color:var(--on-primary);margin-top:3.5rem;padding:2.2rem;display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:1rem}
.hm-contact h2{color:#fff;margin:0;font-size:1.6rem}
.hm-contact p{margin:.3rem 0 0;color:#d1d5db}
</style>

<section class="hm-hero">
  <div>
    <p class="pub-kicker">{{ SiteSettings::name() }}</p>
    <h1 class="hm-title">@foreach ($lines as $l)<span>{{ $l }}</span>@endforeach</h1>
    <p class="hm-intro">{{ SiteSettings::get('site_intro') }}</p>
    <div class="hm-cta">
      @if ($offers)
        <a class="pub-btn" href="{{ route('public.'.array_key_first(array_filter($offers))) }}">Freie Angebote ansehen</a>
      @endif
      <a class="pub-btn ghost" href="{{ route('public.contact') }}">Kontakt aufnehmen</a>
    </div>
  </div>
  <div @class(['hm-photo', 'empty' => ! $hero])>
    @if ($hero)<img src="{{ $hero->url('l') }}" alt="" width="{{ $hero->width }}" height="{{ $hero->height }}">@endif
  </div>
</section>

@if (array_filter($offers))
  <section class="hm-sec">
    <div class="hm-sec-head"><h2>Aktuelle Angebote</h2></div>
    <div class="hm-offers">
      @foreach ($offerText as $k => [$title, $text, $route])
        @continue(($offers[$k] ?? 0) === 0)
        <a class="hm-offer" href="{{ route($route) }}">
          <span class="hm-num">{{ str_pad((string) ++$n, 2, '0', STR_PAD_LEFT) }}</span>
          <strong>{{ $title }}</strong>
          <span class="c">{{ $text }} · {{ $offers[$k] }} {{ $offers[$k] === 1 ? 'Angebot' : 'Angebote' }}</span><br>
          <span class="arrow">Ansehen →</span>
        </a>
      @endforeach
    </div>
  </section>
@endif

@if ($services)
  <section class="hm-sec">
    <div class="hm-sec-head"><h2>Leistungen</h2><a href="{{ route('site.services') }}">Alle Leistungen →</a></div>
    <div class="hm-services">
      @foreach ($services as $i => $s)
        <div><span class="hm-num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span><h3>{{ $s['title'] }}</h3><p>{{ $s['text'] }}</p></div>
      @endforeach
    </div>
  </section>
@endif

@if ($objects->isNotEmpty())
  <section class="hm-sec">
    <div class="hm-sec-head"><h2>Unsere Objekte</h2><a href="{{ route('site.objects') }}">Alle Objekte →</a></div>
    <div class="hm-objects">
      @foreach ($objects as $o)
        @php($ph = $o->photos->first())
        <a href="{{ route('site.objects') }}#o{{ $o->id }}">
          @if ($ph)<img src="{{ $ph->url('s') }}" alt="{{ $o->title ?: $o->property->name }}" loading="lazy">@else<div class="ph"></div>@endif
          <strong>{{ $o->title ?: $o->property->name }}</strong>
          <span>{{ trim(($o->property->zip ?? '').' '.($o->property->city ?? '')) }}</span>
        </a>
      @endforeach
    </div>
  </section>
@endif

<section class="hm-contact">
  <div><h2>Fragen oder Interesse?</h2><p>Schreiben Sie uns – wir melden uns zeitnah.@if ($email) Oder per E-Mail: <a href="mailto:{{ $email }}" style="color:#fff">{{ $email }}</a>@endif</p></div>
  <a class="pub-btn" href="{{ route('public.contact') }}">Zum Kontaktformular</a>
</section>
@endsection
