@extends('layouts.app')
@section('title', 'Rechnungsdaten')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php $box = 'padding:.55rem;border:1px solid #c5ccd3;border-radius:.5rem;font:inherit;width:100%;box-sizing:border-box'; @endphp
<div class="sd-head"><h1>Rechnungsdaten der Hausverwaltung</h1></div>
<p class="sd-muted">Diese Angaben stehen auf jeder Rechnung. Name, Adresse, Telefon, E-Mail und Umsatzsteuer-ID sind aus dem Impressum vorbelegt. Beim Festschreiben einer Rechnung wird der Stand eingefroren, spätere Änderungen betreffen nur neue Rechnungen.</p>
@if (count($issues) > 0)
  <div class="alert warn"><strong>Noch nicht vollständig:</strong><ul style="margin:.3rem 0 0 1.1rem">@foreach ($issues as $i)<li>{{ $i }}</li>@endforeach</ul></div>
@endif
<form method="POST" action="{{ route('invoices.settings.save') }}" class="sd-card" style="display:grid;gap:.8rem">
  @csrf @method('PUT')
  @foreach (['inv_name' => 'Name oder Firma', 'inv_street' => 'Straße und Hausnummer', 'inv_phone' => 'Telefon', 'inv_email' => 'E-Mail-Adresse'] as $k => $label)
    <div><label for="{{ $k }}">{{ $label }}</label><input id="{{ $k }}" name="{{ $k }}" value="{{ old($k, $d[$k]) }}" style="{{ $box }}">@error($k)<small style="color:#b42318">{{ $message }}</small>@enderror</div>
  @endforeach
  <div style="display:grid;grid-template-columns:1fr 2fr;gap:.7rem">
    <div><label for="inv_zip">PLZ</label><input id="inv_zip" name="inv_zip" value="{{ old('inv_zip', $d['inv_zip']) }}" style="{{ $box }}"></div>
    <div><label for="inv_city">Ort</label><input id="inv_city" name="inv_city" value="{{ old('inv_city', $d['inv_city']) }}" style="{{ $box }}"></div>
  </div>
  <div><label for="inv_vat_id">Umsatzsteuer-ID (DE und 9 Ziffern)</label><input id="inv_vat_id" name="inv_vat_id" value="{{ old('inv_vat_id', $d['inv_vat_id']) }}" style="{{ $box }}">@error('inv_vat_id')<small style="color:#b42318">{{ $message }}</small>@enderror</div>
  <div><label for="inv_tax_number">Steuernummer</label><input id="inv_tax_number" name="inv_tax_number" value="{{ old('inv_tax_number', $d['inv_tax_number']) }}" style="{{ $box }}"><small class="sd-muted">Eine von beiden, Umsatzsteuer-ID oder Steuernummer, muss auf der Rechnung stehen.</small></div>
  <div><label for="inv_iban">IBAN</label><input id="inv_iban" name="inv_iban" value="{{ old('inv_iban', $d['inv_iban']) }}" style="{{ $box }}"></div>
  <div style="display:grid;grid-template-columns:1fr 2fr;gap:.7rem">
    <div><label for="inv_bic">BIC</label><input id="inv_bic" name="inv_bic" value="{{ old('inv_bic', $d['inv_bic']) }}" style="{{ $box }}"></div>
    <div><label for="inv_holder">Kontoinhaber</label><input id="inv_holder" name="inv_holder" value="{{ old('inv_holder', $d['inv_holder']) }}" style="{{ $box }}"></div>
  </div>
  <div style="display:grid;grid-template-columns:1fr 2fr;gap:.7rem">
    <div><label for="inv_due_days">Zahlungsziel (Tage)</label><input id="inv_due_days" name="inv_due_days" inputmode="numeric" value="{{ old('inv_due_days', $d['inv_due_days']) }}" style="{{ $box }}"></div>
    <div><label for="inv_tax_mode">Steuerart</label>
      <select id="inv_tax_mode" name="inv_tax_mode" style="{{ $box }}">
        <option value="regel" @selected(old('inv_tax_mode', $d['inv_tax_mode']) === 'regel')>Regelbesteuerung (Umsatzsteuer wird ausgewiesen)</option>
        <option value="klein" @selected(old('inv_tax_mode', $d['inv_tax_mode']) === 'klein')>Kleinunternehmer nach § 19 UStG (keine Umsatzsteuer)</option>
      </select>
    </div>
  </div>
  <div><label for="inv_note">Hinweistext auf jeder Rechnung (freiwillig)</label><textarea id="inv_note" name="inv_note" rows="3" maxlength="1000" style="{{ $box }}">{{ old('inv_note', $d['inv_note']) }}</textarea></div>
  <div class="sd-actions"><button class="sd-btn" type="submit">Speichern</button> <a class="sd-btn sec" href="{{ route('invoices.index') }}">Zurück</a></div>
</form>
@endsection
