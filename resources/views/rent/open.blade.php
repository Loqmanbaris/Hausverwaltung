@extends('layouts.app')
@section('title', 'Offene Posten')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php use App\Support\Money; @endphp
<div class="sd-head">
  <h1>Offene Posten</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('rent.month') }}">Mieteingang</a>
    <a class="sd-btn sec" href="{{ route('leases.index') }}">Mietverträge</a>
    <a class="sd-btn" href="{{ route('dunning.index') }}">Mahnwesen</a>
  </div>
</div>
<p class="sd-muted">Alle Mieten ab {{ \Carbon\Carbon::parse(\App\Support\Rent\Mieten::start())->format('m.Y') }}, die fällig und noch nicht (ganz) bezahlt sind, Stand heute. Zahlungserinnerungen und Mahnungen erstellst du im <a href="{{ route('dunning.index') }}">Mahnwesen</a>.</p>

@if ($owners->count() > 1)
  <form class="rt-filter" method="GET" action="{{ route('rent.open') }}">
    <select name="eigentuemer" aria-label="Eigentümer">
      <option value="">Alle Eigentümer</option>
      @foreach ($owners as $o)
        <option value="{{ $o->id }}" @selected($ownerId === (int) $o->id)>{{ $o->name }}</option>
      @endforeach
    </select>
    <button class="sd-btn sec small" type="submit">Anzeigen</button>
  </form>
@endif

<div class="rt-stats">
  <div class="rt-stat"><strong @class(['rt-late' => $total > 0.004, 'rt-ok' => $total <= 0.004])>{{ Money::format($total) }}</strong><span>offen gesamt</span></div>
  <div class="rt-stat"><strong>{{ $groups->count() }}</strong><span>Mietverträge mit Rückstand</span></div>
  @foreach ($byOwner as $name => $sum)
    @if ($byOwner->count() > 1)
      <div class="rt-stat"><strong>{{ Money::format($sum) }}</strong><span>{{ $name }}</span></div>
    @endif
  @endforeach
</div>

<div class="sd-card">
  @if ($groups->isEmpty())
    <p class="rt-ok" style="margin:0">Keine offenen Posten. Alle fälligen Mieten sind bezahlt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Einheit und Mieter</th><th>Offene Monate</th><th class="rt-num">Offen</th><th class="rt-num">Älteste Fälligkeit</th><th></th></tr></thead>
        <tbody>
          @foreach ($groups as $g)
            <tr>
              <td><a href="{{ route('leases.show', $g['lease']) }}">{{ $g['lease']->unit?->label }}</a>, {{ $g['lease']->unit?->property?->name }}<br>
                <span class="sd-muted">{{ $g['lease']->tenant?->display_name }} · {{ $g['lease']->owner?->name }}</span></td>
              <td>
                @foreach ($g['charges'] as $c)
                  {{ $c->periodLabel() }}: {{ Money::format($c->openAmount()) }}@if ((float) $c->paid_amount > 0) <span class="sd-muted">(Teilzahlung)</span>@endif<br>
                @endforeach
              </td>
              <td class="rt-num rt-late">{{ Money::format($g['open']) }}</td>
              <td class="rt-num">{{ \Carbon\Carbon::parse($g['oldest'])->format('d.m.Y') }}<br><span class="sd-muted">seit {{ $g['days'] }} Tag{{ $g['days'] === 1 ? '' : 'en' }}</span></td>
              <td><a class="sd-btn small" href="{{ route('leases.show', $g['lease']) }}#zahlung">Zahlung erfassen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@if (auth()->user()?->isAdmin())
  <form class="sd-card rt-filter" id="erfasst-ab" method="POST" action="{{ route('rent.start') }}" style="margin-top:1rem;align-items:center">
    @csrf @method('PUT')
    <label style="margin:0">Mieten erfasst ab <input type="month" name="rent_start" value="{{ substr(\App\Support\Rent\Mieten::start(), 0, 7) }}" required></label>
    <button class="sd-btn sec small" type="submit" onclick="return confirm('Alle Mietkonten neu berechnen? Soll-Mieten vor diesem Monat werden entfernt.')">Speichern</button>
    <span class="sd-muted">Für Monate davor gibt es kein Soll, keinen Rückstand und keine Mahnung.</span>
  </form>
@endif
@endsection
