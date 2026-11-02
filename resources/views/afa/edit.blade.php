@extends('layouts.app')
@section('title', 'AfA')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Tax\Afa;
  $eur = fn ($c) => number_format($c / 100, 2, ',', '.').' €';
  $input = 'padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;width:100%';
  $rateNow = rtrim(rtrim(number_format((float) old('rate', $a->rate ?? 2), 2, '.', ''), '0'), '.');
@endphp
<div class="sd-head">
  <h1>AfA: {{ $property->name }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('properties.show', $property) }}">Zurück zum Objekt</a></div>
</div>
<p class="sd-muted">Die Absetzung für Abnutzung (AfA) mindert in der Anlage V die steuerpflichtigen Einnahmen, ohne dass Geld abfließt. Gerechnet wird die lineare AfA auf das Gebäude (ohne Grund und Boden). Degressive AfA und Sonder-AfA rechnet das CRM nicht, dafür trägst du unten den Jahresbetrag deines Steuerberaters ein. Gebäudeanteil, Satz und Beginn bitte mit dem Steuerberater abstimmen.</p>

<form method="POST" action="{{ route('afa.update', $property) }}" class="sd-card">
  @csrf @method('PUT')
  <div class="sd-grid">
    <div class="sd-field">
      <label for="f-share">Gebäudeanteil am Kaufpreis (%)</label>
      <input id="f-share" name="building_share" inputmode="decimal" value="{{ old('building_share', $a->building_share !== null ? number_format((float) $a->building_share, 2, ',', '') : '') }}">
      <small class="sd-muted">Laut Kaufvertrag oder Aufteilung des Finanzamts. Der Rest ist Grund und Boden und wird nicht abgeschrieben.@if ($v && (float) $v->purchase_price > 0) Kaufpreis im CRM: {{ Money::format($v->purchase_price) }}, Nebenkosten {{ Money::format($v->purchase_costs ?? 0) }}.@else Der Kaufpreis fehlt noch unter <a href="{{ route('properties.values', $property) }}">Kaufpreis und Wert</a>.@endif</small>
    </div>
    <div class="sd-field">
      <label for="f-override">oder Bemessungsgrundlage direkt (€)</label>
      <input id="f-override" name="basis_override" inputmode="decimal" value="{{ old('basis_override', $a->basis_override !== null ? Money::input($a->basis_override) : '') }}">
      <small class="sd-muted">Wenn der Steuerberater die Grundlage vorgibt. Ersetzt die Rechnung aus Kaufpreis und Gebäudeanteil.</small>
    </div>
  </div>
  <div class="sd-grid">
    <div class="sd-field">
      <label for="f-rate">AfA-Satz (%) *</label>
      <input id="f-rate" name="rate" inputmode="decimal" required list="afa-rates" value="{{ str_replace('.', ',', $rateNow) }}">
      <datalist id="afa-rates">@foreach (Afa::RATES as $k => $label)<option value="{{ str_replace('.', ',', $k) }}">{{ $label }}</option>@endforeach</datalist>
      <small class="sd-muted">Üblich: 2 % (Fertigstellung 1925 bis 2022), 2,5 % (davor), 3 % (ab 2023).</small>
    </div>
    <div class="sd-field">
      <label for="f-start">Beginn der AfA (Anschaffung oder Fertigstellung)</label>
      <input id="f-start" type="date" name="start_date" value="{{ old('start_date', $a->start_date?->format('Y-m-d') ?? $v?->purchase_date?->format('Y-m-d')) }}">
      <small class="sd-muted">Im ersten Jahr wird anteilig ab diesem Monat gerechnet. Leer = Kaufdatum.</small>
    </div>
    <div class="sd-field">
      <label for="f-extra">Nachträgliche Herstellungskosten (€)</label>
      <input id="f-extra" name="extra_basis" inputmode="decimal" value="{{ old('extra_basis', (float) $a->extra_basis > 0 ? Money::input($a->extra_basis) : '') }}">
      <small class="sd-muted">Wird zur Grundlage addiert und vereinfacht ab Beginn mitgerechnet. Genauer bitte über einen Jahresbetrag unten.</small>
    </div>
  </div>
  <div class="sd-field"><label for="f-note">Notiz</label><textarea id="f-note" name="note" rows="2" maxlength="2000" style="{{ $input }}">{{ old('note', $a->note) }}</textarea></div>
  <button class="sd-btn" type="submit">Speichern</button>
</form>

@if (! empty($near) && $near['count'] > 0)
<div class="sd-card" style="border-left:4px solid {{ $near['status'] === 'ueber' ? '#c0392b' : ($near['status'] === 'nah' ? '#e67e22' : '#2e8b57') }}">
  <h2>Anschaffungsnahe Herstellungskosten (15-%-Grenze)</h2>
  <p style="margin:.2rem 0">Instandsetzungs- und Modernisierungsbelege in den ersten drei Jahren nach dem Kauf ({{ date('d.m.Y', strtotime($near['from'])) }} bis {{ date('d.m.Y', strtotime($near['to'])) }}): <strong>{{ $eur($near['sum']) }}</strong> ({{ number_format($near['percent'], 1, ',', '.') }} % der Grundlage). Grenze: {{ $eur($near['limit']) }}.</p>
  @if ($near['status'] === 'ueber')
  <p style="margin:.2rem 0"><strong>Die Grenze ist überschritten.</strong> Diese Aufwendungen sind dann steuerlich keine sofort abziehbaren Erhaltungskosten, sondern erhöhen die Grundlage der AfA. Das CRM zieht sie in der Anlage V derzeit trotzdem bei den Erhaltungsaufwendungen ab. Bitte mit dem Steuerberater klären, ob du sie stattdessen bei „Zusätzliche Grundlage“ eintragen musst.</p>
  @elseif ($near['status'] === 'nah')
  <p style="margin:.2rem 0">Du näherst dich der Grenze. Weitere Maßnahmen in diesem Zeitraum können sie überschreiten. Sprich vorher mit dem Steuerberater.</p>
  @else
  <p style="margin:.2rem 0" class="sd-muted">Die Grenze ist nicht erreicht. Rechnerische Prüfung nach Belegdatum, ohne Gewähr (Umsatzsteuer und regelmäßig wiederkehrende Arbeiten sind bei der Grenze anders zu behandeln).</p>
  @endif
</div>
@endif

<div class="sd-card">
  <h2>AfA je Jahr</h2>
  @if ($basis === null || $start === null)
    <p class="sd-muted" style="margin:0">Sobald Gebäudeanteil (und Kaufpreis) oder die Bemessungsgrundlage sowie der Beginn eingetragen sind, siehst du hier die AfA je Jahr.</p>
  @else
    <p class="sd-muted" style="margin-top:0">Bemessungsgrundlage: <strong>{{ $eur($basis) }}</strong>, Satz {{ str_replace('.', ',', $rateNow) }} %, Beginn {{ \Carbon\Carbon::parse($start)->format('d.m.Y') }}.</p>
    <div class="sd-table-wrap"><table class="sd">
      <thead><tr><th>Jahr</th><th class="rt-num">AfA</th><th>Art</th></tr></thead>
      <tbody>@foreach ($years as $y => $r)<tr><td>{{ $y }}</td><td class="rt-num">{{ $r ? $eur($r['cents']) : '–' }}</td><td>{{ $r ? ($r['source'] === 'manuell' ? 'Jahresbetrag (von Hand)' : ($r['months'] < 12 ? 'linear, '.$r['months'].' Monate' : 'linear')) : '' }}</td></tr>@endforeach</tbody>
    </table></div>
  @endif
</div>

<div class="sd-card">
  <h2>Jahresbetrag vom Steuerberater</h2>
  <p class="sd-muted" style="margin-top:0">Für degressive AfA, Sonder-AfA oder Sonderfälle: Der Betrag ersetzt für dieses Jahr die Rechnung. Spätere Jahre rechnet das CRM weiter linear, höchstens bis die Grundlage verbraucht ist.</p>
  @if ($overrides->isNotEmpty())
    <div class="sd-table-wrap"><table class="sd"><tbody>
      @foreach ($overrides as $o)
        <tr><td>{{ $o->year }}</td><td class="rt-num">{{ Money::format($o->amount) }}</td><td>{{ $o->note }}</td>
          <td><form method="POST" action="{{ route('afa.year.destroy', [$property, $o->year]) }}" onsubmit="return confirm('Jahresbetrag entfernen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Entfernen</button></form></td></tr>
      @endforeach
    </tbody></table></div>
  @endif
  <form method="POST" action="{{ route('afa.year.store', $property) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin-top:.6rem">
    @csrf
    <div class="sd-field" style="margin:0"><label for="f-y">Jahr</label><input id="f-y" name="year" type="number" min="1990" max="{{ (int) date('Y') + 1 }}" required value="{{ date('Y') - 1 }}" style="{{ $input }};width:7rem"></div>
    <div class="sd-field" style="margin:0"><label for="f-amt">AfA-Betrag (€)</label><input id="f-amt" name="amount" inputmode="decimal" required style="{{ $input }};width:9rem"></div>
    <div class="sd-field" style="margin:0"><label for="f-n">Hinweis</label><input id="f-n" name="note" maxlength="190" placeholder="z. B. degressiv 5 %" style="{{ $input }}"></div>
    <button class="sd-btn" type="submit">Speichern</button>
  </form>
</div>
@endsection
