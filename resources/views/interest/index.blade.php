@extends('layouts.app')
@section('title', 'Verzugszinsen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php use App\Support\Money; use App\Support\Interest\Verzugszinsen; @endphp
<div class="sd-head">
  <h1>Verzugszinsen</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('leases.show', $lease) }}#zinsen">Zum Mietvertrag</a></div>
</div>
<p class="sd-muted">{{ $lease->unit?->label }}, {{ $lease->unit?->property?->name }} · {{ $lease->tenant?->display_name }}. Zinsen nach § 288 BGB taggenau ab dem Tag nach der Fälligkeit bis zur Zahlung. Keine Zinsen auf Zinsen und Mahngebühren.</p>
@if ($missingRate)
  <div class="sd-err">Für das laufende Halbjahr ist noch kein Basiszinssatz eingetragen. Bitte im <a href="{{ route('dunning.index') }}#basiszins">Mahnwesen</a> ergänzen.</div>
@endif

<div class="sd-card">
  <form class="rt-filter" method="GET" action="{{ route('interest.index', $lease) }}" style="flex-wrap:wrap">
    <label>Zinsen bis <input type="date" name="bis" value="{{ $until }}" max="{{ date('Y-m-d') }}" style="padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit"></label>
    <select name="modus" aria-label="Zinssatz">
      @foreach (Verzugszinsen::MODES as $k => $label)<option value="{{ $k }}" @selected($mode === $k)>{{ $label }}</option>@endforeach
    </select>
    <button class="sd-btn sec small" type="submit">Berechnen</button>
  </form>
  <p style="margin:.6rem 0 0">Zeitraum: {{ $from ? 'ab '.\Carbon\Carbon::parse($from)->addDay()->format('d.m.Y').' (bis dahin schon gebucht)' : 'seit der ersten Fälligkeit' }} bis {{ \Carbon\Carbon::parse($until)->format('d.m.Y') }}.
    Zinssatz derzeit {{ $rateNow !== null ? number_format($rateNow + Verzugszinsen::surcharge($mode), 2, ',', '.').' % pro Jahr' : 'unbekannt' }}.</p>
</div>

<div class="sd-card">
  <h2>Berechnung</h2>
  @if (empty($calc['lines']))
    <p class="rt-ok" style="margin:0">Keine Verzugszinsen in diesem Zeitraum.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Posten</th><th>fällig</th><th class="rt-num">Betrag</th><th class="rt-num">noch offen</th><th class="rt-num">Zinstage</th><th class="rt-num">Zinsen</th></tr></thead>
        <tbody>
          @foreach ($calc['lines'] as $l)
            <tr>
              <td>{{ $l['label'] }}</td>
              <td>{{ \Carbon\Carbon::parse($l['due'])->format('d.m.Y') }}</td>
              <td class="rt-num">{{ Money::format($l['total']) }}</td>
              <td class="rt-num">{{ Money::format($l['open_now']) }}</td>
              <td class="rt-num">{{ $l['days'] }}</td>
              <td class="rt-num">{{ Money::format($l['interest']) }}</td>
            </tr>
          @endforeach
          <tr><td colspan="5"><strong>Summe</strong></td><td class="rt-num"><strong>{{ Money::format($calc['total']) }}</strong></td></tr>
        </tbody>
      </table>
    </div>
    @if ($calc['missing_rate'])<p class="rt-late">Für einen Teil des Zeitraums fehlt der Basiszinssatz; diese Tage sind nicht berechnet.</p>@endif
    <form method="POST" action="{{ route('interest.book', $lease) }}" onsubmit="return confirm('{{ Money::format($calc['total']) }} Verzugszinsen als Forderung ins Mieterkonto buchen?')" style="margin-top:.8rem">
      @csrf
      <input type="hidden" name="until" value="{{ $until }}">
      <input type="hidden" name="mode" value="{{ $mode }}">
      <button class="sd-btn" type="submit">Als Forderung buchen</button>
      <span class="sd-muted">Erscheint dann im Mieterkonto und in der nächsten Mahnung. Kleinbeträge besser nicht einzeln buchen.</span>
    </form>
  @endif
</div>

<div class="sd-card">
  <h2>Gebuchte Zinsen</h2>
  @if ($bookings->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine.</p>
  @else
    <table class="sd">
      <thead><tr><th>Zeitraum</th><th class="rt-num">Betrag</th><th>Status</th><th></th></tr></thead>
      <tbody>
        @foreach ($bookings as $b)
          <tr>
            <td>{{ $b->period_from->copy()->addDay()->format('d.m.Y') }} – {{ $b->period_until->format('d.m.Y') }}</td>
            <td class="rt-num">{{ Money::format($b->amount) }}</td>
            <td>{{ $b->status === 'gebucht' ? 'gebucht' : 'storniert' }}</td>
            <td>@if ($b->status === 'gebucht')<form method="POST" action="{{ route('interest.cancel', $b) }}" class="sd-inline" onsubmit="return confirm('Zinsbuchung stornieren?')">@csrf<button class="sd-btn sec small" type="submit">Stornieren</button></form>@endif</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  @endif
</div>
@endsection
