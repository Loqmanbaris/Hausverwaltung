@extends('layouts.app')
@section('title', 'DATEV-Export Mieten')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php use App\Support\Money; @endphp
<style>
.dv-warn{background:var(--brass-bg);border:1px solid var(--brass-line);color:var(--brass);padding:.7rem 1rem;border-radius:.5rem;margin-bottom:1rem}
.dv-num{text-align:right;white-space:nowrap}
.dv-form{display:flex;flex-wrap:wrap;gap:.7rem;align-items:end}
.dv-form .f{display:flex;flex-direction:column}
.dv-form label{font-size:.9rem;margin:0 0 .15rem;font-weight:600}
.dv-form input[type=date],.dv-form select{width:auto;padding:.5rem;border:1px solid var(--line-strong);border-radius:.45rem;font:inherit;background:var(--surface)}
</style>

<div class="sd-head">
  <h1>DATEV-Export Mieten</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('datev.rent.accounts') }}">Erlöskonten Mieten</a>
    <a class="sd-btn sec" href="{{ route('datev.index') }}">Export Belege</a>
  </div>
</div>

<p class="sd-muted">Hier erzeugst du die Datei mit den Mieteinnahmen für den Steuerberater. Gebucht werden die Zahlungseingänge (Zuflussprinzip) im gewählten Zeitraum, aufgeteilt nach Miete, Nebenkosten- und Heizkosten-Vorauszahlung und nach Steuersatz. Die Datei ist getrennt von der Belege-Datei. Beide werden nacheinander in Lexware eingelesen.</p>

@if (count($missing) > 0 || $counters['bank'] === '')
  <div class="dv-warn">
    <strong>Noch einzutragen:</strong>
    @if ($counters['bank'] === '') Gegenkonto Bank. @endif
    @if (count($missing) > 0) Erlöskonto „Miete“ für {{ implode(', ', $missing) }}. Nur nötig, wenn es Verträge dieser Art gibt. @endif
    <a href="{{ route('datev.rent.accounts') }}">Erlöskonten eintragen</a>
  </div>
@endif

@if ($cancelled->isNotEmpty())
  <div class="dv-warn">
    <strong>Nach dem Export storniert:</strong> Diese Zahlungen waren schon in einem Export. Sprich mit dem Steuerberater ab, ob er eine Korrektur braucht.<br>
    @foreach ($cancelled as $c)
      {{ $c->paid_on->format('d.m.Y') }} {{ Money::format($c->amount) }} ({{ $c->lease?->unit?->label }} – {{ $c->lease?->tenant?->display_name }}){{ $loop->last ? '' : ', ' }}
    @endforeach
  </div>
@endif

<div class="sd-card">
  <h2>Zeitraum wählen</h2>
  <form class="dv-form" method="GET" action="{{ route('datev.rent') }}">
    <input type="hidden" name="vorschau" value="1">
    <div class="f"><label for="dv-von">Zahlungen vom</label><input id="dv-von" type="date" name="von" value="{{ $from->format('Y-m-d') }}" required></div>
    <div class="f"><label for="dv-bis">bis</label><input id="dv-bis" type="date" name="bis" value="{{ $to->format('Y-m-d') }}" required></div>
    <div class="f"><label for="dv-own">Eigentümer</label>
      <select id="dv-own" name="eigentuemer">
        <option value="">Alle</option>
        @foreach ($owners as $o)
          <option value="{{ $o->id }}" @selected($ownerId === (int) $o->id)>{{ $o->name }}</option>
        @endforeach
      </select>
    </div>
    <label class="sd-check" style="margin:0 0 .4rem"><input type="checkbox" name="nochmal" value="1" @checked($again)> Auch schon exportierte Zahlungen</label>
    <button class="sd-btn" type="submit">Vorschau</button>
  </form>
  <p class="sd-muted" style="margin-bottom:0">Ohne den Haken kommen nur Zahlungen in den Export, die noch in keiner Datei waren. Der Zeitraum muss innerhalb eines Wirtschaftsjahres liegen. Die Datei geht an Mandant {{ $config['client'] }}. Wenn die Objekte der Geschwister nicht dazugehören, wähle oben deinen Eigentümer aus.</p>
</div>

@foreach ($inputErrors as $e)
  <div class="sd-err">{{ $e }}</div>
@endforeach

@if ($preview)
  <div class="sd-card">
    <h2>Vorschau</h2>
    @if ($preview['problems'] !== [])
      <div class="dv-warn"><ul style="margin:0;padding-left:1.2rem">@foreach ($preview['problems'] as $p)<li>{{ $p }}</li>@endforeach</ul></div>
    @endif
    @if ($preview['already'] > 0)
      <p class="sd-muted">{{ $preview['already'] }} Zahlung(en) waren schon in einem früheren Export und sind hier nicht enthalten.</p>
    @endif
    @if ($preview['skipped'] !== [])
      <div class="dv-warn"><strong>{{ count($preview['skipped']) }} Zahlung(en) ohne Erlöskonto:</strong>
        <ul style="margin:.3rem 0 0;padding-left:1.2rem">
          @foreach (collect($preview['skipped'])->unique('reason') as $s)<li>{{ $s['reason'] }}</li>@endforeach
        </ul>
      </div>
    @endif

    @if ($preview['bookings'] === [])
      <p class="sd-muted" style="margin:0">Keine Buchungen.</p>
    @else
      <p><strong>{{ count($preview['payments']) - count($preview['skipped']) }} Zahlungen, {{ count($preview['bookings']) }} Buchungen.</strong></p>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Datum</th><th>Belegfeld</th><th>Buchungstext</th><th>Konto</th><th>Gegenkonto</th><th>BU</th><th class="dv-num">Betrag</th></tr></thead>
          <tbody>
            @foreach ($preview['bookings'] as $b)
              <tr>
                <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($b['date'])->format('d.m.Y') }}</td>
                <td>{{ $b['doc'] }}</td>
                <td>{{ $b['text'] }}</td>
                <td>{{ $b['account'] }}</td>
                <td>{{ $b['counter'] }}</td>
                <td>{{ $b['bu_key'] }}</td>
                <td class="dv-num">{{ $b['side'] === 'S' ? '−' : '' }}{{ Money::format($b['amount']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <h3 style="font-size:1rem">Summe je Erlöskonto</h3>
      <div class="sd-table-wrap">
        <table class="sd">
          <tbody>
            @foreach ($preview['sums'] as $acc => $sum)
              <tr><td>{{ $acc }}</td><td class="dv-num">{{ Money::format($sum) }}</td></tr>
            @endforeach
            <tr><td><strong>Gesamt</strong></td><td class="dv-num"><strong>{{ Money::format(array_sum($preview['sums'])) }}</strong></td></tr>
          </tbody>
        </table>
      </div>
      @if ($preview['problems'] === [] && $preview['skipped'] === [])
        <form method="POST" action="{{ route('datev.rent.export') }}" style="margin-top:1rem">
          @csrf
          <input type="hidden" name="von" value="{{ $from->format('Y-m-d') }}">
          <input type="hidden" name="bis" value="{{ $to->format('Y-m-d') }}">
          <input type="hidden" name="eigentuemer" value="{{ $ownerId }}">
          <input type="hidden" name="nochmal" value="{{ $again ? 1 : 0 }}">
          <button class="sd-btn" type="submit">DATEV-Datei erstellen</button>
          <span class="sd-muted">Die Zahlungen gelten danach als exportiert.</span>
        </form>
      @endif
    @endif
  </div>
@endif

<div class="sd-card">
  <h2>Bisherige Exporte</h2>
  <div class="sd-table-wrap">
    <table class="sd">
      <thead><tr><th>Nr.</th><th>Erstellt</th><th>Zahlungen vom</th><th>Eigentümer</th><th class="dv-num">Buchungen</th><th></th></tr></thead>
      <tbody>
        @forelse ($exports as $x)
          <tr>
            <td>{{ $x->id }}</td>
            <td style="white-space:nowrap">{{ $x->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}@if ($x->creator) <span class="sd-muted">von {{ $x->creator->name }}</span>@endif</td>
            <td style="white-space:nowrap">{{ $x->period_from->format('d.m.Y') }} bis {{ $x->period_to->format('d.m.Y') }}</td>
            <td>{{ $x->owner ? $x->owner->name : 'Alle' }}</td>
            <td class="dv-num">{{ $x->entry_count }}</td>
            <td><a class="sd-btn sec small" href="{{ route('datev.download', $x) }}">DATEV-Datei</a></td>
          </tr>
        @empty
          <tr><td colspan="6" class="sd-muted">Noch kein Export erstellt.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
