@extends('layouts.app')
@section('title', 'Erlöskonten Mieten')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.dv-acc input[type=text]{width:7rem;padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit}
</style>
<div class="sd-head">
  <h1>Erlöskonten Mieten</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('datev.rent') }}">Zurück zum Export</a>
  </div>
</div>

<p class="sd-muted">Hier legst du fest, auf welche Konten im SKR{{ $config['skr'] }} die Mieteinnahmen gebucht werden. Bitte mit dem Steuerberater abstimmen. Konten haben {{ $config['account_length'] }} Ziffern. Bleibt das Feld für Nebenkosten oder Heizkosten leer, wird das Konto „Miete“ derselben Zeile genommen. Einen Steuerschlüssel nur eintragen, wenn der Steuerberater ihn vorgibt (zum Beispiel bei Konten ohne automatische Umsatzsteuer). Gebucht wird der Bruttobetrag.</p>

<form method="POST" action="{{ route('datev.rent.accounts.save') }}">
  @csrf @method('PUT')

  <div class="sd-card dv-acc">
    <h2>Gegenkonten</h2>
    <div class="sd-table-wrap">
      <table class="sd">
        <tbody>
          <tr><td>Bank (Überweisung, Lastschrift) *</td><td><input type="text" name="bank_account" inputmode="numeric" maxlength="{{ $config['account_length'] + 1 }}" value="{{ old('bank_account', $counters['bank']) }}" aria-label="Bankkonto"></td></tr>
          <tr><td>Kasse (Barzahlung, optional)</td><td><input type="text" name="cash_account" inputmode="numeric" maxlength="{{ $config['account_length'] + 1 }}" value="{{ old('cash_account', $counters['cash']) }}" aria-label="Kassenkonto"> <span class="sd-muted">leer = Bank</span></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  @foreach (\App\Support\Datev\DatevRentAccounts::GROUPS as $g => $gLabel)
    <div class="sd-card dv-acc">
      <h2>{{ $gLabel }}</h2>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Bestandteil</th><th>Erlöskonto</th><th>Steuerschlüssel (optional)</th></tr></thead>
          <tbody>
            @foreach (\App\Support\Datev\DatevRentAccounts::PARTS as $p => $pLabel)
              @php($key = $g.':'.$p)
              <tr>
                <td>{{ $pLabel }}</td>
                <td><input type="text" name="account[{{ $key }}]" inputmode="numeric" maxlength="{{ $config['account_length'] }}" value="{{ old('account.'.$key, $accounts[$key]['account'] ?? '') }}" placeholder="{{ $p === 'miete' ? '' : 'wie Miete' }}" aria-label="Konto {{ $gLabel }} {{ $pLabel }}"></td>
                <td><input type="text" name="bu[{{ $key }}]" inputmode="numeric" maxlength="4" value="{{ old('bu.'.$key, $accounts[$key]['bu_key'] ?? '') }}" aria-label="Steuerschlüssel {{ $gLabel }} {{ $pLabel }}"></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endforeach

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ route('datev.rent') }}">Abbrechen</a>
  </div>
</form>
@endsection
