@extends('layouts.app')
@section('title', $listing->exists ? 'Inserat bearbeiten' : 'Neues Inserat')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Support\Listings\Listings;
  $kind = old('kind', $listing->kind);
  $dec = fn ($v, $d = 2) => $v === null ? '' : rtrim(rtrim(number_format((float) $v, $d, ',', ''), '0'), ',');
  $money = fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', '');
  $features = old('features', $listing->features ?? []);
@endphp
<style>
.ls-kind{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 .6rem}
.ls-kind label{display:flex;gap:.4rem;align-items:center;border:1px solid var(--line-strong);border-radius:.5rem;padding:.45rem .8rem;background:var(--surface);cursor:pointer}
.ls-feat{display:grid;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));gap:.3rem .8rem;margin:.4rem 0}
.ls-feat label{display:flex;gap:.45rem;align-items:center}
.ls-date{width:100%;padding:.5rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit}
.ls-photos{display:grid;grid-template-columns:repeat(auto-fill,minmax(11rem,1fr));gap:.8rem}
.ls-photo{border:1px solid var(--line);border-radius:.5rem;overflow:hidden;background:var(--surface)}
.ls-photo img{display:block;width:100%;aspect-ratio:4/3;object-fit:cover;background:var(--surface-2)}
.ls-photo .b{padding:.4rem;display:flex;flex-wrap:wrap;gap:.3rem;align-items:center}
.ls-photo input[type=text]{width:100%;padding:.3rem;border:1px solid var(--line-strong);border-radius:.3rem;font:inherit;font-size:.9rem}
.ls-first{position:relative}
.ls-first::after{content:"Titelbild";position:absolute;top:.4rem;left:.4rem;background:var(--primary);color:var(--on-primary);font-size:.8rem;padding:.1rem .45rem;border-radius:.3rem}
.ls-state{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
.ls-missing{background:var(--brass-bg);border:1px solid var(--brass-line);border-radius:.6rem;padding:.6rem .9rem;margin:0 0 1rem}
.ls-count{font-size:.85rem;color:var(--muted);float:right}
</style>

<div class="sd-head">
  <h1>{{ $listing->exists ? 'Inserat bearbeiten' : 'Neues Inserat' }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('listings.index') }}">Alle Inserate</a>
    @if ($listing->exists)
      <a class="sd-btn sec" href="{{ route('listings.portal', $listing) }}">Text für ImmoScout24 / Kleinanzeigen</a>
      @if ($listing->kind === 'montage')
        <a class="sd-btn sec" href="{{ route('bookings.index', $listing->unit_id) }}">Belegung</a>
      @endif
    @endif
  </div>
</div>
<p class="sd-muted">Einheit: {{ $unit->label }}, {{ $unit->property?->name }} ({{ $unit->property?->addressLine() }})</p>

@if ($listing->exists)
  <div class="sd-card">
    <div class="ls-state">
      <span>Status: <strong>{{ $listing->statusLabel() }}</strong></span>
      @if ($listing->isOnline())
        <a href="{{ route('public.listing', $listing->slug) }}" target="_blank" rel="noopener">Auf der Webseite ansehen</a>
        <form method="POST" action="{{ route('listings.status', $listing) }}" class="sd-inline">@csrf<input type="hidden" name="status" value="offline"><button class="sd-btn sec small" type="submit">Offline nehmen</button></form>
      @else
        <form method="POST" action="{{ route('listings.status', $listing) }}" class="sd-inline">@csrf<input type="hidden" name="status" value="online"><button class="sd-btn small" type="submit">Online stellen</button></form>
      @endif
    </div>
    @if (! empty($missing))
      <p class="sd-muted" style="margin:.6rem 0 0">Noch nicht vollständig: {{ implode(', ', $missing) }}.</p>
    @endif
  </div>
@endif

<form method="POST" action="{{ $listing->exists ? route('listings.update', $listing) : route('listings.store') }}" id="ls-form">
  @csrf
  @if ($listing->exists) @method('PUT') @endif
  <input type="hidden" name="unit_id" value="{{ $unit->id }}">

  <div class="sd-card">
    <h2>Art und Titel</h2>
    <div class="ls-kind">
      @foreach (Listings::KINDS as $k => $label)
        <label><input type="radio" name="kind" value="{{ $k }}" @checked($kind === $k) onchange="lsKind(this.value)"> {{ $label }}</label>
      @endforeach
    </div>
    <div class="sd-field">
      <label for="f-title">Titel * <span class="ls-count" data-count="f-title" data-max="100"></span></label>
      <input id="f-title" name="title" type="text" maxlength="100" required value="{{ old('title', $listing->title) }}">
      <small class="sd-muted">Bei Kleinanzeigen werden nur die ersten 65 Zeichen übernommen.</small>
    </div>
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-available_from">Verfügbar ab</label>
        <input class="ls-date" id="f-available_from" name="available_from" type="date" value="{{ old('available_from', $listing->available_from?->format('Y-m-d')) }}">
        <small class="sd-muted">Leer = sofort</small>
      </div>
      @include('partials.sd-field', ['name' => 'area_sqm', 'label' => 'Fläche in m²', 'value' => $dec($listing->area_sqm), 'inputmode' => 'decimal'])
      @include('partials.sd-field', ['name' => 'rooms', 'label' => 'Zimmer', 'value' => $dec($listing->rooms, 1), 'inputmode' => 'decimal'])
      @include('partials.sd-field', ['name' => 'floor', 'label' => 'Etage', 'value' => $listing->floor])
      @include('partials.sd-field', ['name' => 'year_built', 'label' => 'Baujahr des Hauses', 'value' => $listing->year_built, 'inputmode' => 'numeric'])
    </div>
    <label class="sd-check"><input type="hidden" name="show_address" value="0"><input type="checkbox" name="show_address" value="1" @checked(old('show_address', $listing->show_address))> Straße und Hausnummer öffentlich zeigen (sonst nur PLZ und Ort)</label>
  </div>

  <div class="sd-card">
    <h2>Preis</h2>
    <div class="ls-only" data-kind="wohnen gewerbe">
      <div class="sd-grid">
        @include('partials.sd-field', ['name' => 'rent_cold', 'label' => 'Kaltmiete monatlich (€)', 'value' => $money($listing->rent_cold), 'inputmode' => 'decimal'])
        @include('partials.sd-field', ['name' => 'service_charge', 'label' => 'Nebenkosten-Vorauszahlung (€)', 'value' => $money($listing->service_charge), 'inputmode' => 'decimal'])
        @include('partials.sd-field', ['name' => 'heating_charge', 'label' => 'Heizkosten-Vorauszahlung (€)', 'value' => $money($listing->heating_charge), 'inputmode' => 'decimal', 'hint' => 'Leer, wenn in den Nebenkosten enthalten oder der Mieter direkt zahlt'])
        @include('partials.sd-field', ['name' => 'deposit', 'label' => 'Kaution (€)', 'value' => $money($listing->deposit), 'inputmode' => 'decimal', 'hint' => 'Bei Wohnraum höchstens drei Kaltmieten'])
      </div>
      <label class="sd-check ls-only" data-kind="gewerbe"><input type="hidden" name="plus_vat" value="0"><input type="checkbox" name="plus_vat" value="1" @checked(old('plus_vat', $listing->plus_vat))> Preise zuzüglich Umsatzsteuer</label>
    </div>
    <div class="ls-only" data-kind="montage">
      <div class="sd-grid">
        @include('partials.sd-field', ['name' => 'price_night', 'label' => 'Preis pro Nacht (€)', 'value' => $money($listing->price_night), 'inputmode' => 'decimal'])
        @include('partials.sd-field', ['name' => 'price_week', 'label' => 'Preis pro Woche (€)', 'value' => $money($listing->price_week), 'inputmode' => 'decimal'])
        @include('partials.sd-field', ['name' => 'price_month', 'label' => 'Preis pro Monat (€)', 'value' => $money($listing->price_month), 'inputmode' => 'decimal'])
        @include('partials.sd-field', ['name' => 'beds', 'label' => 'Schlafplätze', 'value' => $listing->beds, 'inputmode' => 'numeric'])
        @include('partials.sd-field', ['name' => 'min_nights', 'label' => 'Mindestaufenthalt (Nächte)', 'value' => $listing->min_nights, 'inputmode' => 'numeric'])
      </div>
      <label class="sd-check"><input type="hidden" name="price_per_person" value="0"><input type="checkbox" name="price_per_person" value="1" @checked(old('price_per_person', $listing->price_per_person))> Preise gelten pro Person (sonst für die ganze Wohnung)</label>
      <p class="sd-muted" style="margin:.4rem 0 0">Leere Felder werden nicht angezeigt. Nur ausfüllen, was du anbietest.</p>
    </div>
  </div>

  <div class="sd-card">
    <h2>Texte</h2>
    @foreach (['description' => ['Objektbeschreibung', 'Was ist das Besondere? Größe, Zuschnitt, Zustand, für wen geeignet.'], 'equipment_text' => ['Ausstattung', 'Bodenbeläge, Bad, Küche, Fenster, Heizung …'], 'location_text' => ['Lage', 'Einkaufen, Bus und Bahn, Autobahn, Schulen …'], 'other_text' => ['Sonstiges', 'Besichtigung, Bedingungen, Hinweise …']] as $f => [$tLabel, $tHint])
      <div class="sd-field">
        <label for="f-{{ $f }}">{{ $tLabel }} <span class="ls-count" data-count="f-{{ $f }}" data-max="3999"></span></label>
        <textarea id="f-{{ $f }}" name="{{ $f }}" rows="{{ $f === 'description' ? 7 : 4 }}" maxlength="3999" placeholder="{{ $tHint }}">{{ old($f, $listing->{$f}) }}</textarea>
      </div>
    @endforeach
    <h3 style="margin:1rem 0 .2rem;font-size:1rem">Merkmale</h3>
    <div class="ls-feat">
      @foreach (Listings::FEATURES as $key => [$fLabel, $kinds])
        <label class="ls-only" data-kind="{{ implode(' ', $kinds) }}"><input type="checkbox" name="features[]" value="{{ $key }}" @checked(in_array($key, $features, true))> {{ $fLabel }}</label>
      @endforeach
    </div>
  </div>

  <div class="sd-card ls-only" data-kind="wohnen gewerbe montage">
    <h2>Energieausweis</h2>
    <p class="sd-muted" style="margin-top:0">In Anzeigen für Wohnungen und Gewerbe sind diese Angaben Pflicht, wenn ein Energieausweis vorliegt (§ 87 GEG). Sie stehen auf dem Ausweis.</p>
    <div class="sd-grid">
      @include('partials.sd-select', ['name' => 'energy_type', 'label' => 'Art des Ausweises', 'options' => Listings::ENERGY_TYPES, 'value' => $listing->energy_type])
      @include('partials.sd-field', ['name' => 'energy_value', 'label' => 'Kennwert in kWh/(m²·a)', 'value' => $dec($listing->energy_value, 1), 'inputmode' => 'decimal'])
      @include('partials.sd-field', ['name' => 'energy_source', 'label' => 'Wesentlicher Energieträger', 'value' => $listing->energy_source, 'hint' => 'z. B. Erdgas, Heizöl, Fernwärme'])
      @include('partials.sd-field', ['name' => 'energy_year', 'label' => 'Baujahr laut Ausweis', 'value' => $listing->energy_year, 'inputmode' => 'numeric'])
      @include('partials.sd-select', ['name' => 'energy_class', 'label' => 'Effizienzklasse', 'options' => array_combine(Listings::ENERGY_CLASSES, Listings::ENERGY_CLASSES), 'value' => $listing->energy_class, 'hint' => 'Nur bei Wohngebäuden'])
    </div>
  </div>

  <div class="sd-actions" style="margin-bottom:1.2rem">
    <button class="sd-btn" type="submit">{{ $listing->exists ? 'Speichern' : 'Als Entwurf speichern' }}</button>
    <a class="sd-btn sec" href="{{ route('listings.index') }}">Abbrechen</a>
  </div>
</form>

@if ($listing->exists)
  <div class="sd-card" id="fotos">
    <h2>Fotos</h2>
    @unless ($gd)
      <div class="sd-err">Auf dem Server fehlt die Bildbearbeitung (PHP-Erweiterung GD). Fotos können erst hochgeladen werden, wenn sie bei IONOS aktiv ist.</div>
    @endunless
    <form method="POST" action="{{ route('listings.photos.store', $listing) }}" enctype="multipart/form-data" style="margin-bottom:1rem">
      @csrf
      <input type="file" name="fotos[]" accept="image/jpeg,image/png,image/webp" multiple required>
      <button class="sd-btn small" type="submit">Hochladen</button>
      <br><small class="sd-muted">Mehrere auf einmal möglich (JPG, PNG, WebP). Die Fotos werden verkleinert, Standortdaten der Kamera werden entfernt. Keine Personen und keine Kfz-Kennzeichen zeigen.</small>
    </form>
    @if ($listing->photos->isEmpty())
      <p class="sd-muted" style="margin:0">Noch keine Fotos.</p>
    @else
      <div class="ls-photos">
        @foreach ($listing->photos as $i => $p)
          <div class="ls-photo">
            <div @class(['ls-first' => $i === 0])><img src="{{ route('listings.photos.show', [$listing, $p, 's']) }}" alt="" loading="lazy"></div>
            <div class="b">
              <form method="POST" action="{{ route('listings.photos.update', [$listing, $p]) }}" style="width:100%">
                @csrf @method('PUT')
                <input type="text" name="caption" value="{{ $p->caption }}" placeholder="Bildunterschrift" maxlength="190" onchange="this.form.submit()" aria-label="Bildunterschrift">
              </form>
              @if ($i > 0)
                <form method="POST" action="{{ route('listings.photos.update', [$listing, $p]) }}" class="sd-inline">@csrf @method('PUT')<input type="hidden" name="move" value="first"><button class="sd-btn sec small" type="submit" title="Als Titelbild">★</button></form>
                <form method="POST" action="{{ route('listings.photos.update', [$listing, $p]) }}" class="sd-inline">@csrf @method('PUT')<input type="hidden" name="move" value="up"><button class="sd-btn sec small" type="submit" title="Nach vorne">←</button></form>
              @endif
              @if ($i < $listing->photos->count() - 1)
                <form method="POST" action="{{ route('listings.photos.update', [$listing, $p]) }}" class="sd-inline">@csrf @method('PUT')<input type="hidden" name="move" value="down"><button class="sd-btn sec small" type="submit" title="Nach hinten">→</button></form>
              @endif
              <form method="POST" action="{{ route('listings.photos.destroy', [$listing, $p]) }}" class="sd-inline" onsubmit="return confirm('Foto löschen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Löschen</button></form>
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>

  <div class="sd-card">
    <h2>Inserat löschen</h2>
    <p class="sd-muted">Löscht das Inserat mit allen Fotos. Wenn die Einheit nur vorübergehend vermietet ist, besser „Offline nehmen“, dann kann es später wieder verwendet werden.</p>
    <form method="POST" action="{{ route('listings.destroy', $listing) }}" onsubmit="return confirm('Inserat mit allen Fotos löschen?')">@csrf @method('DELETE')<button class="sd-btn danger small" type="submit">Löschen</button></form>
  </div>
@endif

<script>
function lsKind(k) {
  document.querySelectorAll('.ls-only').forEach(function (el) {
    var show = el.dataset.kind.split(' ').indexOf(k) >= 0;
    el.style.display = show ? '' : 'none';
    el.querySelectorAll('input,select,textarea').forEach(function (i) { if (!show && i.type === 'checkbox') { i.checked = false; } });
  });
}
lsKind(@json($kind));
document.querySelectorAll('[data-count]').forEach(function (c) {
  var f = document.getElementById(c.dataset.count), max = +c.dataset.max;
  var u = function () { c.textContent = f.value.length + ' / ' + max; };
  f.addEventListener('input', u); u();
});
</script>
@endsection
