@extends('layouts.app')
@section('title', 'Updates')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head"><h1>Updates</h1></div>
<p class="sd-muted">Hier spielst du neue Pakete ein, ohne SSH. Pakete haben die Endung <strong>.crmupdate</strong> und sind digital unterschrieben. Ein verändertes oder fremdes Paket lehnt das System ab. Vor dem Einspielen erstellt das System automatisch eine verschlüsselte Sicherung und hebt die alten Dateien auf, damit du jedes Update wieder zurücknehmen kannst.</p>

@unless ($writable)
  <div class="sd-err">Das System darf seine Programmdateien nicht ändern. Updates über diese Seite sind so nicht möglich. Bitte melden.</div>
@endunless

<div class="sd-card">
  <h2>Paket hochladen</h2>
  <form method="POST" action="{{ route('updates.upload') }}" enctype="multipart/form-data">
    @csrf
    <div class="sd-field" style="margin-bottom:.8rem">
      <label for="f-paket">Paket-Datei (.crmupdate)</label>
      <input id="f-paket" type="file" name="paket" accept=".crmupdate" required>
    </div>
    <button class="sd-btn" type="submit">Hochladen und prüfen</button>
    <span class="sd-muted">Es wird noch nichts eingespielt.</span>
  </form>
</div>

<div class="sd-card">
  <h2>Programmstand für Claude</h2>
  <p class="sd-muted" style="margin-top:0">Für eine neue Unterhaltung mit Claude: Diese Datei enthält nur den Programmcode, keine Passwörter, keine Belege und keine Mieterdaten. Herunterladen und im Chat hochladen.</p>
  <a class="sd-btn sec" href="{{ route('updates.source') }}">Programmstand herunterladen</a>
</div>

<div class="sd-card">
  <h2>Eingespielte Updates</h2>
  @if ($updates->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine Updates über diese Seite. Frühere Pakete wurden per SSH eingespielt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Datum</th><th>Paket</th><th>Stand</th><th></th></tr></thead>
        <tbody>
          @foreach ($updates as $u)
            <tr>
              <td style="white-space:nowrap">{{ $u->installed_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') }}<br><span class="sd-muted">{{ $u->installer?->name }}</span></td>
              <td>{{ $u->title }}<br><span class="sd-muted">{{ $u->name }}</span></td>
              <td>{{ $u->statusLabel() }}@if ($u->rolled_back_at)<br><span class="sd-muted">am {{ $u->rolled_back_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</span>@endif</td>
              <td><a class="sd-btn sec small" href="{{ route('updates.result', $u) }}">Details</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
