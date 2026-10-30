@extends('layouts.app')
@section('title', 'Mieterhöhung')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Support\Money;
  use App\Support\Rent\Erhoehung\Regeln;
  $lease = $inc->lease;
  $d = $inc->data ?? [];
  $ownerCan = auth()->user()->isAdmin();
  $input = 'padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit';
  $needsConsent = $inc->needsConsent();
@endphp
<div class="sd-head">
  <h1>{{ $inc->kindLabel() }} ab {{ $inc->effective_from->format('d.m.Y') }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('rent_increase.index') }}">Alle Mieterhöhungen</a></div>
</div>
<p class="sd-muted"><a href="{{ route('leases.show', $lease) }}">{{ $lease?->title() }}</a> · Stand: <strong>{{ $inc->statusLabel() }}</strong></p>

<div class="sd-card">
  <dl class="sd-dl">
    <dt>Kaltmiete</dt><dd>{{ Money::format($inc->old_cold) }} → <strong>{{ Money::format($inc->new_cold) }}</strong> ({{ $inc->difference() >= 0 ? '+' : '' }}{{ Money::format($inc->difference()) }}, {{ number_format($inc->percent(), 2, ',', '') }} %)</dd>
    @if ($inc->area_sqm)<dt>Je m²</dt><dd>{{ Money::format((float) $inc->old_cold / (float) $inc->area_sqm) }} → {{ Money::format((float) $inc->new_cold / (float) $inc->area_sqm) }} bei {{ Regeln::num((float) $inc->area_sqm) }} m²</dd>@endif
    <dt>Schreiben vom</dt><dd>{{ $inc->notice_on->format('d.m.Y') }}</dd>
    <dt>Gilt ab</dt><dd>{{ $inc->effective_from->format('d.m.Y') }}</dd>
    @if ($inc->consent_until)<dt>Zustimmung bis</dt><dd>{{ $inc->consent_until->format('d.m.Y') }}</dd>@endif
    @if ($inc->suit_until)<dt>Klage auf Zustimmung bis</dt><dd>{{ $inc->suit_until->format('d.m.Y') }}</dd>@endif
    @if ($inc->decided_on)<dt>Entschieden am</dt><dd>{{ $inc->decided_on->format('d.m.Y') }}</dd>@endif
    @if ($inc->kind === 'vergleich')
      <dt>Begründung</dt><dd>{{ \App\Support\Rent\Erhoehung\Verfahren::BASIS_TYPES[$d['basis_type'] ?? ''] ?? '–' }}: {{ $d['source_name'] ?? '' }}</dd>
    @elseif ($inc->kind === 'modernisierung')
      <dt>Maßnahme</dt><dd>{{ $d['measure'] ?? '' }}</dd>
    @elseif ($inc->kind === 'index')
      <dt>Index</dt><dd>{{ isset($d['base_index']) ? number_format((float) $d['base_index'], 1, ',', '') : '–' }} → {{ isset($d['new_index']) ? number_format((float) $d['new_index'], 1, ',', '') : '–' }}@if (isset($d['percent'])) ({{ number_format((float) $d['percent'], 2, ',', '') }} %)@endif</dd>
    @endif
  </dl>
  @if (! empty($d['checks']))
    <details style="margin-top:.8rem"><summary>Ergebnis der Prüfung beim Anlegen</summary>@include('rentincrease._checks', ['checks' => $d['checks']])</details>
  @endif
</div>

<div class="sd-card">
  <h2>Schreiben</h2>
  <p style="margin:0 0 .6rem;display:flex;flex-wrap:wrap;gap:.5rem">
    <a class="sd-btn" href="{{ route('rent_increase.print', $inc) }}" target="_blank" rel="noopener">Drucken</a>
    <a class="sd-btn sec" href="{{ route('documents.pdf', ['art' => 'erhoehung', 'id' => $inc->id]) }}">PDF</a>
    <a class="sd-btn sec" href="{{ route('mail.compose', ['art' => 'erhoehung', 'ids' => $inc->id]) }}">Per E-Mail senden</a>
  </p>
  @if ($inc->isDraft())
    <form method="POST" action="{{ route('rent_increase.note', $inc) }}">
      @csrf @method('PUT')
      <label for="f-note" class="sd-muted">Zusatztext im Schreiben</label>
      <textarea id="f-note" name="note" rows="2" maxlength="3000" style="width:100%;{{ $input }}">{{ old('note', $inc->note) }}</textarea>
      <button class="sd-btn sec small" type="submit" style="margin-top:.4rem">Text speichern</button>
    </form>
  @elseif ($inc->note)
    <p class="sd-muted" style="margin:0">Zusatztext: {{ $inc->note }}</p>
  @endif
</div>

<div class="sd-card">
  <h2>Nächster Schritt</h2>
  @if ($inc->status === 'entwurf')
    <p class="sd-muted" style="margin-top:0">Verschicke das Schreiben per E-Mail (dann wird es automatisch als erklärt vermerkt) oder drucke es aus und vermerke hier den Tag, an dem es dem Mieter zugeht. Bei Post rechne ein bis zwei Tage ein. Stimmt der Zugang nicht mit dem Datum im Schreiben überein, rechnet das System die Fristen neu. Drucke dann bitte neu.</p>
    <form method="POST" action="{{ route('rent_increase.declare', $inc) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
      @csrf
      <div class="sd-field" style="margin:0"><label for="f-dc">Zugang beim Mieter am</label><input id="f-dc" type="date" name="notice_on" value="{{ $inc->notice_on->format('Y-m-d') }}" style="{{ $input }}"></div>
      <button class="sd-btn" type="submit">Als erklärt vermerken</button>
    </form>
  @elseif ($inc->status === 'erklaert' && $needsConsent)
    <p class="sd-muted" style="margin-top:0">Der Mieter muss zustimmen{{ $inc->consent_until ? ' (Frist bis '.$inc->consent_until->format('d.m.Y').')' : '' }}. Stimmt er zu, trage das Datum ein: Die neue Miete wird ab {{ $inc->effective_from->format('d.m.Y') }} in den Mietstand übernommen.@if ($inc->kind === 'vergleich') Zahlt er die erhöhte Miete mehrere Monate ohne Vorbehalt, kann das nach der Rechtsprechung als Zustimmung gelten. Trage sie dann hier ein.@endif</p>
    <div style="display:flex;flex-wrap:wrap;gap:1rem">
      <form method="POST" action="{{ route('rent_increase.consent', $inc) }}" style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div class="sd-field" style="margin:0"><label for="f-cd">Zugestimmt am</label><input id="f-cd" type="date" name="decided_on" value="{{ $today }}" style="{{ $input }}"></div>
        <button class="sd-btn" type="submit">Zustimmung vermerken</button>
      </form>
      <form method="POST" action="{{ route('rent_increase.reject', $inc) }}" style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
        @csrf
        <div class="sd-field" style="margin:0"><label for="f-rd">Abgelehnt am</label><input id="f-rd" type="date" name="decided_on" value="{{ $today }}" style="{{ $input }}"></div>
        <button class="sd-btn sec" type="submit">Als abgelehnt vermerken</button>
      </form>
    </div>
  @elseif ($inc->status === 'erklaert')
    <p class="sd-muted" style="margin-top:0">Diese Erhöhung braucht keine Zustimmung. Sie gilt ab {{ $inc->effective_from->format('d.m.Y') }}. @if ($inc->effective_from->format('Y-m-d') <= $today)Der Zeitpunkt ist erreicht: Übernimm die neue Miete jetzt in den Mietstand.@else Übernimm die neue Miete in den Mietstand, wenn der Zeitpunkt erreicht ist. Das System tut das nicht von selbst.@endif</p>
    <form method="POST" action="{{ route('rent_increase.adopt', $inc) }}" onsubmit="return confirm('Neue Kaltmiete {{ Money::format($inc->new_cold) }} ab {{ $inc->effective_from->format('d.m.Y') }} in den Mietstand übernehmen?')">
      @csrf
      <button class="sd-btn" type="submit">In den Mietstand übernehmen</button>
    </form>
  @elseif ($inc->status === 'abgelehnt')
    <p class="sd-muted" style="margin-top:0">Der Mieter hat nicht zugestimmt. Du kannst innerhalb von drei weiteren Monaten (bis {{ $inc->suit_until?->format('d.m.Y') ?? '–' }}) auf Zustimmung klagen. Dafür empfiehlt sich anwaltliche Hilfe. Stimmt er doch noch zu, kannst du das nachtragen.</p>
    <form method="POST" action="{{ route('rent_increase.consent', $inc) }}" style="display:flex;gap:.5rem;align-items:flex-end;flex-wrap:wrap">
      @csrf
      <div class="sd-field" style="margin:0"><label for="f-cd2">Zugestimmt am</label><input id="f-cd2" type="date" name="decided_on" value="{{ $today }}" style="{{ $input }}"></div>
      <button class="sd-btn sec" type="submit">Zustimmung nachtragen</button>
    </form>
  @elseif ($inc->status === 'wirksam')
    <p style="margin:0">Die neue Miete steht im Mietstand{{ $inc->rent ? ' (ab '.$inc->rent->valid_from->format('d.m.Y').')' : '' }}. <a href="{{ route('leases.show', $lease) }}">Zum Mietvertrag</a></p>
  @else
    <p class="sd-muted" style="margin:0">Zurückgezogen. Es ändert sich nichts an der Miete.</p>
  @endif
</div>

<div style="display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1rem">
  @if (in_array($inc->status, ['entwurf', 'erklaert', 'abgelehnt'], true))
    <form method="POST" action="{{ route('rent_increase.withdraw', $inc) }}" onsubmit="return confirm('Diese Mieterhöhung zurückziehen? Die Miete bleibt unverändert.')">@csrf<button class="sd-btn sec small" type="submit">Zurückziehen</button></form>
  @endif
  @if ($inc->isDraft())
    <form method="POST" action="{{ route('rent_increase.destroy', $inc) }}" onsubmit="return confirm('Entwurf endgültig löschen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Entwurf löschen</button></form>
  @endif
  @if ($inc->status === 'wirksam' && $inc->kind !== 'staffel' && $ownerCan)
    <form method="POST" action="{{ route('rent_increase.undo', $inc) }}" onsubmit="return confirm('Die Erhöhung rückgängig machen? Der Mietstand wird entfernt und die Soll-Mieten werden neu berechnet.')">@csrf<button class="sd-btn sec small" type="submit">Rückgängig machen (Administrator)</button></form>
  @endif
</div>
@endsection
