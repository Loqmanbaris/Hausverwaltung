<div class="sd-card">
  <h2>Mieteingang der letzten 12 Monate</h2>
  @if ($ck['chart'] === [])
    <p class="sd-muted" style="margin:0">Noch keine Mieten erfasst.</p>
  @else
    @include('partials.cockpit-chart', ['chart' => $ck['chart']])
  @endif
</div>
