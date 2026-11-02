@php($rd = \App\Support\Rent\RentDashboard::forUser(auth()->user(), $selected ? (int) $selected->id : null))
@if ($rd)
  <div class="sd-card">
    <h2>Mieten</h2>
    <div class="db-stats" style="margin:.2rem 0 .8rem">
      <a class="db-stat" href="{{ route('rent.open') }}" style="text-decoration:none">
        <strong style="{{ $rd['overdue_sum'] > 0.004 ? 'color:var(--danger)' : 'color:var(--ok)' }}">{{ \App\Support\Money::format($rd['overdue_sum']) }}</strong>
        <span>überfällig{{ $rd['overdue_leases'] > 0 ? ' bei '.$rd['overdue_leases'].' Vertr'.($rd['overdue_leases'] === 1 ? 'ag' : 'ägen') : '' }}</span>
      </a>
      <a class="db-stat" href="{{ route('leases.index') }}" style="text-decoration:none"><strong>{{ $rd['active'] }}</strong><span>laufende Verträge</span></a>
      <a class="db-stat" href="{{ route('leases.index') }}" style="text-decoration:none"><strong>{{ $rd['vacant'] }}</strong><span>Einheiten leer</span></a>
    </div>
    @if ($rd['ending']->isNotEmpty() || $rd['changes']->isNotEmpty() || count($rd['index_due']) > 0)
      <strong>Demnächst (nächste {{ $rd['days'] }} Tage)</strong>
      <ul class="db-list">
        @foreach ($rd['ending'] as $l)
          <li><a href="{{ route('leases.show', $l) }}">{{ $l->unit?->label }} – {{ $l->tenant?->display_name }}</a>: Vertrag endet am {{ $l->end_date->format('d.m.Y') }}</li>
        @endforeach
        @foreach ($rd['changes'] as $r)
          <li><a href="{{ route('leases.show', $r->lease_id) }}">{{ $r->lease?->unit?->label }} – {{ $r->lease?->tenant?->display_name }}</a>: {{ $r->reasonLabel() }} ab {{ $r->valid_from->format('d.m.Y') }}</li>
        @endforeach
        @foreach ($rd['index_due'] as $i)
          <li><a href="{{ route('leases.show', $i['lease']) }}">{{ $i['lease']->unit?->label }} – {{ $i['lease']->tenant?->display_name }}</a>: Indexanpassung möglich (letzte Änderung {{ $i['since']->format('m/Y') }})</li>
        @endforeach
      </ul>
    @endif
  </div>
@endif
