@extends('layouts.app')
@section('title', 'Benutzer')
@section('content')
<div class="toolbar">
  <h1 style="margin:0">Benutzer</h1>
  <a class="btn primary" href="{{ route('users.create') }}">Benutzer anlegen</a>
</div>

@if (session('temp_password'))
  @php($tp = session('temp_password'))
  <div class="alert warn" role="status">
    <strong>Vorläufiges Passwort für {{ $tp['name'] }} ({{ $tp['email'] }}):</strong>
    <div class="temp-pw mono">{{ $tp['password'] }}</div>
    Es wird nur jetzt angezeigt. Bitte persönlich oder per Telefon weitergeben, nicht per E-Mail. Bei der ersten Anmeldung richtet {{ $tp['name'] }} die Authenticator-App ein und wählt ein eigenes Passwort.
  </div>
@endif

@error('users')
  <div class="alert error" role="alert">{{ $message }}</div>
@enderror

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Name</th>
        <th>Rolle</th>
        <th>Status</th>
        <th>Zwei-Faktor</th>
        <th>Letzte Anmeldung</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
      @foreach ($users as $u)
        <tr>
          <td>{{ $u->name }}<br><span class="muted">{{ $u->email }}</span></td>
          <td>{{ $u->roleLabel() }}</td>
          <td>
            @if (! $u->is_active)
              <span class="status-off">Gesperrt</span>
            @elseif ($u->isLocked())
              <span class="status-wait">Bis {{ $u->locked_until->timezone('Europe/Berlin')->format('H:i') }} Uhr gesperrt</span>
            @else
              <span class="status-ok">Aktiv</span>
            @endif
          </td>
          <td>{{ $u->hasTwoFactorEnabled() ? 'Eingerichtet' : 'Noch offen' }}</td>
          <td>{{ $u->last_login_at ? $u->last_login_at->timezone('Europe/Berlin')->format('d.m.Y H:i') : 'noch nie' }}</td>
          <td>
            @if ($u->is(auth()->user()))
              <span class="muted">Das bist du</span>
            @else
              <form method="POST" action="{{ route('users.toggle', $u) }}" onsubmit="return confirm('{{ $u->is_active ? 'Zugang sperren und sofort abmelden?' : 'Zugang wieder freischalten?' }}')">
                @csrf
                <button class="btn small {{ $u->is_active ? 'danger' : '' }}" type="submit">{{ $u->is_active ? 'Sperren' : 'Entsperren' }}</button>
              </form>
              @unless ($u->isAdmin())
                <form method="POST" action="{{ route('users.mail-all', $u) }}">
                  @csrf
                  <button class="btn small" type="submit" title="Alle E-Mails im Postfach sehen">{{ $u->mail_all ? '✉ Alle E-Mails: an' : '✉ Alle E-Mails: aus' }}</button>
                </form>
              @endunless
              <form method="POST" action="{{ route('users.reset-password', $u) }}" onsubmit="return confirm('Neues vorläufiges Passwort vergeben? Der Benutzer wird abgemeldet.')">
                @csrf
                <button class="btn small" type="submit">Neues Passwort</button>
              </form>
              @if ($u->hasTwoFactorEnabled())
                <form method="POST" action="{{ route('users.reset-two-factor', $u) }}" onsubmit="return confirm('Zwei-Faktor zurücksetzen? Der Benutzer muss die App neu einrichten und wird abgemeldet.')">
                  @csrf
                  <button class="btn small" type="submit">App zurücksetzen</button>
                </form>
              @endif
            @endif
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
<p class="hint mt">Die Rechte pro Eigentümer und Objekt kommen mit den Stammdaten im nächsten Paket. Bis dahin sehen Mitarbeiter nur die Übersicht.</p>
@endsection
