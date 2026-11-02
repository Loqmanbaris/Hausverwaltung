@extends('layouts.app')
@section('title', 'Miete korrigieren')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Rent\Mieten;
  $vat = (float) $lease->vat_rate;
@endphp
<div class="sd-head">
  <h1>Miete korrigieren</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('leases.show', $lease) }}">Abbrechen</a></div>
</div>
<p class="sd-muted">{{ $lease->title() }} · {{ $rent->reasonLabel() }} ab {{ $rent->valid_from->format('m/Y') }}. Hier korrigierst du falsch erfasste Beträge. Alle Monate ab {{ $rent->valid_from->format('m/Y') }} bis zur nächsten Änderung werden neu berechnet. Für eine echte Mieterhöhung ab einem neuen Monat nimm „Mietänderung erfassen“.</p>

<div class="sd-card">
  <p style="margin:0">Umsatzsteuer: <strong>{{ Mieten::vatLabel($lease->vat_rate) }}</strong>{{ $vat > 0 ? ' – die Beträge hier sind netto.' : '' }}
    <a href="{{ route('leases.edit', $lease) }}">Ändern unter „Vertrag bearbeiten“ → Umsatzsteuer</a></p>
</div>

@if ($locked)
  <div class="sd-err">Diese Beträge gelten schon. Korrigieren kann sie nur der Administrator.</div>
@else
  <form method="POST" action="{{ route('leases.rent.update', [$lease, $rent]) }}">
    @csrf @method('PUT')
    <div class="sd-card">
      <div class="sd-grid">
        @foreach (Mieten::COMPONENTS as $col => $label)
          @include('partials.sd-field', ['name' => $col, 'label' => $label.($vat > 0 ? ' (netto)' : ''), 'inputmode' => 'decimal', 'value' => Money::input($rent->{$col}), 'required' => $col === 'rent_cold'])
        @endforeach
      </div>
      @include('partials.sd-field', ['name' => 'note', 'label' => 'Notiz', 'value' => $rent->note, 'hint' => 'z. B. „Tippfehler, richtig sind 850,00“'])
    </div>
    <button class="sd-btn" type="submit">Korrektur speichern</button>
  </form>
@endif
@endsection
