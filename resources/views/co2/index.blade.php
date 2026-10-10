@extends('layouts.app')
@section('title', 'CO2-Erstattung')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Nk\Co2Split;
@endphp
<div class="sd-head">
  <h1>CO2-Erstattung an Mieter</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('nk.index') }}">Nebenkosten</a>
    <a class="sd-btn sec" href="{{ route('consumption.index') }}">Zähler und Verbrauch</a>
  </div>
</div>
<p class="sd-muted">Mieter mit eigenem Gas- oder Wärmevertrag (z. B. Gasetagenheizung) bezahlen die CO2-Kosten zuerst selbst. Den Anteil des Vermieters können sie innerhalb von 12 Monaten nach der Rechnung ihres Versorgers in Textform bei dir einfordern (§ 6 CO2KostAufG). Du kannst ihn mit der nächsten Abrechnung verrechnen oder innerhalb von 12 Monaten erstatten. Hängt am selben Gaszähler auch ein Gasherd, wird der Anspruch um 5 % gekürzt.</p>

<div class="sd-card">
  <h2>Anspruch berechnen und erfassen</h2>
  <form method="POST" action="{{ route('co2.store') }}">
    @csrf
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-lease_id">Mietvertrag *</label>
        <select id="f-lease_id" name="lease_id" required>
          <option value="">– wählen –</option>
          @foreach ($leases as $l)
            <option value="{{ $l->id }}" @selected((string) old('lease_id', $selected) === (string) $l->id)>{{ $l->unit?->property?->name }} · {{ $l->unit?->label }} – {{ $l->tenant?->display_name }}{{ in_array((int) $l->unit?->property_id, $direct, true) ? ' (Etagenheizung)' : '' }}{{ $l->unit?->area_sqm ? ', '.number_format((float) $l->unit->area_sqm, 2, ',', '.').' m²' : '' }}</option>
          @endforeach
        </select>
      </div>
      @include('partials.sd-field', ['name' => 'period_from', 'label' => 'Rechnung des Versorgers von', 'type' => 'date', 'required' => true])
      @include('partials.sd-field', ['name' => 'period_to', 'label' => 'bis', 'type' => 'date', 'required' => true])
      @include('partials.sd-field', ['name' => 'co2_kg', 'label' => 'CO2-Menge (kg)', 'required' => true, 'inputmode' => 'decimal', 'hint' => 'steht auf der Rechnung'])
      @include('partials.sd-field', ['name' => 'co2_cost', 'label' => 'CO2-Kosten (€ brutto)', 'required' => true, 'inputmode' => 'decimal'])
      @include('partials.sd-field', ['name' => 'area', 'label' => 'Wohnfläche (m²)', 'inputmode' => 'decimal', 'hint' => 'leer = Fläche der Einheit'])
      @include('partials.sd-field', ['name' => 'claimed_on', 'label' => 'Vom Mieter geltend gemacht am', 'type' => 'date', 'value' => date('Y-m-d')])
      @include('partials.sd-field', ['name' => 'note', 'label' => 'Notiz'])
    </div>
    <label style="display:flex;gap:.4rem;align-items:center;margin:.2rem 0 .8rem"><input type="checkbox" name="gas_stove" value="1" @checked(old('gas_stove')) style="width:auto"> Gasherd am selben Zähler (Anspruch −5 %)</label>
    <button class="sd-btn" type="submit">Berechnen und speichern</button>
  </form>
  <details style="margin-top:.8rem">
    <summary>Stufenmodell für Wohngebäude</summary>
    <table class="sd" style="max-width:30rem;margin-top:.4rem">
      <thead><tr><th>kg CO2 je m² und Jahr</th><th class="rt-num">Mieter</th><th class="rt-num">Vermieter</th></tr></thead>
      <tbody>
        @foreach (Co2Split::STEPS as $i => [$from, $pct])
          <tr><td>{{ Co2Split::stepLabel($i + 1) }}</td><td class="rt-num">{{ 100 - $pct }} %</td><td class="rt-num">{{ $pct }} %</td></tr>
        @endforeach
      </tbody>
    </table>
    <p class="sd-muted">Gewerbe (Nichtwohngebäude): 50 : 50. Bei Einschränkungen durch Denkmal- oder Milieuschutz wird der Vermieteranteil halbiert (Einstellung beim Objekt unter Nebenkosten).</p>
  </details>
</div>

<div class="sd-card">
  <h2>Erfasste Erstattungen</h2>
  @if ($claims->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Mieter</th><th>Zeitraum</th><th>Berechnung</th><th class="rt-num">Erstattung</th><th>Stand</th><th></th></tr></thead>
        <tbody>
          @foreach ($claims as $c)
            <tr id="erstattung-{{ $c->id }}">
              <td>@if ($c->lease)<a href="{{ route('leases.show', $c->lease_id) }}">{{ $c->lease->tenant?->display_name }}</a><br><span class="sd-muted">{{ $c->lease->unit?->property?->name }} · {{ $c->lease->unit?->label }}</span>@else – @endif</td>
              <td data-l="Zeitraum">{{ $c->period_from->format('d.m.Y') }}–{{ $c->period_to->format('d.m.Y') }}@if ($c->claimed_on)<br><span class="sd-muted">gefordert {{ $c->claimed_on->format('d.m.Y') }}</span>@endif</td>
              <td data-l="Berechnung">{{ number_format((float) $c->co2_kg, 1, ',', '.') }} kg, {{ number_format((float) $c->area, 2, ',', '.') }} m² = {{ number_format((float) $c->kg_per_sqm, 1, ',', '.') }} kg/m²<br><span class="sd-muted">{{ $c->building_use === 'nichtwohn' ? 'Gewerbe 50 %' : 'Stufe '.$c->step }}, Vermieter {{ $c->landlord_pct }} % von {{ Money::format($c->co2_cost) }}{{ $c->gas_stove ? ', Gasherd −5 %' : '' }}{{ $c->restriction !== 'keine' ? ', § 9 gekürzt' : '' }}</span></td>
              <td class="rt-num" data-l="Erstattung"><strong>{{ Money::format($c->amount) }}</strong></td>
              <td data-l="Stand">
                @if ($c->status === 'offen')
                  <span class="{{ $c->dueBy() && $c->dueBy()->format('Y-m-d') < date('Y-m-d', strtotime('+2 months')) ? 'rt-late' : '' }}">offen</span>@if ($c->dueBy())<br><span class="sd-muted">spätestens {{ $c->dueBy()->format('d.m.Y') }}</span>@endif
                @else
                  <span class="rt-ok">{{ $c->statusLabel() }}</span><br><span class="sd-muted">{{ $c->settled_on?->format('d.m.Y') }}</span>
                @endif
              </td>
              <td style="white-space:nowrap">
                <a class="sd-btn sec small" href="{{ route('co2.print', $c) }}" target="_blank" rel="noopener">Schreiben</a>
                @if ($c->status === 'offen')
                  <form class="sd-inline" method="POST" action="{{ route('co2.credit', $c) }}" onsubmit="return confirm('Als Gutschrift ins Mieterkonto buchen?')">@csrf<button class="sd-btn small" type="submit">Gutschreiben</button></form>
                  <details style="display:inline-block"><summary class="sd-btn sec small" style="display:inline-block">Ausgezahlt</summary>
                    <form method="POST" action="{{ route('co2.paid', $c) }}" style="margin-top:.3rem">@csrf<input type="date" name="settled_on" value="{{ date('Y-m-d') }}" required aria-label="Datum der Auszahlung"> <button class="sd-btn sec small" type="submit">Speichern</button></form>
                  </details>
                  <form class="sd-inline" method="POST" action="{{ route('co2.destroy', $c) }}" onsubmit="return confirm('Eintrag löschen?')">@csrf @method('DELETE')<button class="sd-btn danger small" type="submit">×</button></form>
                @else
                  <form class="sd-inline" method="POST" action="{{ route('co2.reset', $c) }}" onsubmit="return confirm('Wieder auf offen setzen? Eine Gutschrift im Mieterkonto wird entfernt.')">@csrf<button class="sd-btn sec small" type="submit">Zurücksetzen</button></form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <p class="sd-muted" style="margin-bottom:0">„Gutschreiben“ bucht den Betrag ins Mieterkonto; er wird mit der nächsten Miete verrechnet und mindert in der Anlage V die Einnahmen. Bei Überweisung „Ausgezahlt“ wählen und die Zahlung als Ausgabe erfassen.</p>
  @endif
</div>
@endsection
