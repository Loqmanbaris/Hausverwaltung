@extends('layouts.app')
@section('title', 'Belegung '.$unit->label)
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php use App\Models\UnitBooking; use App\Http\Controllers\BookingController; @endphp
<style>.bk-date{width:100%;padding:.5rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit}</style>
<div class="sd-head">
  <h1>Belegung {{ $unit->label }}</h1>
  <div class="sd-actions">
    @if ($listing)<a class="sd-btn sec" href="{{ route('listings.edit', $listing) }}">Inserat</a>@endif
    <a class="sd-btn sec" href="{{ route('inquiries.index') }}">Anfragen</a>
  </div>
</div>
<p class="sd-muted">{{ $unit->property?->name }}, {{ $unit->property?->addressLine() }}. Eingetragen wird von Anreise bis Abreise; der Abreisetag ist wieder frei. Mietverträge der Einheit zählen automatisch als belegt. Auf der Webseite erscheint nur „frei“ oder „belegt“, keine Namen.</p>

<div class="sd-card">
  <div style="display:flex;justify-content:space-between;gap:.5rem;margin-bottom:.6rem">
    <a class="sd-btn sec small" href="{{ route('bookings.index', ['unit' => $unit, 'monat' => $prev]) }}">← früher</a>
    <a class="sd-btn sec small" href="{{ route('bookings.index', ['unit' => $unit]) }}">heute</a>
    <a class="sd-btn sec small" href="{{ route('bookings.index', ['unit' => $unit, 'monat' => $next]) }}">später →</a>
  </div>
  @include('partials.occupancy-calendar', ['calendar' => $calendar])
</div>

<div class="sd-card" id="neu">
  <h2>Belegung eintragen</h2>
  @if ($inquiry)
    <p style="margin-top:0">Aus der Anfrage von <strong>{{ $inquiry->company ?: $inquiry->name }}</strong> vom {{ $inquiry->created_at->format('d.m.Y') }}.</p>
  @endif
  <form method="POST" action="{{ route('bookings.store', $unit) }}">
    @csrf
    @if ($inquiry)<input type="hidden" name="inquiry_id" value="{{ $inquiry->id }}">@endif
    <div class="sd-grid">
      <div class="sd-field"><label for="b-from">Anreise *</label><input class="bk-date" id="b-from" type="date" name="date_from" required value="{{ old('date_from', $inquiry?->date_from?->format('Y-m-d')) }}"></div>
      <div class="sd-field"><label for="b-to">Abreise *</label><input class="bk-date" id="b-to" type="date" name="date_to" required value="{{ old('date_to', $inquiry?->date_to?->format('Y-m-d')) }}"></div>
      @include('partials.sd-select', ['name' => 'status', 'label' => 'Status', 'options' => UnitBooking::STATUS, 'value' => $inquiry ? 'reserviert' : 'belegt', 'noBlank' => true])
      @include('partials.sd-field', ['name' => 'label', 'label' => 'Firma oder Gast', 'value' => $inquiry ? ($inquiry->company ?: $inquiry->name) : ''])
      @include('partials.sd-field', ['name' => 'persons', 'label' => 'Personen', 'value' => $inquiry?->persons, 'inputmode' => 'numeric'])
    </div>
    @include('partials.sd-textarea', ['name' => 'note', 'label' => 'Notiz (intern)', 'value' => ''])
    <button class="sd-btn" type="submit">Eintragen</button>
  </form>
</div>

<div class="sd-card">
  <h2>Belegungen</h2>
  @if (count($periods) === 0)
    <p class="sd-muted" style="margin:0">Keine Belegungen eingetragen.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Zeitraum</th><th>Wer</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach ($periods as $p)
            <tr>
              <td>{{ \Carbon\Carbon::parse($p['from'])->format('d.m.Y') }} – {{ $p['to'] === '9999-12-31' ? 'unbefristet' : \Carbon\Carbon::parse($p['to'])->format('d.m.Y') }}
                @if ($p['booking'])<br><span class="sd-muted">{{ $p['booking']->nights() }} Nächte{{ $p['booking']->persons ? ', '.$p['booking']->persons.' Pers.' : '' }}</span>@endif</td>
              <td>{{ $p['label'] ?: '–' }}@if ($p['booking'] && $p['booking']->note)<br><span class="sd-muted">{{ $p['booking']->note }}</span>@endif</td>
              <td>
                @if ($p['booking'])
                  <form method="POST" action="{{ route('bookings.update', [$unit, $p['booking']]) }}" class="sd-inline">
                    @csrf @method('PUT')
                    <select name="status" onchange="this.form.submit()" aria-label="Status" style="padding:.3rem;border:1px solid var(--line-strong);border-radius:.3rem;font:inherit">
                      @foreach (UnitBooking::STATUS as $k => $v)<option value="{{ $k }}" @selected($p['status'] === $k)>{{ $v }}</option>@endforeach
                    </select>
                  </form>
                @else
                  Mietvertrag
                @endif
              </td>
              <td>
                @if ($p['booking'])
                  <form method="POST" action="{{ route('bookings.destroy', [$unit, $p['booking']]) }}" class="sd-inline" onsubmit="return confirm('Belegung entfernen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Entfernen</button></form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
