@extends('layouts.app')
@section('title', $o->number())
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('workorders.styles')
@php
  $M = fn ($v) => \App\Support\Money::format($v);
  $flow = ['gemeldet', 'beauftragt', 'termin', 'erledigt', 'abgerechnet'];
  $pos = array_search($o->status, $flow, true);
@endphp
<div class="sd-head">
  <h1>{{ $o->title }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('workorders.index') }}">Alle Aufträge</a>
    <a class="sd-btn sec" href="{{ route('workorders.pdf', $o) }}" target="_blank" rel="noopener">Auftrag als PDF</a>
  </div>
</div>
<p class="sd-muted" style="margin-top:-.4rem">{{ $o->number() }} · {{ $o->place() }} · {{ \App\Models\WorkOrder::SOURCE[$o->source] ?? '' }} am {{ $o->created_at->format('d.m.Y') }}@if ($o->priority !== 'normal') · <span class="wo-pr">{{ \App\Models\WorkOrder::PRIORITY[$o->priority] }}</span>@endif</p>

@if ($o->status === 'storniert')
  <div class="sd-err">Storniert. <form method="POST" action="{{ route('workorders.reopen', $o) }}" style="display:inline">@csrf<button class="link" type="submit">Wieder öffnen</button></form></div>
@else
  <ol class="wo-steps">
    @foreach ($flow as $i => $s)
      <li @class(['done' => $pos !== false && $i < $pos, 'now' => $pos === $i])>{{ \App\Models\WorkOrder::STATUS[$s] }}</li>
    @endforeach
  </ol>
@endif

@if ($link)
  <div class="wo-link"><strong>Link für den Handwerker:</strong><input type="text" readonly value="{{ $link }}" onclick="this.select()" aria-label="Link"><span class="sd-muted" style="font-size:.82rem">Nur jetzt sichtbar. Wer den Link hat, sieht diesen Auftrag.</span></div>
@endif

<div class="wo-grid">
  <div>
    @if ($o->isOpen() && $o->property_id)
      <div class="sd-card" style="border-left:4px solid var(--brand)">
        @if ($o->status === 'gemeldet')
          <h2>Handwerker beauftragen</h2>
        @else
          <h2>Auftrag: {{ $o->vendor?->name }}</h2>
          <p class="sd-muted" style="margin-top:0">{{ $o->status === 'termin' && $o->appointment_at ? 'Termin am '.$o->appointment_at->format('d.m.Y').' um '.$o->appointment_at->format('H:i').' Uhr.' : 'Wartet auf Terminvorschlag des Handwerkers.' }}@if ($o->vendor?->phone) Telefon: {{ $o->vendor->phone }}@endif</p>
        @endif
        <form method="POST" action="{{ route('workorders.assign', $o) }}" class="wo-form" @if ($o->status !== 'gemeldet') style="display:none" id="reassign" @endif>
          @csrf
          <div><label for="a-v">Handwerker *</label>
            <select id="a-v" name="vendor_id" required><option value="">– wählen –</option>@foreach ($vendors as $v)<option value="{{ $v->id }}" @selected((int) old('vendor_id', $o->vendor_id ?? $o->task?->vendor_id) === (int) $v->id)>{{ $v->name }}{{ $v->email ? '' : ' (ohne E-Mail)' }}</option>@endforeach</select>
            <small class="sd-muted">Neue Firma unter <a href="{{ route('vendors.create') }}">Lieferanten</a> anlegen.</small></div>
          <div><label for="a-c">Kostenvoranschlag</label><input id="a-c" name="cost_estimate" inputmode="decimal" value="{{ old('cost_estimate', $o->cost_estimate !== null ? \App\Support\Money::input($o->cost_estimate) : '') }}" placeholder="optional"></div>
          <div class="full"><label for="a-m">Nachricht an den Handwerker</label><textarea id="a-m" name="message" rows="2" maxlength="2000" placeholder="z. B. Schlüssel beim Hausmeister">{{ old('message') }}</textarea></div>
          <label class="full" style="display:flex;gap:.5rem;align-items:center;font-weight:500"><input type="checkbox" name="send_mail" value="1" style="width:auto" checked> Per E-Mail senden (mit Link für Termin und Erledigt-Meldung, Auftrag als PDF)</label>
          <div><button class="sd-btn" type="submit">{{ $o->status === 'gemeldet' ? 'Beauftragen' : 'Neu beauftragen' }}</button></div>
        </form>
        @if ($o->status !== 'gemeldet')
          <div class="sd-actions">
            <details><summary class="sd-btn sec small">Termin eintragen</summary>
              <form method="POST" action="{{ route('workorders.appointment', $o) }}" class="wo-form" style="margin-top:.5rem">@csrf
                <div><label for="t-at">Termin</label><input id="t-at" type="datetime-local" name="appointment_at" required value="{{ $o->appointment_at?->format('Y-m-d\TH:i') }}"></div>
                <div><label for="t-n">Notiz</label><input id="t-n" name="note" maxlength="1000"></div>
                <div><button class="sd-btn small" type="submit">Speichern</button></div></form></details>
            <button class="sd-btn sec small" type="button" onclick="document.getElementById('reassign').style.display='grid';this.remove()">Anderen Handwerker</button>
            <form method="POST" action="{{ route('workorders.link', $o) }}" style="display:inline">@csrf<button class="sd-btn sec small" type="submit" title="Der alte Link wird ungültig">Neuen Link erzeugen</button></form>
          </div>
        @endif
      </div>
    @elseif ($o->isOpen())
      <div class="sd-card" style="border-left:4px solid #b54708"><strong>Objekt fehlt.</strong> Die Meldung kam über die Webseite und konnte keinem Objekt zugeordnet werden. Bitte unten unter „Angaben ändern“ das Objekt wählen.</div>
    @endif

    @if ($o->isOpen() && $o->status !== 'gemeldet')
      <div class="sd-card">
        <h2>Erledigt?</h2>
        <form method="POST" action="{{ route('workorders.done', $o) }}" class="wo-form">@csrf
          <div><label for="d-on">Erledigt am</label><input id="d-on" type="date" name="done_on" required value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}"></div>
          <div><label for="d-n">Notiz</label><input id="d-n" name="note" maxlength="2000"></div>
          <div><button class="sd-btn" type="submit">Als erledigt eintragen</button></div>
        </form>
      </div>
    @endif

    @if (in_array($o->status, ['erledigt', 'termin', 'beauftragt'], true) || $o->receipt)
      <div class="sd-card">
        <h2>Rechnung</h2>
        @if ($o->receipt)
          <p style="margin:0">Beleg <a href="{{ route('receipts.show', $o->receipt) }}">Nr. {{ $o->receipt->id }}</a> · {{ $o->receipt->vendor_name }} · {{ $o->receipt->receipt_date?->format('d.m.Y') }} · <strong>{{ $o->receipt->amount_gross !== null ? $M($o->receipt->amount_gross) : '' }}</strong></p>
        @else
          <form method="POST" action="{{ route('workorders.bill', $o) }}" class="wo-form">@csrf
            <div class="full"><label for="b-r">Beleg</label>
              <select id="b-r" name="receipt_id"><option value="">– Beleg wählen –</option>@foreach ($receipts as $r)<option value="{{ $r->id }}">Nr. {{ $r->id }} · {{ $r->receipt_date?->format('d.m.Y') }} · {{ $r->vendor_name ?: 'ohne Lieferant' }} · {{ $r->amount_gross !== null ? $M($r->amount_gross) : '' }}</option>@endforeach</select>
              <small class="sd-muted">Rechnung noch nicht erfasst? <a href="{{ route('receipts.create') }}" target="_blank" rel="noopener">Beleg erfassen</a>, dann hier wählen.</small></div>
            <div><button class="sd-btn" type="submit">Rechnung verknüpfen</button></div>
          </form>
        @endif
      </div>
    @endif

    <div class="sd-card" id="fotos">
      <h2>Fotos ({{ $o->photos->count() }})</h2>
      @if ($o->photos->isNotEmpty())
        <div class="wo-photos">
          @foreach ($o->photos as $ph)
            <figure>
              <a href="{{ route('workorders.photos.show', [$o, $ph, 'l']) }}" target="_blank" rel="noopener"><img src="{{ route('workorders.photos.show', [$o, $ph, 's']) }}" alt="Foto" loading="lazy"></a>
              <figcaption>{{ $ph->by === 'handwerker' ? 'vom Handwerker' : '' }} {{ $ph->created_at->format('d.m.Y') }}</figcaption>
              <form method="POST" action="{{ route('workorders.photos.destroy', [$o, $ph]) }}" onsubmit="return confirm('Foto löschen?')">@csrf @method('DELETE')<button class="sd-btn sec small" type="submit" aria-label="Foto löschen">×</button></form>
            </figure>
          @endforeach
        </div>
      @endif
      <form method="POST" action="{{ route('workorders.photos.store', $o) }}" enctype="multipart/form-data" class="sd-actions" style="margin-top:.6rem">@csrf
        <input type="file" name="fotos[]" accept="image/*" multiple capture="environment" required style="width:auto">
        <button class="sd-btn sec small" type="submit">Fotos hochladen</button>
      </form>
    </div>
  </div>

  <div>
    <div class="sd-card">
      <h2>Angaben</h2>
      <p style="white-space:pre-line;margin-top:0">{{ $o->description ?: 'Keine Beschreibung.' }}</p>
      <dl style="display:grid;grid-template-columns:max-content 1fr;gap:.3rem 1rem;margin:0;font-size:.93rem">
        <dt class="sd-muted">Ort</dt><dd style="margin:0">{{ \App\Support\Technik\Auftraege::address($o) }}</dd>
        @if ($contact)<dt class="sd-muted">Vor Ort</dt><dd style="margin:0">{{ $contact['name'] }}{{ $contact['phone'] ? ', '.$contact['phone'] : '' }}</dd>@endif
        @if ($o->lease)<dt class="sd-muted">Mietvertrag</dt><dd style="margin:0"><a href="{{ route('leases.show', $o->lease_id) }}">{{ $o->lease->tenant?->display_name }}</a></dd>@endif
        @if ($o->task)<dt class="sd-muted">Wartung</dt><dd style="margin:0"><a href="{{ route('maintenance.edit', $o->task) }}">{{ $o->task->title }}</a></dd>@endif
        @if ($o->inquiry_id)<dt class="sd-muted">Meldung</dt><dd style="margin:0"><a href="{{ route('inquiries.show', $o->inquiry_id) }}">Anfrage von der Webseite</a></dd>@endif
        @if ($o->handover_protocol_id)<dt class="sd-muted">Protokoll</dt><dd style="margin:0"><a href="{{ route('handover.show', $o->handover_protocol_id) }}">Übergabeprotokoll</a></dd>@endif
        @if ($o->cost_estimate !== null)<dt class="sd-muted">Kostenvoranschlag</dt><dd style="margin:0">{{ $M($o->cost_estimate) }}</dd>@endif
        @if ($o->done_on)<dt class="sd-muted">Erledigt</dt><dd style="margin:0">{{ $o->done_on->format('d.m.Y') }}</dd>@endif
      </dl>
      <details style="margin-top:.8rem" @if (! $o->property_id) open @endif><summary style="cursor:pointer;font-weight:600">Angaben ändern</summary>
        <form method="POST" action="{{ route('workorders.update', $o) }}" class="wo-form" style="margin-top:.5rem">@csrf @method('PUT')
          @if (! $o->property_id)
            <div class="full"><label for="u-p">Objekt</label><select id="u-p" name="property_id"><option value="">– wählen –</option>@foreach ($properties as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
          @endif
          <div class="full"><label for="u-t">Titel</label><input id="u-t" name="title" required maxlength="190" value="{{ $o->title }}"></div>
          <div class="full"><label for="u-d">Beschreibung</label><textarea id="u-d" name="description" rows="4" maxlength="5000">{{ $o->description }}</textarea></div>
          <div><label for="u-pr">Dringlichkeit</label><select id="u-pr" name="priority">@foreach (\App\Models\WorkOrder::PRIORITY as $k => $l)<option value="{{ $k }}" @selected($o->priority === $k)>{{ $l }}</option>@endforeach</select></div>
          <div><button class="sd-btn small" type="submit">Speichern</button></div>
        </form>
      </details>
    </div>

    <div class="sd-card" id="verlauf">
      <h2>Verlauf</h2>
      <form method="POST" action="{{ route('workorders.note', $o) }}" style="display:flex;gap:.4rem;margin-bottom:.6rem">@csrf
        <input name="text" required maxlength="2000" placeholder="Notiz, z. B. Mieter angerufen" style="flex:1;font:inherit;padding:.45rem .6rem;border:1px solid var(--line-strong);border-radius:8px" aria-label="Notiz">
        <button class="sd-btn sec small" type="submit">Notiz</button>
      </form>
      <ul class="wo-notes">
        @foreach ($o->notes as $n)
          <li><div class="h">{{ $n->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} · {{ $n->author }}</div><div class="b">{{ $n->text }}</div></li>
        @endforeach
      </ul>
    </div>

    @if ($o->isOpen())
      <form method="POST" action="{{ route('workorders.cancel', $o) }}" onsubmit="return confirm('Auftrag stornieren? Der Link für den Handwerker wird ungültig.')" class="sd-actions">@csrf
        <input name="reason" maxlength="190" placeholder="Grund (optional)" style="width:auto;font:inherit;padding:.4rem .6rem;border:1px solid var(--line-strong);border-radius:8px" aria-label="Grund">
        <button class="sd-btn danger small" type="submit">Stornieren</button>
      </form>
    @elseif ($o->status !== 'storniert')
      <form method="POST" action="{{ route('workorders.reopen', $o) }}">@csrf<button class="link sd-muted" type="submit">Wieder öffnen</button></form>
    @endif
  </div>
</div>
@endsection
