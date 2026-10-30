@extends('layouts.app')
@section('title', 'Sicherungen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Sicherungen</h1>
  <div class="sd-actions">
    @if ($hasKey)
      <form class="sd-inline" method="POST" action="{{ route('backups.run') }}">
        @csrf
        <button class="sd-btn" type="submit">Jetzt sichern</button>
      </form>
    @endif
  </div>
</div>

@if ($attention)
  <div class="sd-err" style="background:#fffaf0;border-color:#f0d9a8;color:#7a4b00"><strong>Bitte beachten:</strong> {{ $attention }}</div>
@endif

<div class="sd-card">
  <h2>Stand</h2>
  <dl class="sd-dl">
    <dt>Letzte Sicherung</dt>
    <dd>
      @if ($status && ! empty($status['finished_at']))
        {{ \Illuminate\Support\Carbon::parse($status['finished_at'])->timezone('Europe/Berlin')->format('d.m.Y H:i') }}
        @if (! ($status['ok'] ?? false))
          <span class="status-off">fehlgeschlagen</span>
          <div class="sd-muted">{{ $status['message'] ?? '' }}</div>
        @else
          <span class="status-ok">in Ordnung</span>
        @endif
      @else
        noch keine
      @endif
    </dd>
    <dt>Letzter Download</dt>
    <dd>{{ $lastDownload ? \Illuminate\Support\Carbon::createFromTimestamp($lastDownload)->timezone('Europe/Berlin')->format('d.m.Y H:i') : 'noch nie' }}</dd>
    <dt>Aufbewahrung auf dem Server</dt>
    <dd>Alle Sicherungen der letzten 30 Tage und die erste Sicherung jedes Monats für ein Jahr.</dd>
  </dl>
</div>

<div class="sd-card">
  <h2>Gesicherte Dateien</h2>
  @if (count($backups) === 0)
    <p class="sd-muted" style="margin:0">Es gibt noch keine Sicherung.@if (! $hasKey) Zuerst unten den Schlüssel einrichten.@endif</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Zeitpunkt</th><th>Größe</th><th></th></tr></thead>
        <tbody>
          @foreach ($backups as $backup)
            <tr>
              <td>{{ $backup['date']->format('d.m.Y H:i') }}</td>
              <td>{{ \App\Support\Backup\BackupService::formatSize($backup['size']) }}</td>
              <td><a class="sd-btn sec small" href="{{ route('backups.download', $backup['name']) }}">Herunterladen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <p class="sd-muted" style="margin:.8rem 0 0">
      Tipp: Speichere die heruntergeladene Datei in deinem OneDrive-Ordner auf dem PC, zum Beispiel in einem Unterordner „Sicherungen Baris“.
      Das OneDrive-Programm lädt sie dann automatisch in die Cloud. Die Datei ist verschlüsselt und ohne den privaten Schlüssel nicht lesbar.
    </p>
  @endif
</div>

<div class="sd-card">
  <h2>Schlüssel für die Verschlüsselung</h2>
  @if ($hasKey)
    <p>Ein öffentlicher Schlüssel ist hinterlegt. Der Fingerabdruck zum Vergleichen mit dem Sicherungs-Werkzeug:</p>
    <p class="mono" style="font-size:1.05rem">{{ $fingerprint }}</p>
    <p class="sd-muted">Der Server kann damit nur verschlüsseln. Zum Öffnen einer Sicherung braucht man den privaten Schlüssel, der nicht auf dem Server liegt.</p>
  @else
    <p>Bevor die erste Sicherung möglich ist, brauchst du ein Schlüsselpaar. Der <strong>öffentliche</strong> Schlüssel kommt auf den Server und verschlüsselt die Sicherungen. Der <strong>private</strong> Schlüssel bleibt bei dir und öffnet sie später.</p>
  @endif

  <ol style="margin-top:.8rem;padding-left:1.3rem">
    <li>Lade das Werkzeug herunter: <a class="sd-btn sec small" href="{{ route('backups.tool') }}">sicherungs-werkzeug.html</a></li>
    <li>Öffne die gespeicherte Datei auf deinem PC mit einem Doppelklick. Sie läuft im Browser und braucht kein Internet.</li>
    <li>Klicke dort auf „Schlüsselpaar erzeugen“. Speichere den <strong>privaten</strong> Schlüssel an zwei sicheren Orten und drucke ihn für den Notfallumschlag aus. Er darf nie auf den Server oder in dieses System.</li>
    <li>Kopiere den <strong>öffentlichen</strong> Schlüssel in das Feld unten.</li>
  </ol>

  <form method="POST" action="{{ route('backups.key') }}" style="margin-top:1rem">
    @csrf
    @method('PUT')
    <div class="sd-field">
      <label for="f-public_key">Öffentlicher Schlüssel</label>
      <textarea id="f-public_key" name="public_key" rows="8" class="mono" spellcheck="false" placeholder="-----BEGIN PUBLIC KEY-----">{{ old('public_key') }}</textarea>
      @if ($hasKey)
        <small class="sd-muted">Wenn du den Schlüssel ersetzt, lassen sich ältere Sicherungen nur mit dem alten privaten Schlüssel öffnen. Bewahre ihn deshalb weiter auf.</small>
      @endif
    </div>
    <button class="sd-btn" type="submit">Schlüssel speichern</button>
  </form>
</div>
@include('backups.onedrive')
@endsection
