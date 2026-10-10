@extends('layouts.app')
@section('title', 'Zähler')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php use App\Models\Meter; @endphp
<div class="sd-head">
  <h1>Zähler: {{ $property->name }}</h1>
  <div class="sd-actions">
    @if ($meters->contains(fn ($m) => $m->isHeat() && $m->remote))<a class="sd-btn sec" href="{{ route('consumption.show', $property) }}">Verbrauchsinfo</a>@endif
    <a class="sd-btn sec" href="{{ route('nk.settings', $property) }}">Einstellungen</a>
    <a class="sd-btn sec" href="{{ route('nk.index') }}">Zur Abrechnung</a>
  </div>
</div>
@php
  $heatMeters = $meters->filter(fn ($m) => $m->isHeat());
  $notRemote = $heatMeters->where('remote', false);
  $expired = $meters->filter(fn ($m) => $m->calibrationExpired());
  $expiring = $meters->filter(fn ($m) => $m->calibrated_until !== null && (int) $m->calibrated_until === (int) date('Y'));
@endphp
@if ($notRemote->isNotEmpty())
  <div class="{{ date('Y-m-d') >= '2026-07-01' ? 'sd-err' : 'rt-warn' }}"><strong>{{ $notRemote->count() }} von {{ $heatMeters->count() }} Zählern für Heizung/Warmwasser sind nicht fernablesbar.</strong> Bis 31.12.2026 müssen alle fernablesbar sein (Funkzähler), sonst dürfen Mieter ihren Anteil an den Heizkosten um 3 % kürzen (§ 12 Heizkostenverordnung). Nach dem Tausch beim Zähler „fernablesbar“ anhaken.</div>
@elseif ($heatMeters->isNotEmpty())
  <div class="sd-ok">Alle {{ $heatMeters->count() }} Zähler für Heizung/Warmwasser sind fernablesbar. Die Mieter bekommen monatlich eine <a href="{{ route('consumption.show', $property) }}">Verbrauchsinformation</a>.</div>
@endif
@if ($expired->isNotEmpty() || $expiring->isNotEmpty())
  <div class="rt-warn">Eichfrist: @if ($expired->isNotEmpty()){{ $expired->count() }} Zähler abgelaufen. @endif @if ($expiring->isNotEmpty()){{ $expiring->count() }} Zähler laufen zum 31.12.{{ date('Y') }} ab. @endif Tausch rechtzeitig beauftragen (Warmwasser- und Wärmezähler 5 Jahre, Kaltwasser 6 Jahre).</div>
@endif
<p class="sd-muted">Für die Abrechnung braucht jeder Zähler einen Stand am Anfang und am Ende des Zeitraums (bis zu 31 Tage daneben ist in Ordnung). Bei einem Mieterwechsel im Jahr wird der Verbrauch nach Tagen aufgeteilt.</p>

<div class="sd-card">
  <h2>Zählerstände eintragen</h2>
  @if ($meters->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine Zähler angelegt (siehe unten).</p>
  @else
    <form method="GET" action="{{ route('nk.meters', $property) }}" class="rt-filter">
      <label style="margin:0">Stichtag <input type="date" name="stichtag" value="{{ $date }}" onchange="this.form.submit()"></label>
    </form>
    <form method="POST" action="{{ route('nk.readings.store', $property) }}">
      @csrf
      <input type="hidden" name="read_on" value="{{ $date }}">
      <div class="sd-table-wrap">
        <table class="sd rt-stack">
          <thead><tr><th>Einheit</th><th>Zähler</th><th>Letzter Stand</th><th>Stand am {{ \Carbon\Carbon::parse($date)->format('d.m.Y') }}</th></tr></thead>
          <tbody>
            @foreach ($meters as $m)
              @php($onDate = $m->readings->first(fn ($r) => $r->read_on->format('Y-m-d') === $date))
              @php($last = $m->readings->last())
              <tr>
                <td>{{ $m->unit?->label }}</td>
                <td>{{ $m->title() }}@if ($m->isHeat()) <span class="sd-badge {{ $m->remote ? 'rt-ok' : 'rt-late' }}">{{ $m->remote ? 'Funk' : 'nicht fernablesbar' }}</span>@endif @if ($m->calibrationExpired())<span class="sd-badge rt-late">Eichfrist abgelaufen</span>@endif @if ((float) $m->factor != 1.0)<br><span class="sd-muted">Faktor {{ rtrim(rtrim(number_format((float) $m->factor, 4, ',', ''), '0'), ',') }}</span>@endif</td>
                <td data-l="Letzter Stand">@if ($last){{ number_format((float) $last->value, 3, ',', '.') }} {{ Meter::UNITS[$m->kind] ?? '' }}<br><span class="sd-muted">{{ $last->read_on->format('d.m.Y') }}</span>@else<span class="sd-muted">–</span>@endif</td>
                <td><input type="text" name="value[{{ $m->id }}]" inputmode="decimal" style="width:9rem;padding:.4rem" value="{{ old('value.'.$m->id, $onDate ? rtrim(rtrim(number_format((float) $onDate->value, 3, ',', ''), '0'), ',') : '') }}" aria-label="Stand {{ $m->title() }}"> {{ Meter::UNITS[$m->kind] ?? '' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button class="sd-btn" type="submit">Zählerstände speichern</button>
      <span class="sd-muted">Leere Felder werden übersprungen.</span>
    </form>
  @endif
</div>

<div class="sd-card">
  <h2>Zählerstände aus Datei übernehmen</h2>
  <p class="sd-muted" style="margin-top:0">Für Funkzähler: Export des Ablesesystems als CSV mit den Spalten <strong>Zählernummer; Datum; Stand</strong> (z. B. <code>12345678;31.10.2026;1520,4</code>). Die Zählernummer muss beim Zähler eingetragen sein. Vorhandene Stände am selben Tag werden überschrieben.</p>
  <form method="POST" action="{{ route('nk.readings.import', $property) }}" enctype="multipart/form-data" class="rt-filter" style="margin:0">
    @csrf
    <input type="file" name="file" accept=".csv,.txt,text/csv" required aria-label="CSV-Datei">
    <button class="sd-btn sec" type="submit">Einlesen</button>
  </form>
</div>

<div class="sd-card">
  <h2>Zähler und Verlauf</h2>
  @foreach ($meters as $m)
    <details style="margin-bottom:.5rem" id="zaehler-{{ $m->id }}">
      <summary><strong>{{ $m->unit?->label }}</strong>: {{ $m->title() }} <span class="sd-muted">({{ $m->readings->count() }} Stände{{ $m->calibrated_until ? ', geeicht bis '.$m->calibrated_until : '' }}{{ $m->isHeat() ? ($m->remote ? ', fernablesbar' : ', nicht fernablesbar') : '' }})</span></summary>
      <form method="POST" action="{{ route('nk.meters.update', [$property, $m]) }}" style="margin:.5rem 0;padding:.6rem;background:#f7f9fb;border-radius:.5rem">
        @csrf @method('PUT')
        <div class="sd-grid">
          <div class="sd-field"><label for="m{{ $m->id }}-label">Bezeichnung</label><input id="m{{ $m->id }}-label" name="label" value="{{ $m->label }}"></div>
          <div class="sd-field"><label for="m{{ $m->id }}-number">Zählernummer</label><input id="m{{ $m->id }}-number" name="number" value="{{ $m->number }}"></div>
          <div class="sd-field"><label for="m{{ $m->id }}-factor">Umrechnungsfaktor</label><input id="m{{ $m->id }}-factor" name="factor" inputmode="decimal" value="{{ rtrim(rtrim(number_format((float) $m->factor, 4, ',', ''), '0'), ',') }}"></div>
          <div class="sd-field"><label for="m{{ $m->id }}-cal">Eichfrist bis (Jahr)</label><input id="m{{ $m->id }}-cal" name="calibrated_until" inputmode="numeric" value="{{ $m->calibrated_until }}" placeholder="{{ isset(Meter::CALIBRATION_YEARS[$m->kind]) ? 'z. B. '.((int) date('Y') + Meter::CALIBRATION_YEARS[$m->kind]) : 'nicht eichpflichtig' }}"></div>
        </div>
        <label style="display:flex;gap:.4rem;align-items:center;margin:.2rem 0 .6rem"><input type="checkbox" name="remote" value="1" @checked($m->remote) style="width:auto"> fernablesbar (Funk)</label>
        <button class="sd-btn sec small" type="submit">Zähler speichern</button>
      </form>
      <ul style="margin:.4rem 0 .4rem 1.2rem;padding:0">
        @forelse ($m->readings->sortByDesc('read_on') as $r)
          <li>{{ $r->read_on->format('d.m.Y') }}: {{ number_format((float) $r->value, 3, ',', '.') }} {{ Meter::UNITS[$m->kind] ?? '' }}
            <form class="sd-inline" method="POST" action="{{ route('nk.readings.destroy', [$property, $r]) }}" onsubmit="return confirm('Diesen Zählerstand löschen?')">@csrf @method('DELETE')<button class="sd-btn danger small" type="submit" style="padding:.1rem .4rem">×</button></form>
          </li>
        @empty
          <li class="sd-muted">noch kein Stand</li>
        @endforelse
      </ul>
      <form method="POST" action="{{ route('nk.meters.destroy', [$property, $m]) }}" onsubmit="return confirm('Diesen Zähler entfernen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Zähler entfernen</button></form>
    </details>
  @endforeach

  <h3 style="font-size:1rem">Zähler anlegen</h3>
  <form method="POST" action="{{ route('nk.meters.store', $property) }}">
    @csrf
    <div class="sd-grid">
      <div class="sd-field"><label for="f-unit">Einheit *</label>
        <select id="f-unit" name="unit_id" required><option value="">– wählen –</option>@foreach ($units as $u)<option value="{{ $u->id }}" @selected((string) old('unit_id') === (string) $u->id)>{{ $u->label }}</option>@endforeach</select>
      </div>
      <div class="sd-field"><label for="f-kind">Art *</label>
        <select id="f-kind" name="kind" required>@foreach (Meter::KINDS as $k => $label)<option value="{{ $k }}" @selected(old('kind') === $k)>{{ $label }}</option>@endforeach</select>
      </div>
      @include('partials.sd-field', ['name' => 'label', 'label' => 'Bezeichnung', 'hint' => 'z. B. Bad, Küche, Heizkörper Wohnzimmer'])
      @include('partials.sd-field', ['name' => 'number', 'label' => 'Zählernummer'])
      @include('partials.sd-field', ['name' => 'factor', 'label' => 'Umrechnungsfaktor', 'hint' => 'nur bei Heizkostenverteilern, sonst leer (= 1)', 'inputmode' => 'decimal'])
      @include('partials.sd-field', ['name' => 'calibrated_until', 'label' => 'Eichfrist bis (Jahr)', 'hint' => 'steht auf dem Zähler (Jahr der Eichung + 5 bzw. 6 Jahre)', 'inputmode' => 'numeric'])
    </div>
    <label style="display:flex;gap:.4rem;align-items:center;margin:.2rem 0 .8rem"><input type="checkbox" name="remote" value="1" @checked(old('remote')) style="width:auto"> fernablesbar (Funk)</label>
    <button class="sd-btn" type="submit">Zähler anlegen</button>
  </form>
</div>
@endsection
