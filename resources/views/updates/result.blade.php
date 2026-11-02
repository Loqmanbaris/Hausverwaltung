@extends('layouts.app')
@section('title', 'Update')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>{{ $update->title }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('updates.index') }}">Alle Updates</a></div>
</div>

@if ($test)
  @if ($test['ok'])
    <div class="sd-ok"><strong>Alles in Ordnung.</strong> Das Update ist eingespielt und der Selbsttest war erfolgreich.</div>
  @else
    <div class="sd-err"><strong>Der Selbsttest hat Fehler gemeldet.</strong> Bitte schicke einen Screenshot dieser Seite. Falls etwas nicht mehr funktioniert, kannst du das Update unten zurücknehmen.</div>
  @endif
@endif

<div class="sd-card">
  <dl class="sd-dl">
    <dt>Paket</dt><dd>{{ $update->name }}</dd>
    <dt>Stand</dt><dd>{{ $update->statusLabel() }}</dd>
    <dt>Eingespielt</dt><dd>{{ $update->installed_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') }} von {{ $update->installer?->name ?? '–' }}</dd>
    <dt>Dateien</dt><dd>{{ count((array) $update->files_changed) }} geändert, {{ count((array) $update->files_new) }} neu</dd>
  </dl>
  @if ($update->output)
    <details @if ($test && ! $test['ok']) open @endif style="margin-top:.8rem"><summary class="sd-muted">Protokoll</summary>
      <pre style="white-space:pre-wrap;font-size:.85rem;background:var(--surface-2);padding:.7rem;border-radius:.4rem">{{ $update->output }}</pre>
    </details>
  @endif
</div>

@if (in_array($update->status, ['installiert', 'test_fehler'], true))
  <div class="sd-card">
    <h2>Zurücknehmen</h2>
    <p class="sd-muted" style="margin-top:0">Stellt die alten Dateien wieder her und entfernt neue Tabellen dieses Updates. Daten, die in diesen neuen Tabellen erfasst wurden, gehen dabei verloren.</p>
    <form method="POST" action="{{ route('updates.rollback', $update) }}" onsubmit="return confirm('Dieses Update wirklich zurücknehmen?')">
      @csrf
      <div class="sd-field" style="max-width:20rem;margin-bottom:.8rem">
        <label for="f-password">Dein Passwort</label>
        <input id="f-password" type="password" name="password" required autocomplete="current-password">
      </div>
      <button class="sd-btn danger" type="submit">Update zurücknehmen</button>
    </form>
  </div>
@endif
@endsection
