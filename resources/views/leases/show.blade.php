@extends('layouts.app')
@section('title', 'Mietvertrag')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Rent\Mieten;
  $isAdmin = auth()->user()->isAdmin();
  $vat = (float) $lease->vat_rate;
  $gross = fn ($net) => round($net + round($net * $vat) / 100, 2);
  $suggestAmount = $balance['overdue'] > 0 ? $balance['overdue'] : ($current ? $gross($current->netTotal()) : 0);
@endphp
<div class="sd-head">
  <h1>{{ $lease->unit?->label }} – {{ $lease->tenant?->display_name }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('leases.edit', $lease) }}">Bearbeiten</a>
    <a class="sd-btn sec" href="{{ route('leases.rent.create', $lease) }}">Mietänderung erfassen</a>
    <a class="sd-btn sec" href="{{ route('leases.index') }}">Alle Verträge</a>
  </div>
</div>

<div class="rt-stats">
  <div class="rt-stat">
    @if ($balance['balance'] > 0.004)
      <strong class="rt-late">{{ Money::format($balance['balance']) }}</strong><span>Rückstand gesamt</span>
    @elseif ($balance['balance'] < -0.004)
      <strong class="rt-ok">{{ Money::format(-$balance['balance']) }}</strong><span>Guthaben des Mieters</span>
    @else
      <strong class="rt-ok">0,00 €</strong><span>Konto ausgeglichen</span>
    @endif
  </div>
  <div class="rt-stat"><strong @class(['rt-late' => $balance['overdue'] > 0.004])>{{ Money::format(max(0, $balance['overdue'])) }}</strong><span>davon überfällig</span></div>
  <div class="rt-stat"><strong>{{ $current ? Money::format($gross($current->netTotal())) : '–' }}</strong><span>Miete monatlich{{ $vat > 0 ? ' brutto' : '' }}</span></div>
  <div class="rt-stat"><strong>{{ Money::format($balance['paid']) }}</strong><span>bisher gezahlt</span></div>
</div>

<div class="sd-card">
  <h2>Vertrag</h2>
  <dl class="sd-dl">
    <dt>Einheit</dt><dd>{{ $lease->unit?->label }}, <a href="{{ route('properties.show', $lease->unit?->property_id) }}">{{ $lease->unit?->property?->name }}</a></dd>
    <dt>Eigentümer</dt><dd>{{ $lease->owner?->name }}</dd>
    <dt>Mieter</dt><dd><a href="{{ route('tenants.show', $lease->tenant_id) }}">{{ $lease->tenant?->display_name }}</a></dd>
    <dt>Vertragsart</dt><dd>{{ $lease->typeLabel() }}</dd>
    <dt>Laufzeit</dt><dd>ab {{ $lease->start_date->format('d.m.Y') }}{{ $lease->end_date ? ' bis '.$lease->end_date->format('d.m.Y') : ', unbefristet' }} ({{ $lease->stateLabel() }})</dd>
    <dt>Fälligkeit</dt><dd>{{ $lease->dueLabel() }}</dd>
    <dt>Umsatzsteuer</dt><dd>{{ Mieten::vatLabel($lease->vat_rate) }}</dd>
    <dt>Mietanpassung</dt><dd>{{ $lease->adjustmentLabel() }}@if ($lease->adjustment_type === 'index' && $lease->index_base_value !== null), Ausgangswert {{ number_format((float) $lease->index_base_value, 1, ',', '') }} ({{ $lease->index_base_month?->format('m/Y') }})@endif</dd>
    <dt>Kaution</dt><dd>{{ $lease->depositLabel() }}</dd>
    @if ($lease->note)
      <dt>Notiz</dt><dd style="white-space:pre-line">{{ $lease->note }}</dd>
    @endif
  </dl>
</div>

<div class="sd-card">
  <h2>Mietstand und Verlauf</h2>
  <div class="sd-table-wrap">
    <table class="sd">
      <thead><tr><th>Gilt ab</th><th>Art</th><th>Bestandteile{{ $vat > 0 ? ' (netto)' : '' }}</th><th class="rt-num">Summe{{ $vat > 0 ? ' netto' : '' }}</th>@if ($vat > 0)<th class="rt-num">brutto</th>@endif<th></th></tr></thead>
      <tbody>
        @foreach ($lease->rents as $i => $r)
          <tr>
            <td style="white-space:nowrap">{{ $r->valid_from->format('d.m.Y') }}@if ($r->valid_from->format('Y-m-d') > $today)<br><span class="rt-soon">kommt</span>@elseif ($current && (int) $current->id === (int) $r->id)<br><span class="rt-ok">gilt jetzt</span>@endif</td>
            <td>{{ $r->reasonLabel() }}@if ($r->index_value !== null)<br><span class="sd-muted">Index {{ number_format((float) $r->index_value, 1, ',', '') }} ({{ $r->index_month?->format('m/Y') }})</span>@endif
              @if ($r->note)<br><span class="sd-muted">{{ $r->note }}</span>@endif</td>
            <td>{{ $r->partsLabel() }}</td>
            <td class="rt-num">{{ Money::format($r->netTotal()) }}</td>
            @if ($vat > 0)<td class="rt-num">{{ Money::format($gross($r->netTotal())) }}</td>@endif
            <td>
              @if ($i > 0)
                <form class="sd-inline" method="POST" action="{{ route('leases.rent.destroy', [$lease, $r]) }}" onsubmit="return confirm('Diese Mietänderung wirklich entfernen? Betroffene Monate werden neu berechnet.')">
                  @csrf @method('DELETE')
                  <button class="sd-btn danger small" type="submit">Entfernen</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @if ($lease->adjustment_type === 'index')
    <form method="GET" action="{{ route('leases.rent.create', $lease) }}" class="rt-filter" style="margin:.8rem 0 0">
      <label for="ix" style="margin:0">Indexanpassung prüfen: aktueller Indexwert</label>
      <input id="ix" name="index" inputmode="decimal" placeholder="z. B. 124,3" style="width:7rem" required>
      <input name="index_monat" type="month" aria-label="Monat des Indexwerts" required>
      <button class="sd-btn sec small" type="submit">Neue Miete berechnen</button>
    </form>
  @endif
</div>

<div class="sd-card" id="zahlung">
  <h2>Zahlung erfassen</h2>
  <form method="POST" action="{{ route('leases.payments.store', $lease) }}">
    @csrf
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-paid_on">Zahldatum *</label>
        <input id="f-paid_on" name="paid_on" type="date" required max="{{ $today }}" value="{{ old('paid_on', $today) }}">
      </div>
      @include('partials.sd-field', ['name' => 'amount', 'label' => 'Betrag', 'required' => true, 'inputmode' => 'decimal', 'value' => Money::input($suggestAmount), 'hint' => 'Für eine Rücklastschrift oder Erstattung ein Minus davor setzen.'])
      @include('partials.sd-select', ['name' => 'method', 'label' => 'Zahlungsart', 'required' => true, 'noBlank' => true, 'options' => Mieten::METHODS, 'value' => 'ueberweisung'])
      @include('partials.sd-field', ['name' => 'reference', 'label' => 'Verwendungszweck', 'hint' => 'optional'])
    </div>
    <button class="sd-btn" type="submit">Zahlung speichern</button>
    <span class="sd-muted">Die Zahlung wird automatisch den ältesten offenen Monaten zugeordnet.</span>
  </form>
</div>

<div class="sd-card">
  <h2>Mieterkonto</h2>
  @if (count($statement) === 0)
    <p class="sd-muted" style="margin:0">Noch keine Buchungen. Die erste Soll-Miete entsteht am Monatsersten nach Vertragsbeginn.</p>
  @else
    <p class="sd-muted" style="margin-top:0">Neueste Buchung oben. Saldo positiv = Rückstand, negativ = Guthaben.</p>
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Datum</th><th>Vorgang</th><th class="rt-num">Soll</th><th class="rt-num">Gezahlt</th><th class="rt-num">Saldo</th><th>Stand</th></tr></thead>
        <tbody>
          @foreach (array_reverse($statement) as $line)
            <tr>
              <td style="white-space:nowrap">{{ \Carbon\Carbon::parse($line['date'])->format('d.m.Y') }}</td>
              <td>{{ $line['text'] }}
                @if ($line['kind'] === 'soll')<br><span class="sd-muted">fällig {{ $line['model']->due_date->format('d.m.Y') }}@if ((float) $line['model']->vat_amount > 0), darin USt {{ Money::format($line['model']->vat_amount) }}@endif</span>@endif
                @if ($line['kind'] === 'zahlung' && $line['model']->reference)<br><span class="sd-muted">{{ $line['model']->reference }}</span>@endif
              </td>
              <td class="rt-num">{{ $line['debit'] !== null ? Money::format($line['debit']) : '' }}</td>
              <td class="rt-num">{{ $line['credit'] !== null ? Money::format($line['credit']) : '' }}</td>
              <td class="rt-num">{{ Money::format($line['balance']) }}</td>
              <td>
                @if ($line['kind'] === 'soll')
                  @switch($line['model']->state($today))
                    @case('bezahlt') <span class="rt-ok">bezahlt</span> @break
                    @case('ueberfaellig') <span class="rt-late">offen {{ Money::format($line['model']->openAmount()) }}</span> @break
                    @case('teilweise') <span class="rt-part">teilweise, offen {{ Money::format($line['model']->openAmount()) }}</span> @break
                    @default <span class="rt-soon">noch nicht fällig</span>
                  @endswitch
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="sd-card" id="uebergabe">
  <h2>Übergabeprotokolle</h2>
  @php($hos = \App\Models\HandoverProtocol::query()->where('lease_id', $lease->id)->orderBy('handover_on')->get())
  @if ($hos->isEmpty())
    <p class="sd-muted" style="margin-top:0">Noch kein Protokoll. Am besten bei der Übergabe direkt am Tablet ausfüllen: Räume, Zählerstände, Schlüssel, Fotos und Unterschriften.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Art</th><th>Datum</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach ($hos as $ho)
            <tr>
              <td>{{ \App\Models\HandoverProtocol::KINDS[$ho->kind] }}</td>
              <td>{{ $ho->handover_on->format('d.m.Y') }}</td>
              <td>{{ $ho->isDraft() ? 'Entwurf' : 'Abgeschlossen' }}</td>
              <td><a class="sd-btn sec small" href="{{ $ho->isDraft() ? route('handover.edit', $ho) : route('handover.show', $ho) }}">{{ $ho->isDraft() ? 'Weiter ausfüllen' : 'Öffnen' }}</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
  <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.8rem">
    <form method="POST" action="{{ route('handover.store', $lease) }}" class="sd-inline">@csrf<input type="hidden" name="kind" value="einzug"><button class="sd-btn small" type="submit">Einzugsprotokoll</button></form>
    <form method="POST" action="{{ route('handover.store', $lease) }}" class="sd-inline">@csrf<input type="hidden" name="kind" value="auszug"><button class="sd-btn sec small" type="submit">Auszugsprotokoll</button></form>
  </div>
</div>

<div class="sd-card" id="vertragsdokument">
  <h2>Vertragsdokument</h2>
  @php($docs = \App\Models\LeaseContract::query()->where('lease_id', $lease->id)->orderByDesc('id')->get())
  @if ($docs->isEmpty())
    <p class="sd-muted" style="margin-top:0">Noch kein Vertrag erstellt. Das CRM erzeugt den Vertragstext aus den Daten dieses Mietvertrags, zum Ausdrucken oder zum Unterschreiben am Tablet.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Nr.</th><th>Erstellt</th><th>Status</th><th></th></tr></thead>
        <tbody>
          @foreach ($docs as $d)
            <tr>
              <td>{{ $d->id }}</td>
              <td>{{ $d->created_at->format('d.m.Y') }}</td>
              <td>{{ $d->statusLabel() }}{{ $d->isSigned() && $d->signed_at ? ' am '.$d->signed_at->format('d.m.Y') : '' }}</td>
              <td><a class="sd-btn sec small" href="{{ route('contracts.show', $d) }}">Öffnen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
  <p style="margin:.8rem 0 0"><a class="sd-btn small" href="{{ route('contracts.create', $lease) }}">{{ $docs->isEmpty() ? 'Vertrag erstellen' : 'Neuen Vertrag erstellen' }}</a></p>
</div>

<div class="sd-card" id="mahnungen">
  <h2>Mahnungen</h2>
  @php($dnOverdue = (float) $balance['overdue'])
  @if ($notices->isEmpty())
    <p class="sd-muted" style="margin-top:0">Für diesen Vertrag wurde noch nichts angemahnt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Datum</th><th>Stufe</th><th class="rt-num">Forderung</th><th>Frist</th><th></th></tr></thead>
        <tbody>
          @foreach ($notices as $n)
            <tr>
              <td>{{ $n->issued_on->format('d.m.Y') }}</td>
              <td>{{ $n->levelLabel() }}@if ($n->isCancelled()) <span class="sd-muted">(storniert)</span>@endif</td>
              <td class="rt-num">{{ \App\Support\Money::format($n->totalAmount()) }}</td>
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
  @if ($dnOverdue > 0.004)
    @php($dnLast = $notices->first(fn ($n) => ! $n->isCancelled()))
    @php($dnNext = $dnLast && $dnLast->pay_by->format('Y-m-d') < $today ? min(3, $dnLast->level + 1) : ($dnLast ? $dnLast->level : 1))
    <form method="POST" action="{{ route('dunning.store') }}" style="margin-top:.8rem">
      @csrf
      <input type="hidden" name="leases[]" value="{{ $lease->id }}">
      <input type="hidden" name="back" value="vertrag">
      <p style="margin:0 0 .4rem">Überfällig: <strong class="rt-late">{{ \App\Support\Money::format($dnOverdue) }}</strong></p>
      <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-start">
        <select name="level[{{ $lease->id }}]" aria-label="Stufe" style="padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit">
          @foreach (\App\Models\DunningNotice::LEVELS as $lv => $label)
            <option value="{{ $lv }}" @selected($lv === $dnNext)>{{ $label }}</option>
          @endforeach
        </select>
        <textarea name="note" rows="2" maxlength="2000" placeholder="Zusätzlicher Text im Schreiben (freiwillig)" style="flex:1 1 18rem;min-width:0;padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit"></textarea>
        <button class="sd-btn" type="submit">Schreiben erstellen</button>
      </div>
      @if ($dnLast && $dnLast->pay_by->format('Y-m-d') >= $today)
        <p class="sd-muted" style="margin:.4rem 0 0">Die Frist aus der {{ $dnLast->levelLabel() }} vom {{ $dnLast->issued_on->format('d.m.Y') }} läuft noch bis {{ $dnLast->pay_by->format('d.m.Y') }}.</p>
      @endif
    </form>
  @endif
</div>

<div class="sd-card" id="zinsen">
  <h2>Verzugszinsen und Raten</h2>
  @php($zPlan = \App\Models\InstallmentPlan::query()->where('lease_id', $lease->id)->orderByDesc('id')->get())
  @php($zCalc = $balance['overdue'] > 0.004 ? \App\Support\Interest\Verzugszinsen::calculate($lease, \App\Support\Interest\Verzugszinsen::lastBookedUntil($lease), date('Y-m-d')) : null)
  @if ($zCalc && $zCalc['total'] > 0)
    <p style="margin-top:0">Aufgelaufene Verzugszinsen bis heute (noch nicht gebucht): <strong>{{ \App\Support\Money::format($zCalc['total']) }}</strong></p>
  @else
    <p class="sd-muted" style="margin-top:0">Zurzeit keine offenen Verzugszinsen.</p>
  @endif
  @foreach ($zPlan as $zp)
    <p style="margin:.3rem 0"><a href="{{ route('installments.show', $zp) }}">Ratenvereinbarung vom {{ $zp->agreed_on->format('d.m.Y') }}</a> über {{ \App\Support\Money::format($zp->debt) }} · {{ $zp->statusLabel() }}</p>
  @endforeach
  <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.6rem">
    <a class="sd-btn sec small" href="{{ route('interest.index', $lease) }}">Zinsberechnung</a>
    <a class="sd-btn sec small" href="{{ route('installments.create', $lease) }}">Ratenvereinbarung anlegen</a>
  </div>
</div>

@if ($payments->isNotEmpty())
  <div class="sd-card">
    <h2>Zahlungen</h2>
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Datum</th><th class="rt-num">Betrag</th><th>Art</th><th>Deckt</th><th></th></tr></thead>
        <tbody>
          @foreach ($payments as $p)
            <tr>
              <td style="white-space:nowrap">{{ $p->paid_on->format('d.m.Y') }}</td>
              <td class="rt-num">{{ Money::format($p->amount) }}</td>
              <td>{{ $p->methodLabel() }}@if ($p->source === 'bank')<br><span class="sd-muted">aus Kontoauszug</span>@endif @if ($p->note)<br><span class="sd-muted">{{ $p->note }}</span>@endif</td>
              <td>
                @forelse ($p->allocations as $a)
                  {{ $a->charge?->periodLabel() }}: {{ Money::format($a->amount) }}<br>
                @empty
                  @if ((float) $p->amount > 0)<span class="sd-muted">nichts zugeordnet (Guthaben oder durch Rückbuchung aufgehoben)</span>@else<span class="sd-muted">mit früheren Zahlungen verrechnet</span>@endif
                @endforelse
                @if ((float) $p->amount > 0 && (float) $p->allocated_amount < (float) $p->amount && $p->allocations->isNotEmpty())
                  <span class="sd-muted">Rest {{ Money::format((float) $p->amount - (float) $p->allocated_amount) }} als Guthaben</span>
                @endif
              </td>
              <td>
                @if ($isAdmin)
                  <form class="sd-inline" method="POST" action="{{ route('payments.destroy', $p) }}" onsubmit="return confirm('Diese Zahlung wirklich stornieren?')">
                    @csrf @method('DELETE')
                    <button class="sd-btn danger small" type="submit">Stornieren</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

@php($persons = \App\Models\LeasePerson::query()->where('lease_id', $lease->id)->orderBy('valid_from')->get())
<div class="sd-card">
  <h2>Personen im Haushalt</h2>
  <p class="sd-muted" style="margin-top:0">Wird für die Nebenkostenabrechnung gebraucht, wenn Kosten nach Personen verteilt werden. Bei Änderungen (Zuzug, Auszug) einen neuen Eintrag ab dem Datum anlegen.</p>
  @if ($persons->isNotEmpty())
    <ul style="margin:0 0 .6rem 1.2rem;padding:0">
      @foreach ($persons as $pp)
        <li>ab {{ $pp->valid_from->format('d.m.Y') }}: {{ $pp->persons }} Person{{ $pp->persons === 1 ? '' : 'en' }}
          <form class="sd-inline" method="POST" action="{{ route('leases.persons.destroy', [$lease, $pp]) }}">@csrf @method('DELETE')<button class="sd-btn danger small" type="submit" style="padding:.1rem .4rem" aria-label="Eintrag entfernen">×</button></form>
        </li>
      @endforeach
    </ul>
  @endif
  <form class="rt-pay" method="POST" action="{{ route('leases.persons.store', $lease) }}">
    @csrf
    <span>ab</span><input type="date" name="valid_from" value="{{ $persons->isEmpty() ? $lease->start_date->format('Y-m-d') : date('Y-m-d') }}" required aria-label="ab Datum">
    <input type="number" name="persons" min="0" max="20" value="{{ $persons->last()?->persons ?? 1 }}" style="width:5rem" required aria-label="Personen">
    <span>Personen</span>
    <button class="sd-btn sec small" type="submit">Speichern</button>
  </form>
</div>

@if ($payers->isNotEmpty())
  <div class="sd-card">
    <h2>Bekannte Zahlerkonten</h2>
    <p class="sd-muted" style="margin-top:0">Gutschriften von diesen Konten schlägt der Kontoauszug-Import automatisch für diesen Vertrag vor.</p>
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <tbody>
          @foreach ($payers as $payer)
            <tr>
              <td>{{ $payer->name ?: '–' }}</td>
              <td>{{ $payer->maskedIban() }}</td>
              <td>
                <form class="sd-inline" method="POST" action="{{ route('leases.payers.destroy', [$lease, $payer]) }}" onsubmit="return confirm('Dieses Zahlerkonto vergessen?')">
                  @csrf @method('DELETE')
                  <button class="sd-btn sec small" type="submit">Vergessen</button>
                </form>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

@include('documents.panel', ['docType' => 'lease', 'docId' => $lease->id])

@if ($isAdmin)
  <div class="sd-card">
    <h2>Vertrag löschen</h2>
    <p class="sd-muted">Nur für versehentlich angelegte Verträge ohne Zahlungen. Bei Auszug stattdessen unter „Bearbeiten“ ein Vertragsende eintragen.</p>
    <form method="POST" action="{{ route('leases.destroy', $lease) }}" onsubmit="return confirm('Diesen Mietvertrag wirklich löschen?')">
      @csrf @method('DELETE')
      <button class="sd-btn danger" type="submit">Vertrag löschen</button>
    </form>
  </div>
@endif
@endsection
