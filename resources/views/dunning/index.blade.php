@extends('layouts.app')
@section('title', 'Mahnwesen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Models\DunningNotice;
  $proposals = $rows->where('state', 'vorschlag');
  $others = $rows->where('state', '!=', 'vorschlag');
  $stateText = [
      'frist' => 'Frist läuft',
      'stufe3' => 'Letzte Mahnung abgelaufen',
      'wartet' => 'Noch keine Erinnerung (Karenzzeit)',
      'gering' => 'Unter dem Mindestbetrag',
      'raten' => 'Ratenvereinbarung wird eingehalten',
  ];
@endphp
<style>
.dn-level{display:inline-block;border-radius:1rem;padding:.05rem .55rem;font-size:.85rem;font-weight:600;white-space:nowrap}
.dn-l1{background:#eef4fb;color:var(--ink)}
.dn-l2{background:#fff4e5;color:#8a5a12}
.dn-l3{background:#fdecec;color:#b42318}
.dn-off{background:#f1f3f5;color:#667085;text-decoration:line-through}
.dn-hint{display:block;color:#b42318;font-size:.88rem;margin-top:.2rem}
.dn-sel{padding:.35rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;background:#fff;max-width:100%}
.dn-new{background:#ecfdf3;border:1px solid #abefc6;border-radius:.6rem;padding:.7rem 1rem;margin:0 0 1rem}
.dn-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(15rem,1fr));gap:.8rem}
.dn-grid label{display:block;font-size:.9rem;color:#475467;margin-bottom:.2rem}
.dn-grid input{width:100%;padding:.45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit}
</style>

<div class="sd-head">
  <h1>Mahnwesen</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('rent.open') }}">Offene Posten</a>
    <a class="sd-btn sec" href="{{ route('bank.transactions') }}">Kontoumsätze</a>
  </div>
</div>
<p class="sd-muted">Zahlungserinnerung, Mahnung und letzte Mahnung aus den offenen Posten. Vorgeschlagen wird die jeweils nächste Stufe, sobald die Frist des letzten Schreibens abgelaufen ist. Bitte vorher die neuesten Kontoauszüge einlesen, damit niemand gemahnt wird, der schon bezahlt hat.</p>

@if ($created->isNotEmpty())
  <div class="dn-new">
    <strong>{{ $created->count() === 1 ? 'Ein Schreiben ist' : $created->count().' Schreiben sind' }} erstellt.</strong>
    <a class="sd-btn small" href="{{ route('dunning.print.many', ['ids' => $created->implode(',')]) }}" target="_blank" rel="noopener">Jetzt drucken</a>
    <a class="sd-btn sec small" href="{{ route('mail.compose', ['art' => 'mahnung', 'ids' => $created->implode(',')]) }}">Per E-Mail senden</a>
  </div>
@endif

@if ($owners->count() > 1)
  <form class="rt-filter" method="GET" action="{{ route('dunning.index') }}">
    <select name="eigentuemer" aria-label="Eigentümer">
      <option value="">Alle Eigentümer</option>
      @foreach ($owners as $o)
        <option value="{{ $o->id }}" @selected($ownerId === (int) $o->id)>{{ $o->name }}</option>
      @endforeach
    </select>
    <button class="sd-btn sec small" type="submit">Anzeigen</button>
  </form>
@endif

<div class="sd-card">
  <h2>Vorschläge</h2>
  @if ($proposals->isEmpty())
    <p class="rt-ok" style="margin:0">Zurzeit ist kein Schreiben fällig.</p>
  @else
    <form method="POST" action="{{ route('dunning.store') }}">
      @csrf
      <div class="sd-table-wrap">
        <table class="sd rt-stack">
          <thead><tr><th><input type="checkbox" checked aria-label="Alle auswählen" onclick="document.querySelectorAll('.dn-cb').forEach(c => c.checked = this.checked)"></th><th>Einheit und Mieter</th><th>Offene Posten</th><th class="rt-num">Offen</th><th>Bisher</th><th>Stufe</th></tr></thead>
          <tbody>
            @foreach ($proposals as $r)
              @php($lease = $r['lease'])
              <tr>
                <td><input class="dn-cb" type="checkbox" name="leases[]" value="{{ $lease->id }}" checked aria-label="Auswählen"></td>
                <td><a href="{{ route('leases.show', $lease) }}#mahnungen">{{ $lease->unit?->label }}</a>, {{ $lease->unit?->property?->name }}<br>
                  <span class="sd-muted">{{ $lease->tenant?->display_name }} · {{ $lease->owner?->name }}</span>
                  @if ($r['hint'])<span class="dn-hint">{{ $r['hint'] }}</span>@endif
                </td>
                <td>
                  @foreach ($r['items'] as $c)
                    {{ $c->periodLabel() }}: {{ Money::format($c->openAmount()) }}<br>
                  @endforeach
                  <span class="sd-muted">älteste seit {{ $r['days'] }} Tag{{ $r['days'] === 1 ? '' : 'en' }}</span>
                </td>
                <td class="rt-num rt-late">{{ Money::format($r['open']) }}</td>
                <td>
                  @if ($r['last'])
                    {{ $r['last']->levelLabel() }} vom {{ $r['last']->issued_on->format('d.m.Y') }}<br><span class="sd-muted">Frist bis {{ $r['last']->pay_by->format('d.m.Y') }}</span>
                  @else
                    <span class="sd-muted">noch nichts</span>
                  @endif
                </td>
                <td>
                  <select class="dn-sel" name="level[{{ $lease->id }}]" aria-label="Stufe">
                    @foreach (DunningNotice::LEVELS as $lv => $label)
                      <option value="{{ $lv }}" @selected($lv === $r['level'])>{{ $label }}</option>
                    @endforeach
                  </select>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p style="margin:.8rem 0 0"><button class="sd-btn" type="submit">Ausgewählte Schreiben erstellen</button>
        <span class="sd-muted">Danach erscheint der Knopf „Jetzt drucken“. Gebühren werden ins Mieterkonto gebucht.</span></p>
    </form>
  @endif
</div>

@if ($others->isNotEmpty())
  <div class="sd-card">
    <h2>Weitere Rückstände</h2>
    <p class="sd-muted" style="margin-top:0">Für diese Verträge wird gerade kein Schreiben vorgeschlagen. Über den Mietvertrag kannst du trotzdem jederzeit eins erstellen.</p>
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Einheit und Mieter</th><th class="rt-num">Offen</th><th>Stand</th></tr></thead>
        <tbody>
          @foreach ($others as $r)
            @php($lease = $r['lease'])
            <tr>
              <td><a href="{{ route('leases.show', $lease) }}#mahnungen">{{ $lease->unit?->label }}</a>, {{ $lease->unit?->property?->name }}<br>
                <span class="sd-muted">{{ $lease->tenant?->display_name }}</span>
                @if ($r['hint'])<span class="dn-hint">{{ $r['hint'] }}</span>@endif
              </td>
              <td class="rt-num rt-late">{{ Money::format($r['open']) }}</td>
              <td>
                {{ $stateText[$r['state']] ?? '' }}
                @if ($r['state'] === 'frist' && $r['last'])
                  <br><span class="sd-muted">{{ $r['last']->levelLabel() }} vom {{ $r['last']->issued_on->format('d.m.Y') }}, Frist bis {{ $r['last']->pay_by->format('d.m.Y') }}</span>
                @elseif ($r['state'] === 'raten' && $r['plan'])
                  <br><a href="{{ route('installments.show', $r['plan']) }}">Ratenvereinbarung vom {{ $r['plan']->agreed_on->format('d.m.Y') }}</a>
                @elseif ($r['state'] === 'stufe3')
                  <br><span class="sd-muted">Nächste Schritte: gerichtliches Mahnverfahren, Kündigung oder Ratenvereinbarung prüfen.</span>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

<div class="sd-card">
  <h2>Verlauf</h2>
  @if ($history->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine Schreiben erstellt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Datum</th><th>Stufe</th><th>Einheit und Mieter</th><th class="rt-num">Forderung</th><th>Frist</th><th></th></tr></thead>
        <tbody>
          @foreach ($history as $n)
            <tr>
              <td>{{ $n->issued_on->format('d.m.Y') }}</td>
              <td><span @class(['dn-level', 'dn-l'.$n->level => ! $n->isCancelled(), 'dn-off' => $n->isCancelled()])>{{ $n->levelLabel() }}</span>
                @if ($n->isCancelled())<br><span class="sd-muted">storniert</span>@endif</td>
              <td><a href="{{ route('leases.show', $n->lease_id) }}#mahnungen">{{ $n->lease?->unit?->label }}</a>, {{ $n->lease?->unit?->property?->name }}<br><span class="sd-muted">{{ $n->lease?->tenant?->display_name }}</span></td>
              <td class="rt-num">{{ Money::format($n->totalAmount()) }}@if ((float) $n->fee > 0)<br><span class="sd-muted">inkl. {{ Money::format($n->fee) }} Gebühr</span>@endif</td>
              <td>{{ $n->pay_by->format('d.m.Y') }}</td>
              <td style="white-space:nowrap">
                <a class="sd-btn sec small" href="{{ route('dunning.print', $n) }}" target="_blank" rel="noopener">Drucken</a>
                @unless ($n->isCancelled())
                  <a class="sd-btn sec small" href="{{ route('mail.compose', ['art' => 'mahnung', 'ids' => $n->id]) }}">E-Mail</a>
                  <form method="POST" action="{{ route('dunning.cancel', $n) }}" style="display:inline" onsubmit="return confirm('Dieses Schreiben stornieren? Eine Gebühr wird aus dem Mieterkonto entfernt.')">
                    @csrf
                    <button class="sd-btn sec small" type="submit">Stornieren</button>
                  </form>
                @endunless
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="sd-card" id="basiszins">
  <h2>Basiszinssatz für Verzugszinsen</h2>
  <p class="sd-muted" style="margin-top:0">Die Deutsche Bundesbank gibt den Basiszinssatz (§ 247 BGB) jeweils zum 1. Januar und 1. Juli bekannt. Verzugszinsen: Basiszinssatz + 5 Prozentpunkte bei Wohnraum, + 9 Prozentpunkte bei Gewerbe.</p>
  @if ($missingRate)
    <div class="sd-err">Für das laufende Halbjahr ist noch kein Basiszinssatz eingetragen. Bitte unten ergänzen (Wert auf bundesbank.de unter „Basiszinssatz“).</div>
  @endif
  <div class="sd-table-wrap">
    <table class="sd" style="max-width:28rem">
      <thead><tr><th>gültig ab</th><th class="rt-num">Basiszinssatz</th><th></th></tr></thead>
      <tbody>
        @foreach (array_reverse($baseRates, true) as $from => $rate)
          <tr>
            <td>{{ \Carbon\Carbon::parse($from)->format('d.m.Y') }}</td>
            <td class="rt-num">{{ number_format($rate, 2, ',', '.') }} %</td>
            <td>
              @if (auth()->user()->isAdmin())
                <form method="POST" action="{{ route('interest.rates.destroy', $from) }}" class="sd-inline" onsubmit="return confirm('Eintrag entfernen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Entfernen</button></form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @if (auth()->user()->isAdmin())
    <form method="POST" action="{{ route('interest.rates.store') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin-top:.8rem">
      @csrf
      <div><label for="br-from" style="display:block;font-size:.9rem">gültig ab</label><input id="br-from" type="date" name="valid_from" required style="padding:.45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit"></div>
      <div><label for="br-rate" style="display:block;font-size:.9rem">Basiszinssatz in %</label><input id="br-rate" type="text" name="rate" inputmode="decimal" required placeholder="z. B. 1,52" style="width:8rem;padding:.45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit"></div>
      <button class="sd-btn sec" type="submit">Eintragen</button>
    </form>
  @endif
</div>

<div class="sd-card" id="einstellungen">
  <h2>Einstellungen</h2>
  <p class="sd-muted" style="margin-top:0">Gelten für alle Eigentümer. Bei Wohnraum dürfen nur tatsächliche Kosten (Porto, Papier) als Mahngebühr verlangt werden, die erste Zahlungserinnerung bleibt üblicherweise kostenlos. Im Zweifel 0 lassen.</p>
  @if (auth()->user()->isAdmin())
    <form method="POST" action="{{ route('dunning.settings') }}">
      @csrf
      @method('PUT')
      <div class="dn-grid">
        @foreach ($settings as $key => $s)
          <div>
            <label for="{{ $key }}">{{ $s['label'] }}{{ $s['type'] === 'money' ? ' (€)' : '' }}</label>
            <input id="{{ $key }}" name="{{ $key }}" type="text" inputmode="{{ $s['type'] === 'money' ? 'decimal' : 'numeric' }}" value="{{ old($key, $s['value']) }}">
          </div>
        @endforeach
      </div>
      <p style="margin:.8rem 0 0"><button class="sd-btn" type="submit">Speichern</button></p>
    </form>
  @else
    <ul style="margin:0">
      @foreach ($settings as $s)
        <li>{{ $s['label'] }}: {{ $s['value'] }}{{ $s['type'] === 'money' ? ' €' : '' }}</li>
      @endforeach
    </ul>
  @endif
</div>
@endsection
