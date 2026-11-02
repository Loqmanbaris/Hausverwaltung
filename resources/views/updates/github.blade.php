@extends('layouts.app')
@section('title', 'Updates von GitHub')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  $input = 'padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;width:100%';
@endphp
<div class="sd-head">
  <h1>Updates von GitHub</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('updates.index') }}">Zurück zu Updates</a></div>
</div>
<p class="sd-muted">Das CRM liest die Paket-Dateien (.crmupdate) aus einem Ordner deines GitHub-Repositories. Es lädt nichts ohne deinen Klick und spielt nichts ohne dein Passwort ein. Jedes Paket muss die digitale Unterschrift tragen, sonst wird es abgelehnt. Selbst wer an dein Repository käme, könnte so keinen Programmcode einschleusen.</p>

@if ($configured)
  <div class="sd-card">
    <h2>Pakete in {{ $repo }}{{ $path !== '' ? '/'.$path : '' }}</h2>
    @if ($error)
      <div class="sd-err">{{ $error }}</div>
    @elseif ($files === [])
      <p class="sd-muted" style="margin:0">Im Ordner liegt noch keine Datei mit der Endung .crmupdate.</p>
    @else
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Datei</th><th>Größe</th><th>Stand</th><th></th></tr></thead>
          <tbody>
            @foreach ($files as $f)
              <tr>
                <td>{{ $f['name'] }}</td>
                <td>{{ number_format($f['size'] / 1024, 0, ',', '.') }} KB</td>
                <td>{{ $f['installed'] ? 'schon eingespielt' : 'neu' }}</td>
                <td>
                  <form method="POST" action="{{ route('updates.github.fetch') }}">
                    @csrf
                    <input type="hidden" name="datei" value="{{ $f['name'] }}">
                    <button class="sd-btn {{ $f['installed'] ? 'sec' : '' }} small" type="submit">Laden und prüfen</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <p class="sd-muted" style="margin:.6rem 0 0">Spiele neue Pakete in der Reihenfolge der Nummern ein. „Laden und prüfen“ spielt noch nichts ein.</p>
    @endif
  </div>
@endif

<div class="sd-card">
  <h2>Quelle einstellen</h2>
  <form method="POST" action="{{ route('updates.github.save') }}">
    @csrf
    <div class="sd-grid">
      <div class="sd-field"><label for="f-repo">Repository *</label><input id="f-repo" name="repo" required placeholder="meinname/crm-updates" value="{{ old('repo', $repo) }}"><small class="sd-muted">Name des Kontos oder der Organisation, Schrägstrich, Name des Repositories.</small></div>
      <div class="sd-field"><label for="f-path">Ordner</label><input id="f-path" name="path" placeholder="updates" value="{{ old('path', $path) }}"><small class="sd-muted">Ordner im Repository, in dem die Dateien liegen. Leer = oberste Ebene.</small></div>
      <div class="sd-field"><label for="f-branch">Branch *</label><input id="f-branch" name="branch" required value="{{ old('branch', $branch) }}"></div>
    </div>
    <div class="sd-field">
      <label for="f-token">Zugangsschlüssel (Token){{ $hasToken ? ' – gespeichert' : '' }}</label>
      <input id="f-token" name="token" type="password" autocomplete="off" placeholder="{{ $hasToken ? 'Leer lassen, um den gespeicherten zu behalten' : 'Nur nötig bei privatem Repository' }}">
      <small class="sd-muted">Bei GitHub unter Settings → Developer settings → Personal access tokens einen „fine-grained token“ nur für dieses Repository erzeugen, mit der Berechtigung „Contents: Read-only“. Der Token wird verschlüsselt gespeichert und nie wieder angezeigt.</small>
      @if ($hasToken)<label style="display:block;margin-top:.4rem"><input type="checkbox" name="clear_token" value="1"> Gespeicherten Token entfernen</label>@endif
    </div>
    <div class="sd-field" style="max-width:20rem"><label for="f-pw">Dein Passwort zur Bestätigung *</label><input id="f-pw" name="password" type="password" required autocomplete="current-password"></div>
    <button class="sd-btn" type="submit">Speichern</button>
  </form>
</div>
@endsection
