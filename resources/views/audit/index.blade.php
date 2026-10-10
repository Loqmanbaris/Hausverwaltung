@extends('layouts.app')
@section('title', 'Protokoll')
@section('content')
<div class="toolbar">
  <h1 style="margin:0">Protokoll</h1>
  <form class="search" method="GET" action="{{ route('audit.index') }}" role="search">
    <input type="text" name="q" value="{{ $q }}" placeholder="Suchen (Name, Ereignis, Adresse)" aria-label="Protokoll durchsuchen">
    <button class="btn" type="submit">Suchen</button>
  </form>
</div>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th>Zeitpunkt</th>
        <th>Benutzer</th>
        <th>Ereignis</th>
        <th>Einzelheiten</th>
        <th>IP-Adresse</th>
      </tr>
    </thead>
    <tbody>
      @forelse ($logs as $log)
        <tr>
          <td style="white-space:nowrap">{{ $log->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i:s') }}</td>
          <td>{{ $log->user_label ?? '–' }}</td>
          <td>{{ $log->eventLabel() }}</td>
          <td>{{ $log->description }}</td>
          <td class="mono">{{ $log->ip_address ?? '–' }}</td>
        </tr>
      @empty
        <tr><td colspan="5" class="muted">Keine Einträge gefunden.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<div class="pager">
  @if ($logs->previousPageUrl())
    <a class="btn small" href="{{ $logs->previousPageUrl() }}">Neuere Einträge</a>
  @else
    <span></span>
  @endif
  @if ($logs->nextPageUrl())
    <a class="btn small" href="{{ $logs->nextPageUrl() }}">Ältere Einträge</a>
  @endif
</div>
<p class="hint mt">Einträge können nicht bearbeitet oder gelöscht werden.</p>
@endsection
