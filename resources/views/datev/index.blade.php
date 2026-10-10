@extends('layouts.app')
@section('title', 'DATEV-Export')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.dv-warn{background:#fffaf0;border:1px solid #f0d9a8;color:#7a4b00;padding:.7rem 1rem;border-radius:.5rem;margin-bottom:1rem}
.dv-warn ul{margin:.3rem 0 0;padding-left:1.2rem}
.dv-num{text-align:right;white-space:nowrap}
.dv-form{display:flex;flex-wrap:wrap;gap:.7rem;align-items:end}
.dv-form .f{display:flex;flex-direction:column}
.dv-form label{font-size:.9rem;margin:0 0 .15rem;font-weight:600}
.dv-form input[type=date],.dv-form select{padding:.5rem;border:1px solid #c5ccd3;border-radius:.45rem;font:inherit;background:#fff}
.dv-sum{display:flex;flex-wrap:wrap;gap:1.5rem;margin:.2rem 0 .8rem}
.dv-sum div{min-width:8rem}
.dv-sum strong{display:block;font-size:1.2rem}
</style>

<div class="sd-head">
  <h1>DATEV-Export</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('datev.settings') }}">Einstellungen</a>
    <a class="sd-btn sec" href="{{ route('datev.accounts') }}">Konten der Kostenarten</a>
    <a class="sd-btn" href="{{ route('datev.rent') }}">Export Mieten</a>
    <a class="sd-btn sec" href="{{ route('receipts.index') }}">Belege</a>
  </div>
</div>

<p class="sd-muted">Hier erzeugst du die Datei für den Steuerberater: alle vollständigen Belege eines Zeitraums als Buchungsstapel im DATEV-Format. Die Mieteinnahmen exportierst du getrennt unter „Export Mieten“.</p>

@if ($problems !== [])
  <div class="dv-warn">
    <strong>Bevor du exportieren kannst:</strong>
    <ul>
      @foreach ($problems as $p)
        <li>{{ $p }}</li>
      @endforeach
    </ul>
    <a href="{{ route('datev.settings') }}">Zu den Einstellungen</a>
  </div>
@endif

@if (count($withoutAccount) > 0)
  <div class="dv-warn">
    <strong>{{ count($withoutAccount) }} {{ count($withoutAccount) === 1 ? 'Kostenart hat' : 'Kostenarten haben' }} noch kein Konto.</strong>
    Belege mit diesen Kostenarten werden nicht exportiert:
    {{ implode(', ', array_slice($withoutAccount, 0, 6)) }}@if (count($withoutAccount) > 6) und weitere @endif.
    <a href="{{ route('datev.accounts') }}">Konten zuordnen</a>
  </div>
@endif

@if ($changed->count() > 0)
  <div class="dv-warn">
    <strong>Nach dem Export geändert oder archiviert:</strong>
    Diese Belege waren schon in einem Export und wurden danach verändert. Sprich mit dem Steuerberater ab, ob er eine Korrektur braucht.
    <br>
    @foreach ($changed as $c)
      @if ($c->deleted_at === null)
        <a href="{{ route('receipts.show', $c->id) }}">Nr. {{ $c->id }}</a>@else Nr. {{ $c->id }} (archiviert)@endif{{ $loop->last ? '' : ', ' }}
    @endforeach
  </div>
@endif

<div class="sd-card">
  <h2>Einstellungen</h2>
  <dl class="sd-dl">
    <dt>Beraternummer</dt><dd>{{ $config['consultant'] }}</dd>
    <dt>Mandantennummer</dt><dd>{{ $config['client'] }}</dd>
    <dt>Kontenrahmen</dt><dd>SKR{{ $config['skr'] }}</dd>
    <dt>Wirtschaftsjahr beginnt</dt><dd>1. {{ ['', 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'][$config['fy_month']] ?? '?' }}</dd>
    <dt>Sachkontenlänge</dt><dd>{{ $config['account_length'] }} Stellen</dd>
    <dt>Gegenkonto</dt><dd>{{ $config['counter_account'] !== '' ? $config['counter_account'] : 'noch nicht festgelegt' }}</dd>
  </dl>
</div>

<div class="sd-card">
  <h2>Zeitraum wählen</h2>
  <form class="dv-form" method="GET" action="{{ route('datev.index') }}">
    <input type="hidden" name="vorschau" value="1">
    <div class="f">
      <label for="dv-von">Belege vom</label>
      <input id="dv-von" type="date" name="von" value="{{ $from->format('Y-m-d') }}" required>
    </div>
    <div class="f">
      <label for="dv-bis">bis</label>
      <input id="dv-bis" type="date" name="bis" value="{{ $to->format('Y-m-d') }}" required>
    </div>
    <div class="f">
      <label for="dv-owner">Eigentümer</label>
      <select id="dv-owner" name="eigentuemer">
        <option value="">Alle</option>
        @foreach ($owners as $owner)
          <option value="{{ $owner->id }}" @selected($ownerId === (int) $owner->id)>{{ $owner->name }}</option>
        @endforeach
      </select>
    </div>
    <label class="sd-check" for="dv-again" style="font-weight:400;margin:0 0 .4rem">
      <input type="checkbox" id="dv-again" name="nochmal" value="1" @checked($again)>
      Auch schon exportierte Belege
    </label>
    <button class="sd-btn" type="submit">Vorschau anzeigen</button>
  </form>
  <p class="sd-muted" style="margin-bottom:0">Ohne den Haken kommen nur Belege in den Export, die noch in keiner Datei waren. Der Zeitraum muss innerhalb eines Wirtschaftsjahres liegen.</p>
  @foreach ($inputErrors as $e)
    <div class="dv-warn" style="margin:.8rem 0 0">{{ $e }}</div>
  @endforeach
</div>

@if ($preview !== null)
  @php($ready = $preview['problems'] === [] && count($preview['bookings']) > 0)
  <div class="sd-card">
    <h2>Vorschau</h2>
    <div class="dv-sum">
      <div><strong>{{ count($preview['bookings']) }}</strong>Buchungen</div>
      <div><strong>{{ \App\Support\Money::format($preview['total']) }}</strong>Saldo (Rechnungen minus Gutschriften)</div>
      <div><strong>{{ count($preview['skipped']) }}</strong>nicht übernommen</div>
    </div>

    @if ($preview['incomplete'] > 0)
      <div class="dv-warn">
        <strong>{{ $preview['incomplete'] }} {{ $preview['incomplete'] === 1 ? 'Beleg ist' : 'Belege sind' }} in diesem Zeitraum noch unvollständig</strong> und {{ $preview['incomplete'] === 1 ? 'wird' : 'werden' }} nicht exportiert.
        <a href="{{ route('receipts.index', ['status' => 'unvollstaendig']) }}">Unvollständige Belege anzeigen</a>
      </div>
    @endif
    @if ($preview['already'] > 0)
      <p class="sd-muted">{{ $preview['already'] }} {{ $preview['already'] === 1 ? 'Beleg war' : 'Belege waren' }} schon in einem früheren Export und {{ $preview['already'] === 1 ? 'ist' : 'sind' }} hier nicht enthalten. Mit dem Haken „Auch schon exportierte Belege“ kommen sie noch einmal mit.</p>
    @endif

    @if (count($preview['bookings']) > 0)
      <div class="sd-table-wrap">
        <table class="sd">
          <thead>
            <tr><th>Beleg</th><th>Datum</th><th>Belegnummer</th><th>Buchungstext</th><th>Konto</th><th>Gegenkonto</th><th>S/H</th><th class="dv-num">Betrag</th></tr>
          </thead>
          <tbody>
            @foreach ($preview['bookings'] as $b)
              <tr>
                <td><a href="{{ route('receipts.show', $b['receipt_id']) }}">{{ $b['receipt_id'] }}</a></td>
                <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($b['date'])->format('d.m.Y') }}</td>
                <td>{{ $b['doc'] }}</td>
                <td>{{ $b['text'] }}</td>
                <td>{{ $b['account'] }}</td>
                <td>{{ $b['counter'] }}</td>
                <td>{{ $b['side'] }}</td>
                <td class="dv-num">{{ \App\Support\Money::format($b['amount']) }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <h3 style="margin-bottom:.3rem">Summen je Konto</h3>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Konto</th><th class="dv-num">Saldo</th></tr></thead>
          <tbody>
            @foreach ($preview['sums'] as $account => $sum)
              <tr><td>{{ $account }}</td><td class="dv-num">{{ \App\Support\Money::format($sum) }}</td></tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @else
      <p>Für diesen Zeitraum gibt es keine Buchungen, die exportiert werden können.</p>
    @endif

    @if (count($preview['skipped']) > 0)
      <h3 style="margin-bottom:.3rem">Nicht übernommen</h3>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Beleg</th><th>Grund</th></tr></thead>
          <tbody>
            @foreach ($preview['skipped'] as $s)
              <tr>
                <td><a href="{{ route('receipts.show', $s['id']) }}">{{ $s['id'] }}</a></td>
                <td>{{ $s['reason'] }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif

    @if ($preview['problems'] !== [])
      <div class="dv-warn" style="margin-top:1rem">
        Die Datei kann erst erzeugt werden, wenn die Einstellungen stimmen (siehe oben).
      </div>
    @elseif ($ready)
      <form id="dv-export" method="POST" action="{{ route('datev.export') }}" style="margin-top:1rem">
        @csrf
        <input type="hidden" name="von" value="{{ $from->format('Y-m-d') }}">
        <input type="hidden" name="bis" value="{{ $to->format('Y-m-d') }}">
        <input type="hidden" name="eigentuemer" value="{{ $ownerId }}">
        <input type="hidden" name="nochmal" value="{{ $again ? 1 : 0 }}">
        <button class="sd-btn" type="submit" id="dv-go">DATEV-Datei erzeugen</button>
        <span class="sd-muted">Die {{ count($preview['bookings']) }} Belege gelten danach als exportiert.</span>
      </form>
      <script>
      (function () {
        var f = document.getElementById('dv-export');
        var sending = false;
        f.addEventListener('submit', function (ev) {
          if (sending) { ev.preventDefault(); return; }
          sending = true;
          window.setTimeout(function () {
            var b = document.getElementById('dv-go');
            b.disabled = true;
            b.textContent = 'Wird erzeugt …';
          }, 0);
        });
      })();
      </script>
    @endif
  </div>
@endif

<div class="sd-card">
  <h2>Bisherige Exporte</h2>
  <p class="sd-muted" style="margin-top:0">Die DATEV-Datei enthält nur die Buchungen. Mit „Belege (ZIP)“ bekommst du die zugehörigen Rechnungen als Dateien. Jede heißt nach Datum, Belegnummer aus der DATEV-Datei und Beleg-Nr. Dazu liegt eine Belegliste (Belegliste.csv) für Excel in der ZIP-Datei. So findet der Steuerberater zu jeder Buchung den Beleg.</p>
  <div class="sd-table-wrap">
    <table class="sd">
      <thead>
        <tr><th>Nr.</th><th>Erstellt</th><th>Belege vom</th><th>Eigentümer</th><th class="dv-num">Buchungen</th><th class="dv-num">Saldo</th><th></th></tr>
      </thead>
      <tbody>
        @forelse ($exports as $x)
          <tr>
            <td>{{ $x->id }}@if (($x->kind ?? 'belege') === 'mieten')<br><span class="sd-muted">Mieten</span>@endif</td>
            <td style="white-space:nowrap">{{ $x->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}@if ($x->creator) <span class="sd-muted">von {{ $x->creator->name }}</span>@endif</td>
            <td style="white-space:nowrap">{{ $x->period_from->format('d.m.Y') }} bis {{ $x->period_to->format('d.m.Y') }}</td>
            <td>{{ $x->owner ? $x->owner->name : 'Alle' }}</td>
            <td class="dv-num">{{ $x->entry_count }}</td>
            <td class="dv-num">{{ \App\Support\Money::format($x->total_amount) }}</td>
            <td style="white-space:nowrap">
              <a class="sd-btn sec small" href="{{ route('datev.download', $x) }}">DATEV-Datei</a>
              @if (($x->kind ?? 'belege') !== 'mieten')
                <a class="sd-btn sec small" href="{{ route('datev.receipts', $x) }}">Belege (ZIP)</a>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="sd-muted">Noch kein Export erstellt.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>
@endsection
