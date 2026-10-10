@extends('layouts.app')
@section('title', 'Nebenkosten-Einstellungen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php use App\Models\NkCostKey; use App\Models\NkSetting; @endphp
<div class="sd-head">
  <h1>Nebenkosten: {{ $property->name }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('nk.meters', $property) }}">Zähler</a>
    <a class="sd-btn sec" href="{{ route('nk.index') }}">Zur Abrechnung</a>
  </div>
</div>

<form method="POST" action="{{ route('nk.settings.save', $property) }}">
  @csrf @method('PUT')
  <div class="sd-card">
    <h2>Heizung und Warmwasser</h2>
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-heating_mode">Heizkosten</label>
        <select id="f-heating_mode" name="heating_mode">
          @foreach (NkSetting::HEATING as $k => $label)
            <option value="{{ $k }}" @selected(old('heating_mode', $setting->heating_mode) === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="f-consumption_share">Anteil nach Verbrauch (Prozent)</label>
        <input id="f-consumption_share" type="number" name="consumption_share" min="50" max="70" value="{{ old('consumption_share', $setting->consumption_share) }}">
        <small class="sd-muted">Heizkostenverordnung: 50 bis 70 %, der Rest nach Wohnfläche. Gilt für Heizung und Warmwasser nach Zählern.</small>
      </div>
    </div>
    <p class="sd-muted" style="margin:0">Die Vorschläge in der Tabelle unten richten sich nach dieser Einstellung, solange du dort nichts anderes wählst.</p>
  </div>

  <div class="sd-card" id="co2">
    <h2>Brennstoff und CO2-Kosten</h2>
    <p class="sd-muted" style="margin-top:0">Bei Erdgas, Heizöl, Flüssiggas, Kohle und Fernwärme fallen CO2-Kosten an. Sie werden nach dem CO2-Kostenaufteilungsgesetz zwischen dir und den Mietern geteilt: Je schlechter das Gebäude (kg CO2 je m² und Jahr), desto mehr trägt der Vermieter. Die Werte stehen auf der Rechnung des Versorgers; du trägst sie in der jeweiligen Nebenkostenabrechnung ein. Bei Wärmepumpe, Holz/Pellets und Strom gibt es keine CO2-Kosten.@if ($setting->heating_mode === 'direkt') Zahlen die Mieter direkt (Etagenheizung), können sie den Vermieteranteil bei dir einfordern – siehe <a href="{{ route('co2.index') }}">CO2-Erstattung</a>.@endif</p>
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-fuel">Brennstoff / Wärmeversorgung</label>
        <select id="f-fuel" name="fuel">
          <option value="">– bitte wählen –</option>
          @foreach (NkSetting::FUELS as $k => $label)
            <option value="{{ $k }}" @selected(old('fuel', $setting->fuel) === $k)>{{ $label }}{{ in_array($k, NkSetting::CO2_FUELS, true) ? ' (mit CO2-Kosten)' : '' }}</option>
          @endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="f-building_use">Gebäudeart</label>
        <select id="f-building_use" name="building_use">
          @foreach (NkSetting::BUILDING_USE as $k => $label)
            <option value="{{ $k }}" @selected((string) old('building_use', $setting->building_use) === $k)>{{ $label }}</option>
          @endforeach
        </select>
        <small class="sd-muted">Gemischt genutzte Häuser richten sich nach der überwiegenden Nutzung.</small>
      </div>
      <div class="sd-field">
        <label for="f-co2_restriction">Einschränkung (§ 9 CO2KostAufG)</label>
        <select id="f-co2_restriction" name="co2_restriction">
          @foreach (NkSetting::RESTRICTIONS as $k => $label)
            <option value="{{ $k }}" @selected(old('co2_restriction', $setting->co2_restriction ?: 'keine') === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      @include('partials.sd-field', ['name' => 'co2_restriction_note', 'label' => 'Grund / Nachweis der Einschränkung', 'value' => $setting->co2_restriction_note, 'hint' => 'nur bei Einschränkung, z. B. „Bescheid Denkmalbehörde vom …“ – wird auf der Abrechnung genannt'])
    </div>
  </div>

  <div class="sd-card">
    <h2>Monatliche Verbrauchsinformation</h2>
    <p class="sd-muted" style="margin-top:0">Sobald fernablesbare Zähler eingebaut sind, bekommen die Mieter jeden Monat eine Übersicht ihres Verbrauchs (§ 6a Heizkostenverordnung). Dort steht auch ein Vergleich mit einem durchschnittlichen Nutzer. Den Wert dafür (kWh je m² und Jahr) findest du z. B. im Heizspiegel (heizspiegel.de) oder beim Messdienst; ohne Wert wird nur mit dem Durchschnitt im Haus verglichen.</p>
    <div class="sd-grid">
      @include('partials.sd-field', ['name' => 'benchmark_kwh', 'label' => 'Vergleichswert Heizung (kWh je m² und Jahr)', 'value' => $setting->benchmark_kwh !== null ? rtrim(rtrim(number_format((float) $setting->benchmark_kwh, 1, ',', ''), '0'), ',') : '', 'inputmode' => 'decimal', 'hint' => 'z. B. 130'])
    </div>
  </div>

  <div class="sd-card">
    <h2>Umlageschlüssel je Kostenart</h2>
    <p class="sd-muted" style="margin-top:0">Gilt für dieses Objekt. Nur umlagefähige Kostenarten (einstellbar unter „Kostenarten“) erscheinen hier. Belege, die einer bestimmten Einheit zugeordnet sind, gehen immer direkt an diese Einheit.@if ($hasCommercial) Beim <strong>Vorwegabzug Gewerbe</strong> trägt das Gewerbe zuerst den eingetragenen Prozentsatz (unter den Gewerbe-Einheiten nach Fläche verteilt), der Rest wird auf die Wohnungen verteilt.@endif</p>
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Kostenart</th><th>Schlüssel</th>@if ($hasCommercial)<th>Vorwegabzug Gewerbe %</th>@endif</tr></thead>
        <tbody>
          @foreach ($types as $t)
            @php($current = $keys->get($t->key)?->key_type ?? NkCostKey::defaultType($t->key, $setting->heating_mode))
            <tr>
              <td>{{ $t->label }}@unless ($t->active) <span class="sd-muted">(stillgelegt)</span>@endunless</td>
              <td>
                <select name="key[{{ $t->key }}]" aria-label="Schlüssel {{ $t->label }}" style="width:auto;padding:.4rem">
                  @foreach (NkCostKey::TYPES as $k => $label)
                    <option value="{{ $k }}" @selected(old('key.'.$t->key, $current) === $k)>{{ $label }}</option>
                  @endforeach
                </select>
              </td>
              @if ($hasCommercial)
                <td><input type="text" name="percent[{{ $t->key }}]" inputmode="decimal" style="width:5rem;padding:.4rem" value="{{ old('percent.'.$t->key, $keys->get($t->key)?->commercial_percent !== null ? rtrim(rtrim(number_format((float) $keys->get($t->key)->commercial_percent, 2, ',', ''), '0'), ',') : '') }}" aria-label="Vorwegabzug {{ $t->label }}"></td>
              @endif
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  <button class="sd-btn" type="submit">Speichern</button>
</form>
@endsection
