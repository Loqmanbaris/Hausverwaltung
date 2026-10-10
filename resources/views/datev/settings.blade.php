@extends('layouts.app')
@section('title', 'DATEV-Einstellungen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>DATEV-Einstellungen</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('datev.index') }}">Zurück zum Export</a>
  </div>
</div>

<p class="sd-muted">Diese Angaben stehen in der Kopfzeile jeder DATEV-Datei. Sie müssen genau zu den Angaben im Programm des Steuerberaters passen, sonst lehnt DATEV die Datei ab. Bei Zweifeln bitte beim Steuerberater nachfragen.</p>

@php
  $months = [1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April', 5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'];
  $skr = old('skr', $config['skr']);
  $fy = (int) old('fy_month', $config['fy_month']);
@endphp

<form method="POST" action="{{ route('datev.settings.save') }}">
  @csrf @method('PUT')
  <div class="sd-card">
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-consultant">Beraternummer *</label>
        <input id="f-consultant" name="consultant" type="text" inputmode="numeric" maxlength="7" value="{{ old('consultant', $config['consultant']) }}" required>
        <small class="sd-muted">4 bis 7 Ziffern, vergibt DATEV an die Kanzlei.</small>
      </div>
      <div class="sd-field">
        <label for="f-client">Mandantennummer *</label>
        <input id="f-client" name="client" type="text" inputmode="numeric" maxlength="5" value="{{ old('client', $config['client']) }}" required>
        <small class="sd-muted">1 bis 5 Ziffern.</small>
      </div>
      <div class="sd-field">
        <label for="f-skr">Kontenrahmen *</label>
        <select id="f-skr" name="skr" required>
          <option value="03" @selected($skr === '03')>SKR03</option>
          <option value="04" @selected($skr === '04')>SKR04</option>
        </select>
      </div>
      <div class="sd-field">
        <label for="f-fy_month">Wirtschaftsjahr beginnt am 1. *</label>
        <select id="f-fy_month" name="fy_month" required>
          @foreach ($months as $number => $name)
            <option value="{{ $number }}" @selected($fy === $number)>{{ $name }}</option>
          @endforeach
        </select>
        <small class="sd-muted">Meist Januar. Muss zum Mandanten im Programm des Steuerberaters passen.</small>
      </div>
      <div class="sd-field">
        <label for="f-account_length">Sachkontenlänge *</label>
        <input id="f-account_length" name="account_length" type="text" inputmode="numeric" maxlength="1" value="{{ old('account_length', $config['account_length']) }}" required>
        <small class="sd-muted">Anzahl der Ziffern der Sachkonten, bei SKR04 meist 4.</small>
      </div>
      <div class="sd-field">
        <label for="f-counter_account">Gegenkonto *</label>
        <input id="f-counter_account" name="counter_account" type="text" inputmode="numeric" maxlength="9" value="{{ old('counter_account', $config['counter_account']) }}" required>
        <small class="sd-muted">Auf dieses Konto wird jeder Beleg gegengebucht, zum Beispiel Bank oder Verbindlichkeiten. Bitte vom Steuerberater nennen lassen.</small>
      </div>
    </div>
  </div>
  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ route('datev.index') }}">Abbrechen</a>
  </div>
</form>
@endsection
