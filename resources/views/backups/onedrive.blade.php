@php
  use App\Support\Backup\OneDrive;
  $od = OneDrive::config();
  $odStatus = OneDrive::status();
  $odLast = $odStatus['last'];
  $odPending = $od ? count(OneDrive::pending()) : 0;
  $bag = $errors->getBag('onedrive');
  $in = 'padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;width:100%';
  $val = fn ($k, $d = '') => old($k, \App\Support\Settings::get('onedrive_'.$k, $d));
@endphp
<div class="sd-card" id="onedrive">
  <h2>Sicherung bei OneDrive</h2>
  <p class="sd-muted" style="margin-top:0">Nach jeder täglichen Sicherung lädt das CRM die verschlüsselte Datei in einen OneDrive-Ordner (Microsoft 365 Business). Es schreibt dort nur und löscht oder liest nichts. Das Aufräumen alter Dateien bei OneDrive bleibt bei dir.</p>

  @if ($errors->has('onedrive') || $errors->has('onedrive_test'))
    <div class="sd-err">{{ $errors->first('onedrive') ?: $errors->first('onedrive_test') }}</div>
  @endif

  @if ($od)
    <dl class="sd-dl">
      <dt>Ordner</dt><dd>{{ $od['folder'] }} (Konto {{ $od['user'] }})</dd>
      <dt>Automatisch</dt><dd>{{ $od['auto'] ? 'Ja, nach jeder Sicherung' : 'Aus' }}</dd>
      <dt>Letzter Versuch</dt>
      <dd>
        @if ($odLast)
          {{ \Illuminate\Support\Carbon::parse($odLast['at'])->timezone('Europe/Berlin')->format('d.m.Y H:i') }}:
          @if ($odLast['ok']) <span class="sd-badge" style="background:var(--ok-bg);color:var(--ok)">in Ordnung</span> {{ $odLast['file'] }}
          @else <span class="sd-badge" style="background:var(--danger-bg);color:var(--danger)">fehlgeschlagen</span> {{ $odLast['message'] }}@endif
        @else noch keiner @endif
      </dd>
      <dt>Noch nicht hochgeladen</dt><dd>{{ $odPending }} Sicherung(en)</dd>
    </dl>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem;margin:.6rem 0">
      <form method="POST" action="{{ route('backups.onedrive.test') }}">@csrf<button class="sd-btn sec" type="submit">Verbindung testen</button></form>
      @if ($odPending > 0)<form method="POST" action="{{ route('backups.onedrive.upload') }}">@csrf<button class="sd-btn" type="submit">Jetzt {{ $odPending }} Sicherung(en) hochladen</button></form>@endif
      @if ($od['auto'])<form method="POST" action="{{ route('backups.onedrive.off') }}" onsubmit="return confirm('Automatischen Upload ausschalten?')">@csrf<button class="sd-btn sec" type="submit">Automatik ausschalten</button></form>@endif
    </div>
  @else
    <p class="sd-muted">Noch nicht eingerichtet. Trage die vier Angaben aus dem Microsoft Entra Admin Center ein.</p>
  @endif

  <details @if (! $od || $bag->any()) open @endif>
    <summary style="cursor:pointer;font-weight:600">Einstellungen {{ $od ? 'ändern' : 'eintragen' }}</summary>
    <form method="POST" action="{{ route('backups.onedrive.save') }}" style="margin-top:.6rem">
      @csrf @method('PUT')
      <div class="sd-grid">
        <div class="sd-field"><label for="od-tenant">Verzeichnis-ID (Tenant) *</label><input id="od-tenant" name="tenant" required autocomplete="off" value="{{ $val('tenant') }}" style="{{ $in }}">@if ($bag->has('tenant'))<small class="sd-err">{{ $bag->first('tenant') }}</small>@endif</div>
        <div class="sd-field"><label for="od-client">Anwendungs-ID (Client ID) *</label><input id="od-client" name="client_id" required autocomplete="off" value="{{ $val('client_id') }}" style="{{ $in }}">@if ($bag->has('client_id'))<small class="sd-err">{{ $bag->first('client_id') }}</small>@endif</div>
      </div>
      <div class="sd-grid">
        <div class="sd-field"><label for="od-secret">Geheimer Clientschlüssel (Wert){{ OneDrive::hasSecret() ? ' – gespeichert' : ' *' }}</label><input id="od-secret" name="secret" type="password" autocomplete="new-password" placeholder="{{ OneDrive::hasSecret() ? 'Leer lassen, um den gespeicherten zu behalten' : '' }}" style="{{ $in }}">@if ($bag->has('secret'))<small class="sd-err">{{ $bag->first('secret') }}</small>@endif</div>
        <div class="sd-field"><label for="od-user">E-Mail des OneDrive-Besitzers *</label><input id="od-user" name="user" type="email" required autocomplete="off" value="{{ $val('user') }}" style="{{ $in }}">@if ($bag->has('user'))<small class="sd-err">{{ $bag->first('user') }}</small>@endif</div>
      </div>
      <div class="sd-field"><label for="od-folder">Ordner bei OneDrive *</label><input id="od-folder" name="folder" required value="{{ $val('folder', 'Sicherungen Baris Hausverwaltung') }}" style="{{ $in }}"><small class="sd-muted">Wird bei Bedarf angelegt. Mit Schrägstrich sind Unterordner möglich.</small>@if ($bag->has('folder'))<small class="sd-err">{{ $bag->first('folder') }}</small>@endif</div>
      <label style="display:block;margin:.4rem 0"><input type="checkbox" name="auto" value="1" @checked(old('auto', ! $od ? '1' : ($od['auto'] ? '1' : '')))> Nach jeder täglichen Sicherung automatisch hochladen</label>
      <div class="sd-field" style="max-width:20rem"><label for="od-pw">Dein Passwort zur Bestätigung *</label><input id="od-pw" name="password" type="password" required autocomplete="current-password" style="{{ $in }}">@if ($bag->has('password'))<small class="sd-err">{{ $bag->first('password') }}</small>@endif</div>
      <button class="sd-btn" type="submit">Speichern</button>
      <p class="sd-muted" style="margin:.6rem 0 0">Der geheime Clientschlüssel wird verschlüsselt gespeichert und nie wieder angezeigt. Die App bei Microsoft braucht die Anwendungsberechtigung „Files.ReadWrite.All“ mit Administratorzustimmung.</p>
    </form>
  </details>
</div>
