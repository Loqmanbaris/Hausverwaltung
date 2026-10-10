@extends('layouts.app')
@section('title', 'Nebenkostenabrechnung')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Kostenarten;
  use App\Support\Nk\NkCalculator;
  $final = $settlement->isFinal();
  $eur = fn ($cents) => Money::format($cents / 100);
  $isAdmin = auth()->user()->isAdmin();
@endphp
<div class="sd-head">
  <h1>Nebenkosten {{ $settlement->property?->name }}, {{ $settlement->periodLabel() }}</h1>
  <div class="sd-actions">
    <a class="sd-btn" href="{{ route('nk.print', $settlement) }}" target="_blank" rel="noopener">Alle drucken</a>
    @if ($settlement->isFinal())<a class="sd-btn" href="{{ route('mail.compose', ['art' => 'nk', 'ids' => $settlement->id]) }}">Alle per E-Mail senden</a>@endif
    <a class="sd-btn sec" href="{{ route('nk.settings', $settlement->property_id) }}">Einstellungen</a>
    <a class="sd-btn sec" href="{{ route('nk.meters', $settlement->property_id) }}">Zähler</a>
    <a class="sd-btn sec" href="{{ route('nk.index') }}">Alle Abrechnungen</a>
  </div>
</div>

@if ($final)
  <div class="sd-ok">Abgeschlossen am {{ $settlement->finalized_at?->timezone('Europe/Berlin')->format('d.m.Y') }}, Datum der Abrechnung {{ $settlement->issued_on?->format('d.m.Y') }}. Die Ergebnisse stehen im Mieterkonto. Änderungen an Belegen wirken sich nicht mehr aus.</div>
@else
  <div class="rt-warn">Entwurf: Die Zahlen werden bei jedem Aufruf neu berechnet. Erst „Abschließen“ bucht die Ergebnisse ins Mieterkonto.</div>
@endif

@if (count($result['warnings']) > 0)
  <div class="sd-err"><strong>Bitte prüfen:</strong><ul>@foreach ($result['warnings'] as $w)<li>{{ $w }}</li>@endforeach</ul></div>
@endif

<div class="rt-stats">
  <div class="rt-stat"><strong>{{ $eur($result['totals']['cost_cents']) }}</strong><span>umlagefähige Kosten</span></div>
  <div class="rt-stat"><strong>{{ $eur($result['totals']['tenant_cents']) }}</strong><span>davon Mieter</span></div>
  <div class="rt-stat"><strong>{{ $eur($result['owner']['cents']) }}</strong><span>Eigentümer (Leerstand, Montage)</span></div>
  <div class="rt-stat"><strong>{{ $eur($result['totals']['prepaid_cents']) }}</strong><span>Vorauszahlungen</span></div>
</div>

<div class="sd-card">
  <h2>Ergebnis je Mieter</h2>
  @if (count($result['leases']) === 0)
    <p class="sd-muted" style="margin:0">Im Zeitraum gibt es keine Mietverträge (Montage-Wohnungen werden nicht abgerechnet).</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Mieter</th><th>Zeitraum</th><th class="rt-num">Kosten</th><th class="rt-num">Vorauszahlung</th><th class="rt-num">Ergebnis</th><th></th></tr></thead>
        <tbody>
          @foreach ($result['leases'] as $id => $l)
            <tr>
              <td><a href="{{ route('leases.show', $id) }}">{{ $l['tenant'] }}</a><br><span class="sd-muted">{{ $l['unit'] }}@if ($l['vat_rate'] > 0) · Gewerbe mit USt @endif</span></td>
              <td data-l="Zeitraum">{{ \Carbon\Carbon::parse($l['from'])->format('d.m.') }}–{{ \Carbon\Carbon::parse($l['to'])->format('d.m.Y') }}<br><span class="sd-muted">{{ $l['days'] }} Tage</span></td>
              <td class="rt-num" data-l="Kosten">{{ $eur($l['cost_cents']) }}</td>
              <td class="rt-num" data-l="Vorauszahlung">{{ $eur($l['prepaid_cents']) }}</td>
              <td class="rt-num" data-l="Ergebnis">@if ($l['result_cents'] > 0)<span class="rt-late">Nachzahlung {{ $eur($l['result_cents']) }}</span>@elseif ($l['result_cents'] < 0)<span class="rt-ok">Guthaben {{ $eur(-$l['result_cents']) }}</span>@else ausgeglichen @endif</td>
              <td style="white-space:nowrap"><a class="sd-btn sec small" href="{{ route('nk.print', ['settlement' => $settlement, 'vertrag' => $id]) }}" target="_blank" rel="noopener">Drucken</a>@if ($settlement->isFinal()) <a class="sd-btn sec small" href="{{ route('mail.compose', ['art' => 'nk', 'ids' => $settlement->id, 'vertrag' => $id]) }}">E-Mail</a>@endif</td>
            </tr>
            @if ($final && $l['suggest']['active'])
              <tr><td colspan="6" style="background:#f7f9fb">
                <form class="rt-pay" method="POST" action="{{ route('nk.suggest', [$settlement, $id]) }}">
                  @csrf
                  <span>Neue Vorauszahlung (bisher NK {{ Money::format($l['suggest']['current_operating']) }}, HK {{ Money::format($l['suggest']['current_heating']) }}{{ $l['vat_rate'] > 0 ? ', netto' : '' }}): ab</span>
                  <input type="month" name="valid_from" value="{{ date('Y-m', strtotime('first day of next month')) }}" required aria-label="ab Monat">
                  <span>NK</span><input type="text" name="operating" inputmode="decimal" value="{{ Money::input($l['suggest']['operating']) }}" style="width:6rem" aria-label="Nebenkosten">
                  <span>HK</span><input type="text" name="heating" inputmode="decimal" value="{{ Money::input($l['suggest']['heating']) }}" style="width:6rem" aria-label="Heizkosten">
                  <button class="sd-btn sec small" type="submit">Übernehmen</button>
                </form>
              </td></tr>
            @endif
          @endforeach
        </tbody>
      </table>
    </div>
    @if ($final)<p class="sd-muted" style="margin-bottom:0">Der Vorschlag für die neue Vorauszahlung ist ein Zwölftel der Kosten (aufgerundet). Bei Gewerbe mit Umsatzsteuer bitte netto eintragen. Die Vorauszahlung darf nach einer Abrechnung angemessen angepasst werden (§ 560 Abs. 4 BGB); das Schreiben an den Mieter ist die Abrechnung selbst.</p>@endif
  @endif
</div>

<div class="sd-card">
  <h2>Verteilung je Kostenart</h2>
  <div class="sd-table-wrap">
    <table class="sd rt-stack">
      <thead><tr><th>Kostenart</th><th>Schlüssel</th><th class="rt-num">Gesamt</th><th class="rt-num">Bezugsgröße gesamt</th></tr></thead>
      <tbody>
        @foreach ($result['lines'] as $line)
          <tr>
            <td>{{ $line['label'] }}@if ($line['part'])<br><span class="sd-muted">{{ $line['part'] }}</span>@endif @if ($line['commercial_percent'])<br><span class="sd-muted">Vorwegabzug Gewerbe {{ rtrim(rtrim(number_format((float) $line['commercial_percent'], 2, ',', ''), '0'), ',') }} %</span>@endif</td>
            <td data-l="Schlüssel">{{ ['flaeche' => 'Fläche', 'einheiten' => 'je Einheit', 'personen' => 'Personen', 'wasser' => 'Wasserverbrauch', 'heizung_verbrauch' => 'Heizverbrauch', 'warmwasser_verbrauch' => 'Warmwasserverbrauch', 'direkt' => 'direkt'][$line['basis']] ?? $line['basis'] }}</td>
            <td class="rt-num" data-l="Gesamt">{{ $eur($line['cents']) }}</td>
            <td class="rt-num" data-l="Bezugsgröße">{{ NkCalculator::formatBase($line['basis'], (float) $line['base_total']) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

@php($co2 = $result['co2'] ?? null)
@if ($co2 !== null || (! $final && in_array($result['settings']['fuel'] ?? null, \App\Models\NkSetting::CO2_FUELS, true)))
  <div class="sd-card" id="co2">
    <h2>CO2-Kosten</h2>
    <p class="sd-muted" style="margin-top:0">Brennstoff: {{ $co2['fuel'] ?? '–' }}. Die CO2-Menge (kg) und die CO2-Kosten (€, brutto) stehen auf der Rechnung des Versorgers – bei mehreren Lieferungen oder Rechnungen im Zeitraum bitte zusammenzählen. Der Anteil des Vermieters wird wie die Heizkosten auf die Mieter verteilt und von ihren Kosten abgezogen.</p>
    @if (! $final)
      <form method="POST" action="{{ route('nk.co2', $settlement) }}">
        @csrf @method('PUT')
        <div class="sd-grid">
          @include('partials.sd-field', ['name' => 'co2_kg', 'label' => 'CO2-Menge (kg)', 'value' => $settlement->co2_kg !== null ? number_format((float) $settlement->co2_kg, 1, ',', '.') : '', 'inputmode' => 'decimal'])
          @include('partials.sd-field', ['name' => 'co2_cost', 'label' => 'CO2-Kosten (€ brutto)', 'value' => $settlement->co2_cost !== null ? Money::input($settlement->co2_cost) : '', 'inputmode' => 'decimal'])
          @include('partials.sd-field', ['name' => 'co2_area', 'label' => 'Wohnfläche des Gebäudes (m²)', 'value' => $settlement->co2_area !== null ? number_format((float) $settlement->co2_area, 2, ',', '.') : '', 'inputmode' => 'decimal', 'hint' => 'leer = Summe der Einheiten ('.number_format((float) ($co2['area'] ?? 0), 2, ',', '.').' m²)'])
        </div>
        <button class="sd-btn sec small" type="submit">CO2-Angaben speichern</button>
      </form>
    @endif
    @if ($co2 !== null && ! ($co2['missing'] ?? true))
      <table class="sd" style="margin-top:.8rem;max-width:40rem">
        <tbody>
          <tr><td>CO2-Ausstoß</td><td class="rt-num">{{ number_format($co2['kg'], 1, ',', '.') }} kg</td></tr>
          <tr><td>Wohnfläche</td><td class="rt-num">{{ number_format($co2['area'], 2, ',', '.') }} m²</td></tr>
          <tr><td>je m² und Jahr</td><td class="rt-num">{{ number_format($co2['kg_per_sqm'], 1, ',', '.') }} kg</td></tr>
          <tr><td>Einstufung</td><td class="rt-num">{{ $co2['step_label'] }}</td></tr>
          <tr><td>Anteil Mieter / Vermieter</td><td class="rt-num">{{ \App\Support\Nk\Co2Split::pct($co2['tenant_pct']) }} / {{ \App\Support\Nk\Co2Split::pct($co2['landlord_pct']) }}@if ($co2['restriction'] !== 'keine')<br><span class="sd-muted">gekürzt nach § 9: {{ $co2['restriction_note'] }}</span>@endif</td></tr>
          <tr><td>CO2-Kosten gesamt</td><td class="rt-num">{{ $eur($co2['cost_cents']) }}</td></tr>
          <tr><td><strong>davon trägt der Vermieter</strong></td><td class="rt-num"><strong>{{ $eur($co2['landlord_cents']) }}</strong></td></tr>
        </tbody>
      </table>
    @endif
  </div>
@endif

<div class="sd-card" id="kosten">
  <h2>Kostenpositionen</h2>
  @unless ($final)
    <form method="POST" action="{{ route('nk.refresh', $settlement) }}" class="sd-inline">@csrf<button class="sd-btn sec small" type="submit">Neue Belege übernehmen</button></form>
    <span class="sd-muted">Belege des Objekts mit Datum im Zeitraum und umlagefähiger Kostenart.</span>
  @endunless
  <div class="sd-table-wrap" style="margin-top:.6rem">
    <table class="sd rt-stack">
      <thead><tr><th>Kostenart</th><th>Herkunft</th><th class="rt-num">Betrag</th><th></th></tr></thead>
      <tbody>
        @forelse ($costs as $c)
          <tr @if (! $c->included) style="opacity:.55" @endif>
            <td>{{ Kostenarten::label($c->cost_key) }}@if ($c->unit)<br><span class="sd-muted">nur {{ $c->unit->label }}</span>@endif</td>
            <td data-l="Herkunft">@if ($c->receipt)<a href="{{ route('receipts.show', $c->receipt_id) }}">Beleg Nr. {{ $c->receipt_id }}</a>, {{ $c->receipt->receipt_date?->format('d.m.Y') }}<br>@endif<span class="sd-muted">{{ $c->label }}</span></td>
            <td class="rt-num" data-l="Betrag">{{ Money::format($c->amount) }}</td>
            <td>
              @unless ($final)
                <form class="sd-inline" method="POST" action="{{ route('nk.costs.toggle', [$settlement, $c]) }}">@csrf<button class="sd-btn sec small" type="submit">{{ $c->included ? 'Nicht abrechnen' : 'Wieder abrechnen' }}</button></form>
                @if ($c->receipt_id === null)
                  <form class="sd-inline" method="POST" action="{{ route('nk.costs.destroy', [$settlement, $c]) }}">@csrf @method('DELETE')<button class="sd-btn danger small" type="submit">Entfernen</button></form>
                @endif
              @endunless
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="sd-muted">Keine Kosten. Belege mit umlagefähiger Kostenart und Objekt „{{ $settlement->property?->name }}“ erfassen oder unten eine Position hinzufügen.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
  @unless ($final)
    <details style="margin-top:.8rem" @if ($errors->has('amount') || $errors->has('label')) open @endif>
      <summary><strong>Position von Hand hinzufügen</strong> <span class="sd-muted">(z. B. Abgrenzung, Kosten ohne Beleg)</span></summary>
      <form method="POST" action="{{ route('nk.costs.store', $settlement) }}" style="margin-top:.6rem">
        @csrf
        <div class="sd-grid">
          @include('partials.sd-select', ['name' => 'cost_key', 'label' => 'Kostenart', 'required' => true, 'options' => $allocatable])
          @include('partials.sd-field', ['name' => 'amount', 'label' => 'Betrag', 'required' => true, 'inputmode' => 'decimal', 'hint' => 'Minus für eine Minderung'])
          @include('partials.sd-field', ['name' => 'label', 'label' => 'Beschreibung', 'required' => true])
          @include('partials.sd-select', ['name' => 'unit_id', 'label' => 'Nur für Einheit (optional)', 'options' => $settlement->property->units->sortBy('label')->pluck('label', 'id')->all()])
        </div>
        <button class="sd-btn" type="submit">Hinzufügen</button>
      </form>
    </details>
  @endunless
</div>

<div class="sd-card">
  <h2>Text auf der Abrechnung</h2>
  @if ($final)
    <p style="white-space:pre-line;margin:0">{{ $settlement->note ?: '–' }}</p>
  @else
    <form method="POST" action="{{ route('nk.note', $settlement) }}">
      @csrf @method('PUT')
      @include('partials.sd-textarea', ['name' => 'note', 'label' => 'Zusätzlicher Text (optional, z. B. Bankverbindung oder Hinweis auf Belegeinsicht)', 'value' => $settlement->note])
      <button class="sd-btn sec small" type="submit">Text speichern</button>
    </form>
  @endif
</div>

@if (! $final)
  <div class="sd-card">
    <h2>Abschließen</h2>
    <p class="sd-muted" style="margin-top:0">Erst abschließen, wenn alles stimmt und die Abrechnungen gedruckt werden. Nachzahlungen werden als offene Posten (fällig 30 Tage nach dem Datum der Abrechnung) ins Mieterkonto gebucht, Guthaben als Gutschrift, die mit der nächsten Miete verrechnet wird.</p>
    <form method="POST" action="{{ route('nk.finalize', $settlement) }}" class="rt-filter" style="margin:0" onsubmit="return confirm('Abrechnung jetzt abschließen und ins Mieterkonto buchen?')">
      @csrf
      <label style="margin:0">Datum der Abrechnung <input type="date" name="issued_on" value="{{ date('Y-m-d') }}" required></label>
      <button class="sd-btn" type="submit">Abschließen</button>
    </form>
    <form method="POST" action="{{ route('nk.destroy', $settlement) }}" style="margin-top:1rem" onsubmit="return confirm('Diesen Entwurf löschen?')">
      @csrf @method('DELETE')
      <button class="sd-btn danger small" type="submit">Entwurf löschen</button>
    </form>
  </div>
@elseif ($isAdmin)
  <div class="sd-card">
    <h2>Wieder öffnen</h2>
    <p class="sd-muted" style="margin-top:0">Entfernt die Buchungen dieser Abrechnung aus den Mieterkonten. Zahlungen der Mieter bleiben erhalten. Nur nötig, wenn die Abrechnung korrigiert werden muss.</p>
    <form method="POST" action="{{ route('nk.reopen', $settlement) }}" onsubmit="return confirm('Abrechnung wirklich wieder öffnen?')">@csrf<button class="sd-btn danger small" type="submit">Wieder öffnen</button></form>
  </div>
@endif
@endsection
