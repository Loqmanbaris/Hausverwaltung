@extends('layouts.app')
@section('title', 'Update prüfen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Update prüfen</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('updates.index') }}">Abbrechen</a></div>
</div>

<div class="sd-ok"><strong>Unterschrift gültig.</strong> Das Paket stammt aus der gewohnten Quelle und ist unverändert.</div>

<div class="sd-card">
  <h2>{{ $package->title }}</h2>
  <dl class="sd-dl">
    <dt>Paket</dt><dd>{{ $package->name }}</dd>
    @if ($package->created)<dt>Erstellt</dt><dd>{{ $package->created }}</dd>@endif
    <dt>Dateien</dt><dd>{{ count($check['changed']) }} geändert, {{ count($check['new']) }} neu @if (count($check['same']) > 0), {{ count($check['same']) }} schon aktuell @endif</dd>
    <dt>Neue Tabellen</dt><dd>{{ count($package->migrations) > 0 ? 'ja' : 'nein' }}</dd>
    <dt>Selbsttest</dt><dd>{{ $package->selftest ? 'ja' : 'nein' }}</dd>
  </dl>
  @if ($package->description)
    <div style="white-space:pre-line;margin-top:.8rem">{{ $package->description }}</div>
  @endif
  <details style="margin-top:.8rem"><summary class="sd-muted">Dateien anzeigen</summary>
    <ul class="sd-muted">
      @foreach ($check['changed'] as $f)<li>geändert: {{ $f }}</li>@endforeach
      @foreach ($check['new'] as $f)<li>neu: {{ $f }}</li>@endforeach
      @foreach ($package->migrations as $m)<li>Tabellen: {{ basename($m) }}</li>@endforeach
    </ul>
  </details>
</div>

@if ($problems !== [])
  <div class="sd-err"><strong>Das Paket kann nicht eingespielt werden:</strong>
    <ul>@foreach ($problems as $p)<li>{{ $p }}</li>@endforeach</ul>
  </div>
@else
  <div class="sd-card">
    <h2>Einspielen</h2>
    <p class="sd-muted" style="margin-top:0">Das dauert meist unter einer Minute. Bitte die Seite währenddessen nicht schließen. Danach läuft automatisch der Selbsttest.</p>
    <form method="POST" action="{{ route('updates.install') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='Wird eingespielt …'">
      @csrf
      <input type="hidden" name="paket" value="{{ $token }}">
      <div class="sd-field" style="max-width:20rem;margin-bottom:.8rem">
        <label for="f-password">Zur Sicherheit: dein Passwort</label>
        <input id="f-password" type="password" name="password" required autocomplete="current-password">
      </div>
      <button class="sd-btn" type="submit">Jetzt einspielen</button>
    </form>
  </div>
@endif
@endsection
