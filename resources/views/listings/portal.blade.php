@extends('layouts.app')
@section('title', 'Texte für Portale')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.pt-box{position:relative;margin:0 0 .9rem}
.pt-box label{display:block;font-weight:600;margin-bottom:.25rem}
.pt-box textarea,.pt-box input{width:100%;padding:.5rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;background:#fbfcfd}
.pt-box textarea{min-height:7rem}
.pt-copy{margin-top:.3rem}
.pt-len{color:#667085;font-size:.85rem;margin-left:.4rem}
.pt-facts{display:grid;grid-template-columns:max-content 1fr;gap:.25rem 1rem;margin:0}
.pt-facts dt{color:#475467}
.pt-facts dd{margin:0}
</style>
<div class="sd-head">
  <h1>Texte für ImmoScout24 und Kleinanzeigen</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('listings.edit', $listing) }}">Zurück zum Inserat</a>
    @if ($listing->photos->isNotEmpty())<a class="sd-btn" href="{{ route('listings.photos.zip', $listing) }}">Alle Fotos herunterladen (ZIP)</a>@endif
  </div>
</div>
<p class="sd-muted">{{ $listing->title }} · {{ $listing->unit?->label }}, {{ $listing->unit?->property?->name }}. Auf „Kopieren“ tippen und im Portal in das passende Feld einfügen. Fotos in der Reihenfolge 01, 02 … hochladen, das erste ist das Titelbild.</p>
@if (! empty($missing))
  <div class="sd-err" style="background:#fffaeb;border-color:#fedf89;color:#7a4b00">Noch nicht vollständig: {{ implode(', ', $missing) }}.</div>
@endif

<div class="sd-card">
  <h2>Eckdaten</h2>
  <p class="sd-muted" style="margin-top:0">Diese Werte trägst du in die Formularfelder des Portals ein.</p>
  <dl class="pt-facts">
    @foreach ($facts as $k => $v)
      <dt>{{ $k }}</dt><dd>{{ $v }}</dd>
    @endforeach
  </dl>
</div>

<div class="sd-card">
  <h2>ImmoScout24</h2>
  @foreach (['title' => ['Titel', 100], 'description' => ['Objektbeschreibung', 3999], 'equipment' => ['Ausstattung', 3999], 'location' => ['Lage', 3999], 'other' => ['Sonstiges', 3999]] as $k => [$label, $max])
    <div class="pt-box">
      <label for="is-{{ $k }}">{{ $label }} <span class="pt-len">{{ mb_strlen($is24[$k]) }} / {{ $max }} Zeichen</span></label>
      @if ($k === 'title')
        <input id="is-{{ $k }}" type="text" readonly value="{{ $is24[$k] }}">
      @else
        <textarea id="is-{{ $k }}" readonly>{{ $is24[$k] }}</textarea>
      @endif
      <button class="sd-btn sec small pt-copy" type="button" data-copy="is-{{ $k }}">Kopieren</button>
    </div>
  @endforeach
</div>

<div class="sd-card">
  <h2>Kleinanzeigen</h2>
  <div class="pt-box">
    <label for="ka-title">Titel <span class="pt-len">{{ mb_strlen($ka['title']) }} / 65 Zeichen</span></label>
    <input id="ka-title" type="text" readonly value="{{ $ka['title'] }}">
    <button class="sd-btn sec small pt-copy" type="button" data-copy="ka-title">Kopieren</button>
  </div>
  @if ($ka['price'] !== '')
    <div class="pt-box">
      <label for="ka-price">Preis (€) <span class="pt-len">{{ $listing->kind === 'montage' ? 'erster angegebener Preis' : 'Gesamtmiete' }}</span></label>
      <input id="ka-price" type="text" readonly value="{{ $ka['price'] }}">
      <button class="sd-btn sec small pt-copy" type="button" data-copy="ka-price">Kopieren</button>
    </div>
  @endif
  <div class="pt-box">
    <label for="ka-text">Beschreibung <span class="pt-len">{{ mb_strlen($ka['text']) }} / 4000 Zeichen</span></label>
    <textarea id="ka-text" readonly style="min-height:16rem">{{ $ka['text'] }}</textarea>
    <button class="sd-btn sec small pt-copy" type="button" data-copy="ka-text">Kopieren</button>
  </div>
  @unless ($listing->isOnline())
    <p class="sd-muted" style="margin:0">Hinweis: Der Link zur Webseite im Text funktioniert erst, wenn das Inserat online ist.</p>
  @endunless
</div>

<script>
document.querySelectorAll('[data-copy]').forEach(function (b) {
  b.addEventListener('click', function () {
    var f = document.getElementById(b.dataset.copy);
    var done = function () { b.textContent = 'Kopiert ✓'; setTimeout(function () { b.textContent = 'Kopieren'; }, 1500); };
    if (navigator.clipboard && window.isSecureContext) {
      navigator.clipboard.writeText(f.value).then(done);
    } else {
      f.select(); document.execCommand('copy'); done();
    }
  });
});
</script>
@endsection
