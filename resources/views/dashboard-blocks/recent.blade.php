<div class="sd-card">
  <h2>Letzte Änderungen</h2>
  <div class="sd-table-wrap">
    <table class="sd">
      <tbody>
        @foreach ($recent as $log)
          <tr>
            <td style="white-space:nowrap">{{ $log->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</td>
            <td>{{ $log->user_label ?? '–' }}</td>
            <td>{{ $log->description ?: $log->eventLabel() }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  <p style="margin:.7rem 0 0"><a href="{{ route('audit.index') }}">Ganzes Protokoll öffnen</a></p>
</div>
