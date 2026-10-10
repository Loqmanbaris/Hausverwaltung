@extends('layouts.app')
@section('title', 'Mietänderung erfassen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Rent\Mieten;
  $base = $latest ?? $current;
  $reasonValue = old('reason', $reason);
  $hasProposal = $proposal && ! isset($proposal['error']);
@endphp
<div class="sd-head">
  <h1>Mietänderung erfassen</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('leases.show', $lease) }}">Abbrechen</a></div>
</div>
<p class="sd-muted">{{ $lease->title() }}. Die neuen Beträge gelten ab dem gewählten Monat bis zur nächsten Änderung. Die Felder sind mit dem letzten Stand vorbelegt{{ (float) $lease->vat_rate > 0 ? ' (netto, ohne Umsatzsteuer)' : '' }}.</p>

@if ($proposal && isset($proposal['error']))
  <div class="sd-err">{{ $proposal['error'] }}</div>
@elseif ($hasProposal)
  <div class="sd-card">
    <h2>Vorschlag Indexanpassung</h2>
    <dl class="sd-dl">
      <dt>Ausgangswert</dt><dd>{{ number_format((float) $lease->index_base_value, 1, ',', '') }} ({{ $lease->index_base_month?->format('m/Y') }})</dd>
      <dt>Neuer Indexwert</dt><dd>{{ number_format((float) $proposal['index'], 1, ',', '') }}{{ $proposal['month'] ? ' ('.substr($proposal['month'], 5, 2).'/'.substr($proposal['month'], 0, 4).')' : '' }}</dd>
      <dt>Veränderung</dt><dd>{{ str_replace('.', ',', $proposal['change_percent']) }} %</dd>
      <dt>Kaltmiete bisher</dt><dd>{{ Money::format($base->rent_cold) }}</dd>
      <dt>Kaltmiete neu</dt><dd><strong>{{ Money::format($proposal['new_cold']) }}</strong></dd>
      <dt>Frühestens ab</dt><dd>{{ \Carbon\Carbon::parse($proposal['earliest'])->format('d.m.Y') }}</dd>
    </dl>
    <p class="sd-muted" style="margin-bottom:0">Die neue Miete gilt frühestens ab dem übernächsten Monat, nachdem der Mieter die schriftliche Erklärung erhalten hat, und frühestens ein Jahr nach der letzten Änderung (§ 557b BGB). Sinkt der Index, darf der Mieter eine Senkung verlangen. Das Schreiben an den Mieter erstellst du vorerst selbst.</p>
  </div>
@endif

<form method="POST" action="{{ route('leases.rent.store', $lease) }}">
  @csrf
  <div class="sd-card">
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-reason">Art der Änderung *</label>
        <select id="f-reason" name="reason" required>
          @foreach (Mieten::REASONS as $k => $label)
            @continue($k === 'beginn')
            <option value="{{ $k }}" @selected($reasonValue === $k)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="sd-field">
        <label for="f-valid_from">Gilt ab Monat *</label>
        <input id="f-valid_from" name="valid_from" type="month" required value="{{ old('valid_from', $defaultFrom) }}">
        <small class="sd-muted">Auch für spätere Staffeln oder rückwirkende Korrekturen.</small>
      </div>
    </div>
    <div class="sd-grid">
      @foreach (Mieten::COMPONENTS as $col => $label)
        @php($v = $col === 'rent_cold' && $hasProposal ? $proposal['new_cold'] : $base?->{$col})
        @include('partials.sd-field', ['name' => $col, 'label' => $label, 'inputmode' => 'decimal', 'value' => Money::input($v)])
      @endforeach
    </div>
    <div class="sd-grid" id="index-fields">
      @include('partials.sd-field', ['name' => 'index_value', 'label' => 'Neuer Indexwert', 'inputmode' => 'decimal', 'value' => $hasProposal ? number_format((float) $proposal['index'], 1, ',', '') : '', 'hint' => 'Wird der neue Ausgangswert für die nächste Anpassung.'])
      <div class="sd-field">
        <label for="f-index_month">Monat des Indexwerts</label>
        <input id="f-index_month" name="index_month" type="month" value="{{ old('index_month', $hasProposal ? $proposal['month'] : '') }}">
      </div>
    </div>
    @include('partials.sd-field', ['name' => 'note', 'label' => 'Notiz', 'hint' => 'z. B. „Schreiben vom 15.10.2026“'])
  </div>
  <button class="sd-btn" type="submit">Mietänderung speichern</button>
</form>
<script>
(function () {
  var r = document.getElementById('f-reason'), f = document.getElementById('index-fields');
  function t() { f.style.display = r.value === 'index' ? '' : 'none'; }
  r.addEventListener('change', t); t();
})();
</script>
@endsection
