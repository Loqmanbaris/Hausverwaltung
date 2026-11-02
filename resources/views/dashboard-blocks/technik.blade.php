@php($te = $ck['technik'])
<div class="sd-card">
  <h2>Schäden und Wartung</h2>
  @if (! $te)
    <p class="sd-muted" style="margin:0">Keine Angaben.</p>
  @else
    @if ($te['orders']->isEmpty())
      <p class="ck-done" style="margin:.2rem 0 .6rem">✓ Keine offenen Schäden.</p>
    @else
      <div class="ck-sub" style="margin-top:0">Offen ({{ $te['open'] }})</div>
      <ul class="ck-vac">
        @foreach ($te['orders'] as $o)
          <li><span>@if ($o->priority !== 'normal')<strong style="color:var(--danger)">{{ $o->priority === 'notfall' ? 'Notfall' : 'Dringend' }}:</strong> @endif<a href="{{ route('workorders.show', $o) }}">{{ \Illuminate\Support\Str::limit($o->title, 48) }}</a> <span class="sd-muted">· {{ $o->place() }}</span></span>
            <span class="ck-tag {{ $o->status === 'gemeldet' ? 'off' : 'next' }}">{{ $o->status === 'termin' && $o->appointment_at ? $o->appointment_at->format('d.m. H:i') : $o->statusLabel() }}</span></li>
        @endforeach
      </ul>
    @endif
    @if ($te['due']->isNotEmpty())
      <div class="ck-sub">Wartung in 30 Tagen ({{ $te['due_count'] }})</div>
      <ul class="ck-vac">
        @foreach ($te['due'] as $t)
          <li><span><a href="{{ route('maintenance.edit', $t) }}">{{ $t->title }}</a> <span class="sd-muted">· {{ $t->place() }}</span></span><span class="ck-tag {{ $t->next_due->format('Y-m-d') < date('Y-m-d') ? 'off' : 'next' }}" @if ($t->next_due->format('Y-m-d') < date('Y-m-d')) style="background:var(--danger-bg);color:var(--danger)" @endif>{{ $t->next_due->format('d.m.Y') }}</span></li>
        @endforeach
      </ul>
    @endif
    <p style="margin:.6rem 0 0;font-size:.9rem"><a href="{{ route('workorders.create') }}">Schaden erfassen</a> · <a href="{{ route('workorders.index') }}">Alle Aufträge</a> · <a href="{{ route('maintenance.index') }}">Wartungsplan</a></p>
  @endif
</div>
