@extends('layouts.app')
@section('title', 'Umsätze – Kontoabruf')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  $kaStatus = \App\Models\BankConnection::STATUS;
  $kaDe = fn ($d) => $d ? $d->timezone('Europe/Berlin')->format('d.m.Y H:i') : '–';
@endphp
<style>
.ka-steps{margin:.2rem 0 0 1.1rem;padding:0}
.ka-steps li{margin:.25rem 0}
.ka-state{display:inline-block;border-radius:1rem;padding:.08rem .6rem;font-size:.82rem;font-weight:700;background:var(--surface-2)}
.ka-state.bereit{background:var(--ok-bg);color:var(--ok)}
.ka-state.tan{background:var(--brass-bg);color:var(--brass)}
.ka-state.fehler{background:var(--danger-bg);color:var(--danger)}
.ka-tan{border:2px solid var(--brass-line);background:var(--brass-bg);border-radius:10px;padding:.9rem 1rem;margin:.8rem 0}
.ka-row{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin-top:.6rem}
</style>
@include('partials.bank-tabs')

<div class="sd-card">
  <h2>So funktioniert der automatische Kontoabruf</h2>
  <p style="margin-top:0">Das CRM holt die Umsätze direkt bei der Bank ab (FinTS, wie Banking-Programme am PC) – jeden Morgen automatisch und jederzeit über „Jetzt abrufen“. Sie landen wie beim CSV-Import unter „Eingänge zuordnen“ und „Ausgaben zuordnen“; schon vorhandene werden nicht doppelt angelegt.</p>
  <ol class="ka-steps">
    <li><strong>Einmalig: Registrierungsnummer beantragen</strong> – jede FinTS-Software braucht eine kostenlose Produktregistrierung bei der Deutschen Kreditwirtschaft (<a href="https://www.fints.org/de/hersteller/produktregistrierung" target="_blank" rel="noopener">fints.org → Produktregistrierung</a>). Die Nummer kommt nach einigen Tagen; bis sie bei der Sparkasse freigeschaltet ist, können noch einige Wochen vergehen.</li>
    <li><strong>Zugang je Konto eintragen:</strong> FinTS-Adresse der Bank, Bankleitzahl, Anmeldename und PIN des Online-Bankings.</li>
    <li><strong>TAN-Verfahren wählen</strong> (bei der Sparkasse meist „pushTAN“).</li>
    <li><strong>„Jetzt abrufen“</strong> und in der S-pushTAN-App freigeben. Danach läuft der Abruf täglich; die Bank verlangt etwa alle 90 Tage eine neue Freigabe – dann erscheint hier und in der E-Mail ein Hinweis.</li>
  </ol>
  <p class="sd-muted">Sicherheit: Anmeldename und PIN werden verschlüsselt gespeichert und sind nur für Administratoren änderbar. Das CRM ruft nur Umsätze ab, Überweisungen sind damit nicht möglich.</p>
</div>

<div class="sd-card">
  <h2>1. FinTS-Registrierungsnummer</h2>
  <form method="POST" action="{{ route('bank.abruf.product') }}" class="ka-row">
    @csrf @method('PUT')
    <input type="text" name="fints_product_id" value="{{ old('fints_product_id', $productId) }}" maxlength="40" placeholder="z. B. 1A2B3C4D5E6F7G8H9I0J" style="max-width:22rem">
    <button class="sd-btn small" type="submit">Speichern</button>
    @if ($productId === '')<span class="rt-part">fehlt noch – ohne sie lehnt die Bank die Verbindung ab</span>@else<span class="rt-ok">eingetragen</span>@endif
  </form>
</div>

@forelse ($accounts as $a)
  @php
    $c = $conns->get($a->id);
    $blz = \App\Support\Bank\Kontoabruf::blzAusIban($a->iban);
    $modes = $c ? session('fints_modes_'.$c->id) : null;
    $media = $c ? session('fints_media_'.$c->id) : null;
    $pending = $c && $c->status === 'tan' && $c->pending;
  @endphp
  <div class="sd-card" id="konto-{{ $a->id }}">
    <h2>{{ $a->owner?->name }} – {{ $a->label ?: $a->bank_name }}</h2>
    <p class="sd-muted" style="margin-top:0">IBAN {{ $a->formattedIban() }}@if ($a->bank_name) · {{ $a->bank_name }}@endif</p>

    @if ($c)
      <p>
        <span class="ka-state {{ $c->status }}">{{ $kaStatus[$c->status] ?? $c->status }}</span>
        · Letzter Abruf: {{ $kaDe($c->last_fetch_at) }}
        · Täglich automatisch: <strong>{{ $c->active ? 'an' : 'aus' }}</strong>
        @if ($c->tan_mode) · TAN-Verfahren gewählt @else · <span class="rt-part">TAN-Verfahren fehlt</span>@endif
      </p>
      @if ($c->last_message)<p class="sd-muted">{{ $c->last_message }}</p>@endif
      @if ($c->status === 'fehler' && $c->last_error)<div class="sd-err" style="margin:.4rem 0">{{ $c->last_error }}</div>@endif

      @if ($pending)
        <div class="ka-tan">
          <strong>Freigabe nötig.</strong> Die Bank hat eine Bestätigung angefordert. Bei pushTAN: In der S-pushTAN-App den Auftrag „Umsatzabruf“ bzw. „Anmeldung“ freigeben und danach hier auf „Freigegeben – weiter“ tippen (innerhalb weniger Minuten). Bei anderen Verfahren die TAN eingeben.
          <form method="POST" action="{{ route('bank.abruf.weiter', $c->id) }}" class="ka-row">
            @csrf
            <input type="text" name="tan" maxlength="40" placeholder="TAN (nur falls angezeigt)" autocomplete="one-time-code" style="max-width:14rem">
            <button class="sd-btn" type="submit">Freigegeben – weiter</button>
          </form>
        </div>
      @endif

      @if ($modes)
        <form method="POST" action="{{ route('bank.abruf.select', $c->id) }}" class="ka-row">
          @csrf
          <select name="tan_mode" required aria-label="TAN-Verfahren" onchange="this.form.needs_medium.value=this.selectedOptions[0].dataset.medium">
            @foreach ($modes as $m)<option value="{{ $m['id'] }}" data-medium="{{ $m['medium'] ? 1 : 0 }}">{{ $m['name'] }}{{ $m['decoupled'] ? ' (Freigabe in der App)' : '' }}</option>@endforeach
          </select>
          <input type="hidden" name="needs_medium" value="{{ ($modes[0]['medium'] ?? false) ? 1 : 0 }}">
          <button class="sd-btn small" type="submit">Verfahren übernehmen</button>
        </form>
      @elseif ($media)
        <form method="POST" action="{{ route('bank.abruf.select', $c->id) }}" class="ka-row">
          @csrf
          <input type="hidden" name="tan_mode" value="{{ $media['mode'] }}">
          <select name="tan_medium" required aria-label="TAN-Medium">
            @foreach ($media['media'] as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach
          </select>
          <button class="sd-btn small" type="submit">Gerät übernehmen</button>
        </form>
      @endif

      <div class="ka-row">
        <form method="POST" action="{{ route('bank.abruf.run', $c->id) }}">@csrf<button class="sd-btn" type="submit" @disabled($productId === '' || ! $c->tan_mode)>Jetzt abrufen</button></form>
        <form method="POST" action="{{ route('bank.abruf.modes', $c->id) }}">@csrf<button class="sd-btn sec small" type="submit" @disabled($productId === '')>TAN-Verfahren {{ $c->tan_mode ? 'ändern' : 'wählen' }}</button></form>
        <form method="POST" action="{{ route('bank.abruf.toggle', $c->id) }}">@csrf<button class="sd-btn sec small" type="submit">Täglich {{ $c->active ? 'ausschalten' : 'einschalten' }}</button></form>
        <form method="POST" action="{{ route('bank.abruf.destroy', $c->id) }}" onsubmit="return confirm('Kontoabruf für dieses Konto löschen? Die gespeicherten Zugangsdaten werden entfernt. Die abgerufenen Umsätze bleiben.')">@csrf @method('DELETE')<button class="sd-btn danger small" type="submit">Löschen</button></form>
      </div>
    @endif

    <details @if (! $c) open @endif style="margin-top:.8rem">
      <summary style="cursor:pointer;font-weight:600">{{ $c ? 'Zugangsdaten ändern' : '2. Zugang einrichten' }}</summary>
      <form method="POST" action="{{ route('bank.abruf.save', $a->id) }}" style="margin-top:.6rem" autocomplete="off">
        @csrf
        <div class="sd-grid">
          <div class="sd-field">
            <label>FinTS-Adresse (PIN/TAN) *</label>
            <input type="text" name="url" value="{{ old('url', $c?->url) }}" required placeholder="https://banking-….s-fints-pt-….de/fints30">
            <small class="sd-muted">Steht in der Institutsliste (<a href="https://www.hbci-zka.de/institute/institut_auswahl.htm" target="_blank" rel="noopener">hbci-zka.de</a>, Spalte PIN/TAN) oder auf der Webseite der Sparkasse unter „Finanzsoftware / FinTS“.</small>
          </div>
          <div class="sd-field">
            <label>Bankleitzahl *</label>
            <input type="text" name="bank_code" value="{{ old('bank_code', $c?->bank_code ?: $blz) }}" required inputmode="numeric" maxlength="8">
          </div>
          <div class="sd-field">
            <label>Anmeldename / Legitimations-ID {{ $c ? '' : '*' }}</label>
            <input type="text" name="username" value="" maxlength="100" autocomplete="off" @if (! $c) required @endif placeholder="{{ $c ? 'unverändert lassen = bleibt gespeichert' : '' }}">
          </div>
          <div class="sd-field">
            <label>Online-Banking-PIN {{ $c ? '' : '*' }}</label>
            <input type="password" name="pin" value="" maxlength="100" autocomplete="new-password" @if (! $c) required @endif placeholder="{{ $c ? 'unverändert lassen = bleibt gespeichert' : '' }}" style="width:100%;padding:.58rem .7rem;border:1px solid var(--line-strong);border-radius:8px;font:inherit;background:var(--surface);color:var(--ink)">
          </div>
        </div>
        <button class="sd-btn small" type="submit">Speichern</button>
      </form>
    </details>
  </div>
@empty
  <div class="sd-card"><p class="sd-muted" style="margin:0">Es ist noch keine Bankverbindung hinterlegt. Bitte zuerst beim Eigentümer unter „Bankverbindungen“ eintragen.</p></div>
@endforelse
@endsection
