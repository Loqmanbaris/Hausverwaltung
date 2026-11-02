{{-- Fenster „Gutschrift aufteilen“ (wie bei Objego). Erwartet $tx, $r (Vorschläge), $leaseOptions, $remaining, $state, $accountId --}}
@php
  $spMonths = ['' => 'automatisch (ältester offener Monat)'];
  $spNames = [1 => 'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
  $spCur = $tx->booking_date->toDateTimeImmutable()->modify('first day of this month')->modify('+2 months');
  for ($spI = 0; $spI < 27; $spI++, $spCur = $spCur->modify('-1 month')) {
      $spMonths[$spCur->format('Y-m')] = $spNames[(int) $spCur->format('n')].' '.$spCur->format('Y');
  }
  $spBest = ($r['suggestions'][0] ?? null) && $r['suggestions'][0]['score'] >= 55 ? (int) $r['suggestions'][0]['lease']->id : 0;
@endphp
<dialog class="sp-dlg" id="aufteilen-{{ $tx->id }}" data-max="{{ number_format($remaining, 2, '.', '') }}">
  <form method="POST" action="{{ route('bank.split', $tx) }}" class="sp-form">
    @csrf
    <input type="hidden" name="stand" value="{{ $state }}"><input type="hidden" name="konto" value="{{ $accountId ?: '' }}">
    <div class="sp-head">
      <div><strong>Gutschrift aufteilen</strong><br><span class="sd-muted">{{ $tx->counterparty_name ?: '–' }} · {{ $tx->booking_date->format('d.m.Y') }} · offen {{ Money::format($remaining) }}</span></div>
      <button type="button" class="sp-x" data-sp-close aria-label="Schließen">✕</button>
    </div>
    <div class="sp-rows">
      <div class="sp-row">
        <div class="sp-f"><label>Einnahmeart *</label>
          <select name="rows[0][kind]" data-sp-kind>
            @foreach (\App\Models\RentPayment::INCOME_KINDS as $spK => $spL)<option value="{{ $spK }}">{{ $spL }}</option>@endforeach
          </select></div>
        <div class="sp-f sp-wide"><label>Mietvertrag <span data-sp-req>*</span></label>
          <select name="rows[0][lease_id]">
            <option value="">– Mietvertrag wählen –</option>
            @foreach ($leaseOptions[$tx->owner_id] ?? [] as $l)
              <option value="{{ $l->id }}" @selected((int) $l->id === $spBest)>{{ $l->unit?->property?->name }}, {{ $l->unit?->label }} – {{ $l->tenant?->display_name }}{{ $l->end_date && $l->end_date->format('Y-m-d') < date('Y-m-d') ? ' (beendet)' : '' }}</option>
            @endforeach
          </select></div>
        <div class="sp-f" data-sp-month><label>Monat</label>
          <select name="rows[0][month]">
            @foreach ($spMonths as $spK => $spL)<option value="{{ $spK }}">{{ $spL }}</option>@endforeach
          </select></div>
        <div class="sp-f sp-amt"><label>Betrag *</label><input type="text" name="rows[0][amount]" inputmode="decimal" value="{{ Money::input($remaining) }}" data-sp-amount></div>
        <div class="sp-f sp-wide"><label>Notiz</label><input type="text" name="rows[0][note]" maxlength="190" placeholder="z. B. Mietzahlung 12.2026"></div>
        <button type="button" class="sp-del" data-sp-del aria-label="Zeile entfernen" title="Zeile entfernen">🗑</button>
      </div>
    </div>
    <button type="button" class="sd-btn sec small" data-sp-add>+ Einnahmeart hinzufügen</button>
    <div class="sp-sum">Gesamtsumme: <strong data-sp-total>{{ Money::format($remaining) }}</strong> <span data-sp-hint class="sd-muted"></span></div>
    <label class="sd-check" style="margin:.2rem 0 0;font-weight:400"><input type="checkbox" name="merken" value="1" checked> Konto des Zahlers merken</label>
    <div class="sp-foot">
      <button type="button" class="sd-btn sec" data-sp-close>Abbrechen</button>
      <button type="submit" class="sd-btn" data-sp-save>Aufteilung speichern</button>
    </div>
  </form>
</dialog>
