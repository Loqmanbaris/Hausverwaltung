@extends('layouts.app')
@section('title', 'Webseite gestalten')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php use App\Support\Site\SiteSettings; @endphp
<style>
.ws-tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem}
.ws-tabs a{padding:.4rem .8rem;border:1px solid #c5ccd3;border-radius:2rem;text-decoration:none;color:var(--ink);background:#fff}
.ws-colors{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.ws-colors label{display:flex;align-items:center;gap:.35rem;border:1px solid #d0d5dd;border-radius:.5rem;padding:.35rem .6rem;cursor:pointer;background:#fff}
.ws-colors .sw{width:1.2rem;height:1.2rem;border-radius:.25rem;display:inline-block}
.ws-img{display:flex;gap:1rem;align-items:flex-start;flex-wrap:wrap}
.ws-img img{max-width:240px;max-height:160px;border:1px solid #e3e6ea;border-radius:.4rem;background:repeating-linear-gradient(45deg,#f3f4f6 0 8px,#fff 8px 16px)}
.ws-svc{border:1px solid #e3e6ea;border-radius:.6rem;padding:.6rem .8rem;margin:0 0 .6rem;background:#fff}
.ws-svc input,.ws-svc textarea,.ws-obj input[type=text],.ws-obj textarea{width:100%;box-sizing:border-box;padding:.45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit}
.ws-svc textarea,.ws-obj textarea{min-height:4.5rem;margin-top:.4rem}
.ws-obj{border:1px solid #e3e6ea;border-radius:.6rem;padding:.8rem 1rem;margin:0 0 .8rem;background:#fff}
.ws-obj.off{opacity:.7}
.ws-photos{display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0}
.ws-photos figure{margin:0;position:relative}
.ws-photos img{width:120px;height:90px;object-fit:cover;border-radius:.35rem;display:block}
.ws-photos button{position:absolute;top:2px;right:2px;border:0;background:rgba(0,0,0,.6);color:#fff;border-radius:.3rem;font-size:.75rem;padding:.1rem .35rem;cursor:pointer}
</style>

<div class="sd-head">
  <h1>Webseite gestalten</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('home') }}" target="_blank" rel="noopener">Webseite ansehen</a></div>
</div>
<p class="sd-muted">Alles hier erscheint öffentlich auf der Webseite. Änderungen gelten sofort. Fotos ohne Personen und ohne Kfz-Kennzeichen verwenden.</p>
<nav class="ws-tabs"><a href="#aussehen">Aussehen und Startseite</a><a href="#leistungen">Leistungen</a><a href="#objekte">Unsere Objekte</a><a href="#mieter">Für Mieter</a></nav>
@unless ($gd)<div class="sd-err">Auf dem Server fehlt die Bildbearbeitung (GD). Fotos können erst hochgeladen werden, wenn sie bei IONOS aktiv ist.</div>@endunless

<form method="POST" action="{{ route('site.update') }}">
  @csrf @method('PUT')
  <div class="sd-card" id="aussehen">
    <h2>Aussehen und Startseite</h2>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'site_name', 'label' => 'Name der Webseite', 'value' => $values['site_name'], 'hint' => 'erscheint oben links (wenn kein Logo) und im Browser-Tab'])
    </div>
    <div class="sd-field">
      <label>Akzentfarbe</label>
      <div class="ws-colors">
        @foreach (SiteSettings::ACCENTS as $hex => $name)
          <label><input type="radio" name="site_accent" value="{{ $hex }}" @checked(strtolower(old('site_accent', $values['site_accent'])) === $hex)><span class="sw" style="background:{{ $hex }}"></span>{{ $name }}</label>
        @endforeach
        <label><input type="radio" name="site_accent" value="{{ in_array(strtolower($values['site_accent']), array_keys(SiteSettings::ACCENTS), true) ? '#334155' : $values['site_accent'] }}" id="ws-own" @checked(! in_array(strtolower(old('site_accent', $values['site_accent'])), array_keys(SiteSettings::ACCENTS), true))>eigene: <input type="color" value="{{ $values['site_accent'] }}" oninput="var r=document.getElementById('ws-own');r.value=this.value;r.checked=true" aria-label="Eigene Farbe"></label>
      </div>
    </div>
    <div class="sd-field">
      <label for="f-site_headline">{{ SiteSettings::FIELDS['site_headline'][0] }}</label>
      <textarea id="f-site_headline" name="site_headline" rows="3" maxlength="120">{{ old('site_headline', $values['site_headline']) }}</textarea>
      <small class="sd-muted">Kurz halten, z. B. „Wohnen. / Arbeiten. / Ankommen.“ – die letzte Zeile erscheint in der Akzentfarbe.</small>
    </div>
    <div class="sd-field">
      <label for="f-site_intro">{{ SiteSettings::FIELDS['site_intro'][0] }}</label>
      <textarea id="f-site_intro" name="site_intro" rows="2" maxlength="400">{{ old('site_intro', $values['site_intro']) }}</textarea>
    </div>
  </div>

  <div class="sd-card" id="leistungen">
    <h2>Leistungen</h2>
    <div class="sd-field"><label for="f-si">Einleitung</label><textarea id="f-si" name="site_services_intro" rows="2" maxlength="600">{{ old('site_services_intro', $values['site_services_intro']) }}</textarea></div>
    @for ($i = 0; $i < 8; $i++)
      @php($s = $services[$i] ?? ['title' => '', 'text' => ''])
      <div class="ws-svc">
        <input type="text" name="services[{{ $i }}][title]" value="{{ $s['title'] }}" maxlength="80" placeholder="Leistung {{ $i + 1 }} – Überschrift (leer = nicht anzeigen)" aria-label="Überschrift {{ $i + 1 }}">
        <textarea name="services[{{ $i }}][text]" maxlength="600" placeholder="Beschreibung" aria-label="Beschreibung {{ $i + 1 }}">{{ $s['text'] }}</textarea>
      </div>
    @endfor
  </div>

  <div class="sd-card">
    <h2>Texte für „Unsere Objekte“ und „Für Mieter“</h2>
    <div class="sd-field"><label for="f-oi">Unsere Objekte: Einleitung</label><textarea id="f-oi" name="site_objects_intro" rows="2" maxlength="600">{{ old('site_objects_intro', $values['site_objects_intro']) }}</textarea></div>
    <div class="sd-field"><label for="f-ti">Für Mieter: Einleitung</label><textarea id="f-ti" name="site_tenants_intro" rows="2" maxlength="600">{{ old('site_tenants_intro', $values['site_tenants_intro']) }}</textarea></div>
    <div class="sd-field"><label for="f-dt">Für Mieter: Text bei „Schaden melden“</label><textarea id="f-dt" name="site_damage_text" rows="3" maxlength="1200">{{ old('site_damage_text', $values['site_damage_text']) }}</textarea></div>
    <div class="sd-field"><label for="f-em">Notfallnummern (eine je Zeile, z. B. „Heizungsnotdienst Firma X: 05161 12345“)</label><textarea id="f-em" name="site_emergency" rows="5" maxlength="2000">{{ old('site_emergency', $values['site_emergency']) }}</textarea></div>
  </div>
  <p><button class="sd-btn" type="submit">Texte und Farbe speichern</button></p>
</form>

<div class="sd-card">
  <h2>Logo und Startfoto</h2>
  <div class="ws-img">
    <div>
      <strong>Logo</strong> <span class="sd-muted">(PNG mit transparentem Hintergrund am besten)</span>
      <div style="margin:.4rem 0">@if ($logo)<img src="{{ $logo->url() }}" alt="Logo">@else<span class="sd-muted">kein Logo – oben steht dann der Name</span>@endif</div>
      <form method="POST" action="{{ route('site.upload') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="kind" value="logo"><input type="file" name="files[]" accept="image/png,image/jpeg,image/webp" required> <button class="sd-btn sec small" type="submit">Hochladen</button></form>
      @if ($logo)<form method="POST" action="{{ route('site.asset.destroy', $logo) }}" style="margin-top:.4rem">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Logo entfernen</button></form>@endif
    </div>
    <div>
      <strong>Startfoto</strong> <span class="sd-muted">(hochkant oder quadratisch wirkt am besten)</span>
      <div style="margin:.4rem 0">@if ($hero)<img src="{{ $hero->url('s') }}" alt="Startfoto">@else<span class="sd-muted">kein Foto – es erscheint eine schlichte Fläche</span>@endif</div>
      <form method="POST" action="{{ route('site.upload') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="kind" value="hero"><input type="file" name="files[]" accept="image/*" required> <button class="sd-btn sec small" type="submit">Hochladen</button></form>
      @if ($hero)<form method="POST" action="{{ route('site.asset.destroy', $hero) }}" style="margin-top:.4rem">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Foto entfernen</button></form>@endif
    </div>
  </div>
</div>

<div class="sd-card" id="objekte">
  <h2>Unsere Objekte</h2>
  <p class="sd-muted" style="margin-top:0">Haken setzen, welche Häuser auf der Webseite erscheinen. Angezeigt werden Titel, PLZ und Ort, Text und Fotos – keine Straße, keine Mieter.</p>
  <form method="POST" action="{{ route('site.objects.update') }}" id="ws-objs">@csrf @method('PUT')</form>
  @forelse ($objects as $o)
    <div @class(['ws-obj', 'off' => ! $o->visible]) id="objekt{{ $o->id }}">
      <label class="sd-check" style="margin:0 0 .4rem"><input type="hidden" name="o[{{ $o->id }}][visible]" value="0" form="ws-objs"><input type="checkbox" name="o[{{ $o->id }}][visible]" value="1" form="ws-objs" @checked($o->visible)> <strong>{{ $o->property->name }}</strong> <span class="sd-muted">{{ $o->property->city }}</span></label>
      <div class="sd-grid">
        <div class="sd-field"><label>Titel auf der Webseite</label><input type="text" name="o[{{ $o->id }}][title]" value="{{ $o->title }}" placeholder="{{ $o->property->name }}" maxlength="120" form="ws-objs"></div>
        <div class="sd-field"><label>Reihenfolge</label><input type="text" inputmode="numeric" name="o[{{ $o->id }}][sort]" value="{{ $o->sort }}" form="ws-objs"></div>
      </div>
      <textarea name="o[{{ $o->id }}][text]" maxlength="2000" placeholder="Kurze Beschreibung, z. B. Baujahr, Anzahl Wohnungen, Lage" form="ws-objs" aria-label="Beschreibung">{{ $o->text }}</textarea>
      <div class="ws-photos">
        @foreach ($o->photos as $ph)
          <figure><img src="{{ $ph->url('s') }}" alt=""><form method="POST" action="{{ route('site.asset.destroy', $ph) }}" onsubmit="return confirm('Foto entfernen?')">@csrf @method('DELETE')<button type="submit" aria-label="Foto entfernen">✕</button></form></figure>
        @endforeach
      </div>
      <form method="POST" action="{{ route('site.upload') }}" enctype="multipart/form-data">@csrf<input type="hidden" name="kind" value="object"><input type="hidden" name="ref" value="{{ $o->id }}"><input type="file" name="files[]" accept="image/*" multiple required> <button class="sd-btn sec small" type="submit">Fotos hochladen</button></form>
    </div>
  @empty
    <p class="sd-muted">Noch keine Objekte im CRM.</p>
  @endforelse
  @if ($objects->isNotEmpty())<button class="sd-btn" type="submit" form="ws-objs">Objekte speichern</button>@endif
</div>

<div class="sd-card" id="mieter">
  <h2>Downloads für Mieter</h2>
  <p class="sd-muted" style="margin-top:0">Zum Beispiel Hausordnung, Müllkalender, Merkblatt Heizen und Lüften. Nur PDF.</p>
  @if ($downloads->isNotEmpty())
    <ul>
      @foreach ($downloads as $d)
        <li><a href="{{ $d->url() }}" target="_blank" rel="noopener">{{ $d->title }}</a>
          <form method="POST" action="{{ route('site.asset.destroy', $d) }}" class="sd-inline" onsubmit="return confirm('Datei entfernen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Entfernen</button></form></li>
      @endforeach
    </ul>
  @endif
  <form method="POST" action="{{ route('site.upload') }}" enctype="multipart/form-data" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
    @csrf<input type="hidden" name="kind" value="download">
    <input type="text" name="title" placeholder="Titel, z. B. Hausordnung" maxlength="190" required style="padding:.45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit">
    <input type="file" name="files[]" accept="application/pdf" required>
    <button class="sd-btn sec small" type="submit">Hochladen</button>
  </form>
</div>
@endsection
