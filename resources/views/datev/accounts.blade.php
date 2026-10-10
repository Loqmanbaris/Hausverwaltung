@extends('layouts.app')
@section('title', 'Konten der Kostenarten')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.dv-acc input[type=text]{width:7rem;padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit}
</style>
<div class="sd-head">
  <h1>Konten der Kostenarten</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('datev.index') }}">Zurück zum Export</a>
  </div>
</div>

<p class="sd-muted">Hier legst du fest, auf welches Konto im SKR{{ $config['skr'] }} Belege einer Kostenart gebucht werden. Die Konten stimmst du am besten mit dem Steuerberater ab. Sie haben {{ $config['account_length'] }} Ziffern. Einen Steuerschlüssel trägst du nur ein, wenn der Steuerberater ihn vorgibt. Ohne Steuerschlüssel wird der Bruttobetrag gebucht, die Umsatzsteuer wird nicht aufgeteilt.</p>

<form method="POST" action="{{ route('datev.accounts.save') }}">
  @csrf @method('PUT')

  @foreach ($groups as $group => $items)
    <div class="sd-card dv-acc">
      <h2>{{ $group }}</h2>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Kostenart</th><th>Konto</th><th>Steuerschlüssel (optional)</th></tr></thead>
          <tbody>
            @foreach ($items as $key => $label)
              <tr>
                <td>{{ $label }}</td>
                <td><input type="text" name="account[{{ $key }}]" inputmode="numeric" maxlength="{{ $config['account_length'] }}" value="{{ old('account.'.$key, $accounts[$key]['account'] ?? '') }}" aria-label="Konto für {{ $label }}"></td>
                <td><input type="text" name="bu[{{ $key }}]" inputmode="numeric" maxlength="4" value="{{ old('bu.'.$key, $accounts[$key]['bu_key'] ?? '') }}" aria-label="Steuerschlüssel für {{ $label }}"></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endforeach

  @if (count($inactive) > 0)
    <div class="sd-card dv-acc">
      <h2>Stillgelegte Kostenarten</h2>
      <p class="sd-muted" style="margin-top:0">Nur nötig, wenn es noch Belege mit diesen Kostenarten gibt.</p>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Kostenart</th><th>Konto</th><th>Steuerschlüssel (optional)</th></tr></thead>
          <tbody>
            @foreach ($inactive as $key => $label)
              <tr>
                <td>{{ $label }}</td>
                <td><input type="text" name="account[{{ $key }}]" inputmode="numeric" maxlength="{{ $config['account_length'] }}" value="{{ old('account.'.$key, $accounts[$key]['account'] ?? '') }}" aria-label="Konto für {{ $label }}"></td>
                <td><input type="text" name="bu[{{ $key }}]" inputmode="numeric" maxlength="4" value="{{ old('bu.'.$key, $accounts[$key]['bu_key'] ?? '') }}" aria-label="Steuerschlüssel für {{ $label }}"></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  @endif

  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ route('datev.index') }}">Abbrechen</a>
  </div>
</form>
@endsection
