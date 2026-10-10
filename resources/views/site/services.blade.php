@extends('layouts.public')
@section('title', 'Leistungen – '.\App\Support\Site\SiteSettings::name())
@section('content')
<style>
.sv-list{display:grid;grid-template-columns:repeat(auto-fit,minmax(18rem,1fr));gap:0;border-top:2px solid var(--ink);margin-top:2rem}
.sv-item{padding:1.4rem 1.4rem 1.6rem 0;border-bottom:1px solid var(--line)}
.sv-item .n{font-size:.85rem;font-weight:800;color:var(--accent);letter-spacing:.1em}
.sv-item h2{font-size:1.3rem;margin:.3rem 0 .4rem}
.sv-item p{color:var(--muted);margin:0;white-space:pre-line}
</style>
<p class="pub-kicker">Leistungen</p>
<h1 class="pub-h1">Was wir anbieten</h1>
@if ($intro)<p class="pub-lead">{{ $intro }}</p>@endif
<div class="sv-list">
  @foreach ($services as $i => $s)
    <div class="sv-item"><span class="n">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span><h2>{{ $s['title'] }}</h2><p>{{ $s['text'] }}</p></div>
  @endforeach
</div>
<p style="margin-top:2rem"><a class="pub-btn" href="{{ route('public.contact') }}">Anfrage stellen</a></p>
@endsection
