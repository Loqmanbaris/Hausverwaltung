@extends('layouts.app')
@section('title', 'Kaution')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Models\Deposit;
  use App\Support\Kaution\Kaution;
  $eur = fn ($c) => number_format($c / 100, 2, ',', '.').' €';
  $box = 'padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit';
  $ended = $lease->end_date !== null && $lease->end_date->format('Y-m-d') < date('Y-m-d');
  $lines = $form['lines'] ?? [];
  while (count($lines) < 3) { $lines[] = ['reason' => '', 'amount' => '']; }
@endphp
<div class="sd-head">
  <h1>Kaution</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('leases.show', $lease) }}">Zum Mietvertrag</a><a class="sd-btn sec" href="{{ route('deposit.index') }}">Alle Kautionen</a></div>
</div>
<p class="sd-muted">{{ $lease->title() }}</p>
@foreach (['entry', 'settle'] as $k)@if ($errors->has($k))<div class="sd-err">{{ $errors->first($k) }}</div>@endif @endforeach

<div class="sd-card">
  <dl class="sd-dl">
    <dt>Vereinbart</dt><dd>{{ $eur($s['agreed']) }}@if ($s['too_high']) <span class="sd-badge" style="background:#fffbeb;color:#92400e">mehr als drei Kaltmieten (§ 551 BGB)</span>@endif</dd>
    <dt>Eingezahlt</dt><dd>{{ $eur($s['paid']) }}@if ($s['overdue'] > 0) <span class="sd-badge" style="background:#fef2f2;color:#b91c1c">{{ $eur($s['overdue']) }} überfällig</span>@elseif ($s['open'] > 0 && $s['next']) <span class="sd-muted">· nächste Rate {{ $eur($s['next']['cents']) }} am {{ \Carbon\Carbon::parse($s['next']['due_on'])->format('d.m.Y') }}</span>@endif</dd>
    <dt>Zinsen</dt><dd>{{ $eur($s['interest']) }}</dd>
    @if ($s['withheld'] > 0)<dt>Einbehalten</dt><dd>{{ $eur($s['withheld']) }}</dd>@endif
    @if ($s['returned'] > 0)<dt>Ausgezahlt</dt><dd>{{ $eur($s['returned']) }}</dd>@endif
    <dt>Bestand</dt><dd><strong>{{ $eur($s['held']) }}</strong></dd>
  </dl>
  @if ($s['agreed'] > 0 && count($s['schedule']) > 1)
    <p class="sd-muted" style="margin:.6rem 0 0">Raten: @foreach ($s['schedule'] as $r){{ \Carbon\Carbon::parse($r['due_on'])->format('d.m.Y') }}: {{ $eur($r['cents']) }}{{ ! $loop->last ? ' · ' : '' }}@endforeach</p>
  @endif
  @if ($lease->deposit_amount !== null && $lease->deposit_received_on && $entries->isEmpty())
    <form method="POST" action="{{ route('deposit.import', $lease) }}" style="margin-top:.6rem">@csrf<button class="sd-btn sec small" type="submit">Zahlung aus dem Mietvertrag übernehmen ({{ $eur(\App\Support\Rent\Erhoehung\Regeln::cents($lease->deposit_amount)) }} am {{ $lease->deposit_received_on->format('d.m.Y') }})</button></form>
  @endif
</div>

<div class="sd-card">
  <h2>Buchungen</h2>
  @if ($entries->isNotEmpty())
    <div class="sd-table-wrap"><table class="sd"><tbody>
      @foreach ($entries as $e)
        <tr><td>{{ $e->entry_on->format('d.m.Y') }}</td><td>{{ $e->kindLabel() }}@if ($e->note)<br><span class="sd-muted">{{ $e->note }}</span>@endif</td>
          <td class="rt-num">{{ in_array($e->kind, ['einzahlung', 'zinsen'], true) ? '+' : '−' }} {{ number_format((float) $e->amount, 2, ',', '.') }} €</td>
          <td>@if (! $s['settled'])<form method="POST" action="{{ route('deposit.entry.destroy', [$lease, $e]) }}" onsubmit="return confirm('Buchung löschen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Löschen</button></form>@endif</td></tr>
      @endforeach
    </tbody></table></div>
  @endif
  @unless ($s['settled'])
    <form method="POST" action="{{ route('deposit.entry.store', $lease) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin-top:.6rem">
      @csrf
      <div class="sd-field" style="margin:0"><label for="f-on" class="sd-muted">Datum</label><input id="f-on" type="date" name="entry_on" required value="{{ date('Y-m-d') }}" style="{{ $box }}"></div>
      <div class="sd-field" style="margin:0"><label for="f-kind" class="sd-muted">Art</label><select id="f-kind" name="kind" style="{{ $box }}">@foreach (Deposit::KINDS as $k => $label)<option value="{{ $k }}">{{ $label }}</option>@endforeach</select></div>
      <div class="sd-field" style="margin:0"><label for="f-amt" class="sd-muted">Betrag (€)</label><input id="f-amt" name="amount" inputmode="decimal" required style="{{ $box }};width:8rem"></div>
      <div class="sd-field" style="margin:0;flex:1 1 10rem"><label for="f-note" class="sd-muted">Hinweis</label><input id="f-note" name="note" maxlength="190" style="{{ $box }};width:100%"></div>
      <button class="sd-btn" type="submit">Buchen</button>
    </form>
    <p class="sd-muted" style="margin:.4rem 0 0">Zinsen gehören dem Mieter und erhöhen die Kaution. Trage sie ein, wenn die Bank sie gutschreibt.</p>
  @endunless
</div>

<form method="POST" action="{{ route('deposit.update', $lease) }}" class="sd-card">
  @csrf @method('PUT')
  <h2>Anlage und Raten</h2>
  <div class="sd-grid">
    <div class="sd-field"><label for="f-form">Form</label><select id="f-form" name="form" style="{{ $box }};width:100%">@foreach (Deposit::FORMS as $k => $label)<option value="{{ $k }}" @selected(old('form', $d->form) === $k)>{{ $label }}</option>@endforeach</select></div>
    <div class="sd-field"><label for="f-inst">Bank oder Bürge</label><input id="f-inst" name="institution" maxlength="150" value="{{ old('institution', $d->institution) }}" style="{{ $box }};width:100%"></div>
    <div class="sd-field"><label for="f-acc">Konto (z. B. letzte Stellen)</label><input id="f-acc" name="account_note" maxlength="190" value="{{ old('account_note', $d->account_note) }}" style="{{ $box }};width:100%"></div>
  </div>
  <div class="sd-grid">
    <div class="sd-field"><label for="f-rate">Zinssatz (% im Jahr)</label><input id="f-rate" name="interest_rate" inputmode="decimal" value="{{ old('interest_rate', $d->interest_rate !== null ? number_format((float) $d->interest_rate, 3, ',', '') : '') }}" style="{{ $box }};width:100%"><small class="sd-muted">Für die Schätzung der Zinsen.</small></div>
    <div class="sd-field"><label for="f-n">Zahlung in Raten</label><select id="f-n" name="installments" style="{{ $box }};width:100%">@foreach ([1 => 'auf einmal', 2 => '2 gleiche Monatsraten', 3 => '3 gleiche Monatsraten'] as $k => $label)<option value="{{ $k }}" @selected((int) old('installments', $d->installments) === $k)>{{ $label }}</option>@endforeach</select><small class="sd-muted">Der Mieter darf die Kaution in bis zu drei gleichen Monatsraten zahlen, die erste bei Mietbeginn.</small></div>
  </div>
  <div class="sd-field"><label for="f-dn">Notiz</label><textarea id="f-dn" name="note" rows="2" maxlength="2000" style="{{ $box }};width:100%">{{ old('note', $d->note) }}</textarea></div>
  <button class="sd-btn" type="submit">Speichern</button>
  <p class="sd-muted" style="margin:.6rem 0 0">Die Kaution muss getrennt vom eigenen Vermögen und üblich verzinst angelegt sein (§ 551 Abs. 3 BGB).</p>
</form>

<div class="sd-card">
  <h2>Abrechnung beim Auszug</h2>
  @if ($s['settled'])
    <p style="margin-top:0">Abgerechnet am {{ $d->settled_on?->format('d.m.Y') }}.@if ((float) $d->claim > 0) Offene Nachforderung an den Mieter: <strong>{{ number_format((float) $d->claim, 2, ',', '.') }} €</strong>.@endif</p>
    <p style="display:flex;flex-wrap:wrap;gap:.5rem;margin:0"><a class="sd-btn" href="{{ route('deposit.letter', $lease) }}" target="_blank" rel="noopener">Abrechnung ansehen und drucken</a><a class="sd-btn sec" href="{{ route('deposit.pdf', $lease) }}">PDF</a>
      @if (auth()->user()->isAdmin())<form method="POST" action="{{ route('deposit.reopen', $lease) }}" onsubmit="return confirm('Abrechnung zurücknehmen?')">@csrf<button class="sd-btn sec" type="submit">Abrechnung zurücknehmen</button></form>@endif</p>
  @else
    @if (! $ended)<p class="sd-muted" style="margin-top:0">Der Vertrag läuft noch. Die Abrechnung machst du nach dem Auszug.</p>@else<p class="sd-muted" style="margin-top:0">Der Vermieter hat nach dem Auszug eine angemessene Zeit zur Prüfung, nach der Rechtsprechung meist bis zu sechs Monate (Richtwert, kein fester Termin). Im Mieterkonto sind {{ $eur($claims) }} offen.</p>@endif
    <form method="POST" action="{{ route('deposit.preview', $lease) }}">
      @csrf
      <div class="sd-field" style="max-width:14rem"><label for="f-so">Abrechnung zum</label><input id="f-so" type="date" name="settled_on" value="{{ $form['on'] ?? date('Y-m-d') }}" style="{{ $box }}"></div>
      <p class="sd-muted" style="margin:.2rem 0">Einbehalte (Grund und Betrag):</p>
      @foreach ($lines as $i => $line)
        <div style="display:flex;gap:.4rem;margin-bottom:.4rem"><input name="lines[{{ $i }}][reason]" placeholder="z. B. Schäden an der Wohnung" maxlength="190" value="{{ $line['reason'] ?? '' }}" style="{{ $box }};flex:1"><input name="lines[{{ $i }}][amount]" inputmode="decimal" placeholder="Betrag €" value="{{ $line['amount'] ?? '' }}" style="{{ $box }};width:8rem"></div>
      @endforeach
      @if ($claims > 0)<p class="sd-muted" style="margin:.2rem 0">Vorschlag: offene Posten aus dem Mieterkonto {{ $eur($claims) }}.</p>@endif
      <button class="sd-btn sec" type="submit">Berechnen</button>
    </form>
    @if ($preview)
      @if ($settleError)<div class="sd-err" style="margin-top:.6rem">{{ $settleError }}</div>@endif
      <dl class="sd-dl" style="margin-top:.8rem">
        <dt>Bestand (Kaution und Zinsen)</dt><dd>{{ $eur($preview['held']) }}</dd>
        <dt>Einbehalte</dt><dd>− {{ $eur($preview['withheld']) }}</dd>
        <dt>Auszahlung an den Mieter</dt><dd><strong>{{ $eur($preview['payout']) }}</strong></dd>
        @if ($preview['claim'] > 0)<dt>Nachforderung an den Mieter</dt><dd><strong>{{ $eur($preview['claim']) }}</strong></dd>@endif
      </dl>
      @unless ($settleError)
        <form method="POST" action="{{ route('deposit.settle', $lease) }}" onsubmit="return confirm('Kaution jetzt abrechnen? Die Buchungen werden angelegt.')">
          @csrf
          <input type="hidden" name="settled_on" value="{{ $form['on'] }}">
          @foreach ($form['lines'] as $i => $line)<input type="hidden" name="lines[{{ $i }}][reason]" value="{{ $line['reason'] ?? '' }}"><input type="hidden" name="lines[{{ $i }}][amount]" value="{{ $line['amount'] ?? '' }}">@endforeach
          <button class="sd-btn" type="submit">Kaution abrechnen</button>
        </form>
      @endunless
    @endif
  @endif
</div>
@endsection
