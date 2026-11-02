@extends('layouts.app')
@section('title', 'Interessent')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Models\Applicant;
  use App\Models\ApplicantViewing;
  use App\Support\Interessenten\Interessenten;
  $box = 'padding:.4rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit';
  $filled = $d && $d->isFilled();
  $showValue = fn ($k, $v) => match ($k) {
    'net_income' => number_format((float) $v, 2, ',', '.').' €',
    'move_in' => \Carbon\Carbon::parse($v)->format('d.m.Y'),
    'credit_ok' => Interessenten::CREDIT[$v] ?? $v,
    default => (string) $v,
  };
@endphp
<div class="sd-head">
  <h1>{{ $a->name() }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('applicants.edit', $a) }}">Bearbeiten</a>
    <a class="sd-btn sec" href="{{ route('applicants.index') }}">Alle Interessenten</a>
  </div>
</div>
<p class="sd-muted"><span class="sd-badge">{{ $a->statusLabel() }}</span> · {{ $a->unitLabel() }}</p>

<div class="sd-card">
  <dl class="sd-dl">
    <dt>E-Mail</dt><dd>{{ $a->email ?: '–' }}</dd>
    <dt>Telefon</dt><dd>@if ($a->phone)<a href="tel:{{ preg_replace('/[^0-9+]/', '', $a->phone) }}">{{ $a->phone }}</a>@else – @endif</dd>
    <dt>Personen</dt><dd>{{ $a->persons ?: '–' }}</dd>
    <dt>Gewünschter Einzug</dt><dd>{{ $a->move_in?->format('d.m.Y') ?: '–' }}</dd>
    <dt>Woher</dt><dd>{{ Applicant::SOURCES[$a->source] ?? '–' }}</dd>
    <dt>Eigentümer</dt><dd>{{ $a->owner?->name }}</dd>
    <dt>Daten werden gelöscht am</dt><dd>{{ $a->delete_after?->format('d.m.Y') ?: '–' }}</dd>
  </dl>
  @if ($a->message)<p style="white-space:pre-line;margin:.8rem 0 0"><strong>Nachricht:</strong> {{ $a->message }}</p>@endif
  @if ($a->note)<p style="white-space:pre-line;margin:.6rem 0 0" class="sd-muted"><strong>Notiz:</strong> {{ $a->note }}</p>@endif
</div>

<div class="sd-card">
  <h2>Besichtigung</h2>
  @if ($a->viewings->isNotEmpty())
    <div class="sd-table-wrap"><table class="sd"><tbody>
      @foreach ($a->viewings as $v)
        <tr>
          <td style="white-space:nowrap">{{ $v->starts_at->format('d.m.Y H:i') }} Uhr<br><span class="sd-muted">{{ $v->place }}</span></td>
          <td>
            <form method="POST" action="{{ route('applicants.viewing.update', $v) }}" style="display:flex;gap:.3rem;flex-wrap:wrap">@csrf @method('PUT')
              <select name="status" aria-label="Stand" style="{{ $box }}">@foreach (ApplicantViewing::STATUS as $k => $label)<option value="{{ $k }}" @selected($v->status === $k)>{{ $label }}</option>@endforeach</select>
              <button class="sd-btn sec small" type="submit">Speichern</button>
            </form>
          </td>
          <td style="white-space:nowrap">
            @if ($a->email)<a class="sd-btn sec small" href="{{ route('mail.compose', ['art' => 'int_termin', 'ids' => $a->id.','.$v->id]) }}">Termin per E-Mail</a>@endif
            <form method="POST" action="{{ route('applicants.viewing.destroy', $v) }}" style="display:inline" onsubmit="return confirm('Termin löschen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit">Löschen</button></form>
          </td>
        </tr>
      @endforeach
    </tbody></table></div>
  @endif
  <form method="POST" action="{{ route('applicants.viewing.store', $a) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin-top:.6rem">
    @csrf
    <div class="sd-field" style="margin:0"><label for="f-vs">Neuer Termin</label><input id="f-vs" type="datetime-local" name="starts_at" required style="{{ $box }}"></div>
    <div class="sd-field" style="margin:0;flex:1 1 12rem"><label for="f-vp">Treffpunkt</label><input id="f-vp" name="place" maxlength="190" placeholder="z. B. vor dem Haus" value="{{ $a->unit?->property?->addressLine() }}" style="{{ $box }};width:100%"></div>
    <button class="sd-btn" type="submit">Termin eintragen</button>
  </form>
</div>

<div class="sd-card">
  <h2>Selbstauskunft</h2>
  @if ($errors->has('disclosure'))<div class="sd-err">{{ $errors->first('disclosure') }}</div>@endif
  @if ($filled)
    <p class="sd-muted" style="margin-top:0">{{ $d->statusLabel() }} am {{ $d->submitted_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') }}.</p>
    <dl class="sd-dl">
      @foreach (Interessenten::FIELDS as $k => [$label])
        @continue(! isset($data[$k]) || $data[$k] === '')
        <dt>{{ $label }}</dt>
        <dd>{{ $showValue($k, $data[$k]) }}</dd>
      @endforeach
    </dl>
    @if ($ratio)<p class="sd-muted" style="margin:.6rem 0 0">Orientierung: Das Nettoeinkommen entspricht dem {{ number_format($ratio['factor'], 1, ',', '') }}-Fachen der Kaltmiete ({{ number_format($ratio['rent'], 2, ',', '.') }} € laut Inserat). Üblich ist mindestens das Dreifache. Die Entscheidung bleibt bei dir.</p>@endif
    <p style="margin:.6rem 0 0"><a class="sd-btn sec small" href="{{ route('applicants.disclosure.manual', $a) }}">Angaben ändern</a></p>
  @else
    @if ($d && $d->status === 'angefordert')
      <p class="sd-muted" style="margin-top:0">Angefordert am {{ $d->requested_at?->timezone('Europe/Berlin')->format('d.m.Y') }}.@if ($link) Der Link gilt bis {{ $d->token_expires_at?->timezone('Europe/Berlin')->format('d.m.Y') }}.@else Der Link ist abgelaufen.@endif</p>
      @if ($link)
        <div class="sd-field"><label for="f-link">Link für den Interessenten</label><input id="f-link" readonly value="{{ $link }}" onclick="this.select()" style="{{ $box }};width:100%"></div>
      @endif
    @else
      <p class="sd-muted" style="margin-top:0">Noch keine Angaben. Der Interessent kann sie online ausfüllen (Link per E-Mail) oder auf Papier. Alle Fragen sind freiwillig und auf das Nötige beschränkt.</p>
    @endif
    <div style="display:flex;flex-wrap:wrap;gap:.5rem">
      @if ($a->email)<a class="sd-btn" href="{{ route('mail.compose', ['art' => 'int_selbstauskunft', 'ids' => $a->id]) }}">Link per E-Mail senden</a>@endif
      @if ($d && $d->status === 'angefordert')
        <form method="POST" action="{{ route('applicants.disclosure.renew', $a) }}">@csrf<button class="sd-btn sec" type="submit">Neuen Link erzeugen</button></form>
      @else
        <form method="POST" action="{{ route('applicants.disclosure.request', $a) }}">@csrf<button class="sd-btn sec" type="submit">Nur Link erzeugen</button></form>
      @endif
      <a class="sd-btn sec" href="{{ route('applicants.disclosure.print', $a) }}" target="_blank" rel="noopener">Formular zum Ausdrucken</a>
      <a class="sd-btn sec" href="{{ route('applicants.disclosure.manual', $a) }}">Von Hand eintragen</a>
    </div>
  @endif
</div>

<div class="sd-card">
  <h2>Entscheidung</h2>
  @if (in_array($a->status, ['absage', 'zurueckgezogen'], true))
    <p class="sd-muted" style="margin-top:0">{{ $a->statusLabel() }} am {{ $a->decided_on?->format('d.m.Y') }}.@if ($a->reason) Interner Grund: {{ $a->reason }}@endif</p>
  @endif
  @if ($a->status === 'vertrag')
    <p style="margin-top:0">Als Mieter angelegt @if ($a->tenant): <a href="{{ route('tenants.show', $a->tenant) }}">{{ $a->tenant->display_name }}</a>@endif. <a href="{{ route('leases.create', array_filter(['einheit' => $a->unit_id, 'mieter' => $a->tenant_id])) }}">Mietvertrag erfassen</a></p>
  @else
    <div style="display:grid;gap:1rem">
      <div>
        <strong>Zusage</strong>
        <p style="margin:.3rem 0 0;display:flex;flex-wrap:wrap;gap:.5rem">
          <a class="sd-btn sec small" href="{{ route('applicants.letter', [$a, 'zusage']) }}" target="_blank" rel="noopener">Schreiben ansehen</a>
          <a class="sd-btn sec small" href="{{ route('documents.pdf', ['art' => 'int_zusage', 'id' => $a->id]) }}">PDF</a>
          @if ($a->email)<a class="sd-btn small" href="{{ route('mail.compose', ['art' => 'int_zusage', 'ids' => $a->id]) }}">Zusage per E-Mail senden</a>@endif
        </p>
      </div>
      <div>
        <strong>Absage</strong>
        <form method="POST" action="{{ route('applicants.status', $a) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end;margin-top:.3rem">
          @csrf <input type="hidden" name="status" value="absage">
          <div class="sd-field" style="margin:0;flex:1 1 14rem"><label for="f-rs" class="sd-muted">Interner Grund (kommt nicht ins Schreiben)</label><input id="f-rs" name="reason" maxlength="190" value="{{ $a->reason }}" placeholder="z. B. Einkommen reicht nicht, andere Bewerbung gewählt" style="{{ $box }};width:100%"></div>
          <button class="sd-btn sec small" type="submit">Als abgesagt vermerken</button>
        </form>
        <p style="margin:.4rem 0 0;display:flex;flex-wrap:wrap;gap:.5rem">
          <a class="sd-btn sec small" href="{{ route('applicants.letter', [$a, 'absage']) }}" target="_blank" rel="noopener">Schreiben ansehen</a>
          <a class="sd-btn sec small" href="{{ route('documents.pdf', ['art' => 'int_absage', 'id' => $a->id]) }}">PDF</a>
          @if ($a->email)<a class="sd-btn small" href="{{ route('mail.compose', ['art' => 'int_absage', 'ids' => $a->id]) }}">Absage per E-Mail senden</a>@endif
        </p>
        <p class="sd-muted" style="margin:.3rem 0 0">Ein Grund in der Absage ist nicht nötig. Nenne keine Merkmale wie Herkunft, Religion, Geschlecht oder Familienstand (Gleichbehandlungsgesetz).</p>
      </div>
      <div>
        <strong>Mietvertrag</strong>
        <p class="sd-muted" style="margin:.3rem 0">Legt aus dem Interessenten einen Mieter an und öffnet den Mietvertrag mit der Wohnung.</p>
        <form method="POST" action="{{ route('applicants.convert', $a) }}">@csrf<button class="sd-btn" type="submit">Mieter anlegen und Mietvertrag erfassen</button></form>
      </div>
    </div>
    <form method="POST" action="{{ route('applicants.status', $a) }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-top:1rem">
      @csrf
      <label for="f-st" class="sd-muted">Stand ändern</label>
      <select id="f-st" name="status" style="{{ $box }}">@foreach (Applicant::STATUS as $k => $label)@continue($k === 'vertrag')<option value="{{ $k }}" @selected($a->status === $k)>{{ $label }}</option>@endforeach</select>
      <button class="sd-btn sec small" type="submit">Speichern</button>
    </form>
  @endif
</div>

<form method="POST" action="{{ route('applicants.destroy', $a) }}" onsubmit="return confirm('Den Interessenten mit allen Angaben endgültig löschen?')" style="margin-top:1rem">
  @csrf @method('DELETE')
  <button class="sd-btn sec small" type="submit">Interessent und Daten löschen</button>
</form>
@endsection
