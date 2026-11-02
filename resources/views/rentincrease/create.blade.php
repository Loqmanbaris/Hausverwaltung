@extends('layouts.app')
@section('title', 'Mieterhöhung vorbereiten')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Models\RentIncrease;
  use App\Support\Money;
  use App\Support\Rent\Erhoehung\Regeln;
  use App\Support\Rent\Erhoehung\Verfahren;
  $v = fn ($k, $d = '') => old($k, $values[$k] ?? $d);
  $cur = $lease->currentRent();
  $area = $lease->unit?->area_sqm;
  $input = 'padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;width:100%';
@endphp
<div class="sd-head">
  <h1>Mieterhöhung vorbereiten</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('leases.show', $lease) }}">Abbrechen</a></div>
</div>
<p class="sd-muted">{{ $lease->title() }} · Kaltmiete jetzt {{ Money::format($cur?->rent_cold) }}@if ($area) · {{ Regeln::num((float) $area) }} m² · {{ Regeln::eur((int) round((float) $cur?->rent_cold / (float) $area * 100)) }} je m²@endif</p>

@if ($error)<div class="sd-err">{{ $error }}</div>@endif

<div class="sd-card" style="padding:.6rem .8rem">
  <div style="display:flex;flex-wrap:wrap;gap:.4rem">
    @foreach (RentIncrease::KINDS as $k => $label)
      <a class="sd-btn small {{ $k === $kind ? '' : 'sec' }}" href="{{ route('rent_increase.create', ['lease' => $lease, 'art' => $k]) }}">{{ RentIncrease::SHORT[$k] }}</a>
    @endforeach
  </div>
  <p class="sd-muted" style="margin:.5rem 0 0">{{ RentIncrease::KINDS[$kind] }}</p>
</div>

<form method="POST" action="{{ route('rent_increase.preview', $lease) }}">
  @csrf
  <input type="hidden" name="art" value="{{ $kind }}">
  <div class="sd-card">
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-notice_on">Datum des Schreibens (Zugang) *</label>
        <input id="f-notice_on" name="notice_on" type="date" required value="{{ $v('notice_on', date('Y-m-d')) }}">
        <small class="sd-muted">Daraus ergeben sich Fristen und Wirkungstag. Gilt der Tag, an dem der Mieter das Schreiben erhält.</small>
      </div>
    </div>

    @if ($kind === 'vergleich')
      <div class="sd-grid">
        @include('partials.sd-field', ['name' => 'new_cold', 'label' => 'Neue Kaltmiete (€ im Monat)', 'inputmode' => 'decimal', 'value' => $v('new_cold'), 'required' => true, 'hint' => $calc && ($calc['max_allowed'] ?? null) ? 'Höchstens zulässig: '.Regeln::eur($calc['max_allowed']) : 'Nach „Prüfen“ siehst du den Höchstbetrag.'])
        <div class="sd-field">
          <label for="f-cap_percent">Kappungsgrenze *</label>
          <select id="f-cap_percent" name="cap_percent">
            <option value="20" @selected((int) $v('cap_percent', 20) === 20)>20 % in drei Jahren (Normalfall)</option>
            <option value="15" @selected((int) $v('cap_percent', 20) === 15)>15 % (angespannter Wohnungsmarkt)</option>
          </select>
          <small class="sd-muted">15 % gilt, wenn das Land die Gemeinde per Verordnung als angespannt eingestuft hat.</small>
        </div>
      </div>
      <h2 style="margin-top:1rem">Begründung (§ 558a BGB)</h2>
      <div class="sd-grid">
        <div class="sd-field">
          <label for="f-basis_type">Begründungsmittel *</label>
          <select id="f-basis_type" name="basis_type">
            @foreach (Verfahren::BASIS_TYPES as $k => $label)
              <option value="{{ $k }}" @selected($v('basis_type', 'mietspiegel') === $k)>{{ $label }}</option>
            @endforeach
          </select>
        </div>
        @include('partials.sd-field', ['name' => 'source_name', 'label' => 'Name und Stand (z. B. „Mietspiegel Musterstadt 2025“)', 'value' => $v('source_name'), 'required' => true])
      </div>
      <div class="sd-grid">
        @include('partials.sd-field', ['name' => 'source_detail', 'label' => 'Rasterfeld und Merkmale der Wohnung', 'value' => $v('source_detail'), 'hint' => 'z. B. „Baujahr 1975–1989, 50–70 m², mittlere Wohnlage“'])
        @include('partials.sd-field', ['name' => 'comparable_sqm', 'label' => 'Ortsübliche Vergleichsmiete (€ je m²)', 'inputmode' => 'decimal', 'value' => $v('comparable_sqm'), 'hint' => 'Der Wert, bis zu dem erhöht werden darf (Spanne und Einordnung).'])
      </div>
      <div class="sd-grid">
        @include('partials.sd-field', ['name' => 'range_from', 'label' => 'Spanne von (€ je m²)', 'inputmode' => 'decimal', 'value' => $v('range_from')])
        @include('partials.sd-field', ['name' => 'range_to', 'label' => 'Spanne bis (€ je m²)', 'inputmode' => 'decimal', 'value' => $v('range_to')])
      </div>
      <div class="sd-field">
        <label for="f-comparables">Vergleichswohnungen oder Gutachten (nur wenn kein Mietspiegel)</label>
        <textarea id="f-comparables" name="comparables" rows="3" maxlength="3000" style="{{ $input }}">{{ $v('comparables') }}</textarea>
        <small class="sd-muted">Bei Vergleichswohnungen: drei Wohnungen mit Anschrift oder Lage, Größe und Miete.</small>
      </div>

    @elseif ($kind === 'modernisierung')
      <div class="sd-field">
        <label for="f-measure">Modernisierungsmaßnahme *</label>
        <textarea id="f-measure" name="measure" rows="3" maxlength="3000" required style="{{ $input }}">{{ $v('measure') }}</textarea>
        <small class="sd-muted">z. B. „Einbau neuer Fenster mit Dreifachverglasung, abgeschlossen am 15.09.2026“.</small>
      </div>
      <div class="sd-grid">
        @include('partials.sd-field', ['name' => 'costs', 'label' => 'Kosten für diese Wohnung (€, brutto)', 'inputmode' => 'decimal', 'value' => $v('costs'), 'required' => true, 'hint' => 'Der auf die Wohnung entfallende Anteil der Gesamtkosten.'])
        @include('partials.sd-field', ['name' => 'upkeep_percent', 'label' => 'Anteil für ohnehin nötige Instandhaltung (%)', 'inputmode' => 'decimal', 'value' => $v('upkeep_percent', '30'), 'hint' => 'Wird von den Kosten abgezogen. Vereinfachtes Verfahren: immer 30 %. Geförderte Heizung (nicht Gas oder Öl): immer 15 %. Sonst die tatsächlich ersparten Kosten.'])
        @include('partials.sd-field', ['name' => 'subsidies', 'label' => 'Fördermittel und Zuschüsse (€)', 'inputmode' => 'decimal', 'value' => $v('subsidies'), 'hint' => 'In Anspruch genommene Drittmittel werden abgezogen (§ 559a BGB).'])
      </div>
      <div class="sd-grid">
        <div class="sd-field">
          <label for="f-heating_type">Einbau oder Aufstellung einer Heizungsanlage (§§ 559e, 559f BGB)</label>
          <select id="f-heating_type" name="heating_type" style="{{ $input }}">
            <option value="">Keine Heizungsanlage</option>
            <option value="waermepumpe" @selected($v('heating_type') === 'waermepumpe')>Wärmepumpe</option>
            <option value="erneuerbar" @selected($v('heating_type') === 'erneuerbar')>Fernwärme, Biomasse oder andere erneuerbare Anlage</option>
            <option value="fossil" @selected($v('heating_type') === 'fossil')>Gas- oder Ölheizung (§ 43 GModG)</option>
          </select>
          <small class="sd-muted">Mit Förderung: 10 % statt 8 %, höchstens 0,50 € je m² in sechs Jahren, pauschal 15 % Erhaltungsanteil (nicht bei Gas oder Öl).</small>
        </div>
        <div class="sd-field">
          <label for="f-pump_proof">Nur bei Wärmepumpe: Nachweis Jahresarbeitszahl</label>
          <select id="f-pump_proof" name="pump_proof" style="{{ $input }}">
            <option value="">Kein Nachweis (nur die halben Kosten zählen)</option>
            <option value="nachweis" @selected($v('pump_proof') === 'nachweis')>Nachweis von mindestens 2,5 durch Fachunternehmer liegt vor</option>
            <option value="ausnahme" @selected($v('pump_proof') === 'ausnahme')>Ausnahme: Gebäude nach 1996, WSVO 1994, Sanierung nach § 38 GModG oder höchstens 55 °C Vorlauf</option>
          </select>
        </div>
      </div>
      <div style="display:grid;gap:.5rem;margin:.6rem 0">
        <label><input type="checkbox" name="simplified" value="1" @checked($v('simplified'))> Vereinfachtes Verfahren (§ 559c BGB, Kosten bis 10.000 €, pauschal 30 % Instandhaltung, kein Härteeinwand)</label>
        <label><input type="checkbox" name="delayed" value="1" @checked($v('delayed'))> Die Maßnahme wurde nicht oder zu spät angekündigt, oder die Erhöhung übersteigt die Ankündigung um mehr als 10 % (Wirkung sechs Monate später)</label>
      </div>
      <div class="sd-grid">
        <div class="sd-field"><label for="f-announced_on">Angekündigt am (freiwillig)</label><input id="f-announced_on" name="announced_on" type="date" value="{{ $v('announced_on') }}"></div>
        <div class="sd-field"><label for="f-completed_on">Abgeschlossen am (freiwillig)</label><input id="f-completed_on" name="completed_on" type="date" value="{{ $v('completed_on') }}"></div>
      </div>
      @if ($prior && $prior['all'] > 0)
        <p class="sd-muted">Bisherige Modernisierungszuschläge der letzten sechs Jahre: {{ Regeln::eur($prior['all']) }} im Monat @if ($prior['heating'] > 0), davon Heizung {{ Regeln::eur($prior['heating']) }}@endif. Sie zählen auf die Kappungsgrenze.</p>
      @endif

    @elseif ($kind === 'index')
      <p class="sd-muted" style="margin-top:0">Ausgangswert im Vertrag: {{ $lease->index_base_value !== null ? number_format((float) $lease->index_base_value, 1, ',', '') : 'fehlt' }}{{ $lease->index_base_month ? ' ('.$lease->index_base_month->format('m/Y').')' : '' }}.@if ($indexMode !== 'keine') Begrenzung eingestellt: {{ Regeln::INDEX_CAP_MODES[$indexMode] }}.@endif</p>
      <div class="sd-grid">
        @include('partials.sd-field', ['name' => 'new_index', 'label' => 'Neuer Indexwert (Verbraucherpreisindex)', 'inputmode' => 'decimal', 'value' => $v('new_index'), 'required' => true, 'hint' => 'Zu finden beim Statistischen Bundesamt (Destatis), Basisjahr wie im Ausgangswert.'])
        <div class="sd-field">
          <label for="f-index_month">Monat des neuen Indexwerts *</label>
          <input id="f-index_month" name="index_month" type="month" required value="{{ $v('index_month') }}">
        </div>
      </div>

    @elseif ($kind === 'staffel')
      <div class="sd-field">
        <label for="f-lease_rent_id">Staffel *</label>
        <select id="f-lease_rent_id" name="lease_rent_id" required>
          <option value="">– bitte wählen –</option>
          @foreach ($staffeln as $s)
            <option value="{{ $s->id }}" @selected((int) $v('lease_rent_id') === $s->id)>ab {{ $s->valid_from->format('d.m.Y') }}: Kaltmiete {{ Money::format($s->rent_cold) }}</option>
          @endforeach
        </select>
        @if ($staffeln->isEmpty())<small class="sd-muted">Beim Mietvertrag sind keine Staffeln eingetragen. Lege sie dort unter „Mietänderung“ mit der Art „Staffel“ an.</small>@endif
      </div>

    @else
      <div class="sd-grid">
        @include('partials.sd-field', ['name' => 'new_cold', 'label' => 'Neue Kaltmiete (€ im Monat)', 'inputmode' => 'decimal', 'value' => $v('new_cold'), 'required' => true])
        <div class="sd-field">
          <label for="f-effective_from">Gilt ab (Monatserster) *</label>
          <input id="f-effective_from" name="effective_from" type="date" required value="{{ $v('effective_from') }}">
        </div>
      </div>
    @endif

    <div class="sd-field">
      <label for="f-note">Zusatztext im Schreiben (freiwillig)</label>
      <textarea id="f-note" name="note" rows="2" maxlength="3000" style="{{ $input }}">{{ $v('note') }}</textarea>
    </div>
  </div>
  <button class="sd-btn" type="submit">Prüfen und berechnen</button>
</form>

@if ($calc)
  <div class="sd-card" style="margin-top:1rem" id="ergebnis">
    <h2>Ergebnis der Prüfung</h2>
    @if ($calc['new_cold'] !== null)
      <dl class="sd-dl">
        <dt>Kaltmiete bisher</dt><dd>{{ Regeln::eur($calc['old_cold']) }}</dd>
        <dt>Kaltmiete neu</dt><dd><strong>{{ Regeln::eur($calc['new_cold']) }}</strong> ({{ $calc['new_cold'] >= $calc['old_cold'] ? '+' : '' }}{{ Regeln::eur($calc['new_cold'] - $calc['old_cold']) }}@if ($calc['old_cold'] > 0), {{ number_format(($calc['new_cold'] / $calc['old_cold'] - 1) * 100, 2, ',', '') }} %@endif)</dd>
        @if ($calc['area'])<dt>Je m²</dt><dd>{{ Regeln::eur((int) round($calc['old_cold'] / $calc['area'])) }} → {{ Regeln::eur((int) round($calc['new_cold'] / $calc['area'])) }}</dd>@endif
        @if ($calc['effective'])<dt>Gilt ab</dt><dd><strong>{{ Regeln::de($calc['effective']) }}</strong></dd>@endif
        @if (! empty($calc['timeline']['consent_until']))
          <dt>Zustimmung bis</dt><dd>{{ Regeln::de($calc['timeline']['consent_until']) }}</dd>
          <dt>Klage auf Zustimmung bis</dt><dd>{{ Regeln::de($calc['timeline']['suit_until']) }}</dd>
        @endif
        @if (! empty($calc['data']['calc']))
          @php($mc = $calc['data']['calc'])
          <dt>Rechenweg</dt><dd>Kosten {{ Regeln::eur($mc['costs']) }}@if (! empty($mc['halved'])) − Hälfte nach § 559f BGB {{ Regeln::eur($mc['halved_amount']) }}@endif − Instandhaltung {{ Regeln::eur($mc['upkeep']) }} − Förderung {{ Regeln::eur($mc['subsidies']) }} = {{ Regeln::eur($mc['base']) }}; × {{ $mc['percent'] }} % = {{ Regeln::eur($mc['yearly']) }} im Jahr = {{ Regeln::eur($mc['monthly']) }} im Monat{{ $mc['limited'] ? ', begrenzt auf '.Regeln::eur($mc['allowed']) : '' }}</dd>
        @endif
      </dl>
    @endif
    @include('rentincrease._checks', ['checks' => $calc['checks']])
    @if ($calc['ok'])
      <form method="POST" action="{{ route('rent_increase.store', $lease) }}" style="margin-top:1rem">
        @csrf
        <input type="hidden" name="art" value="{{ $kind }}">
        @foreach ($values as $k => $val)
          @continue(in_array($k, ['art', '_token'], true) || is_array($val))
          <input type="hidden" name="{{ $k }}" value="{{ $val }}">
        @endforeach
        <button class="sd-btn" type="submit">Als Entwurf speichern</button>
        <span class="sd-muted">Danach siehst du das Schreiben und kannst es drucken oder per E-Mail senden.</span>
      </form>
    @else
      <p class="sd-muted" style="margin:1rem 0 0">Bitte die rot markierten Punkte beheben und noch einmal prüfen.</p>
    @endif
  </div>
@endif
@endsection
