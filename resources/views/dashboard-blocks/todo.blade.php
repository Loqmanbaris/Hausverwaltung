<div class="sd-card">
  <h2>Zu erledigen</h2>
  @if ($ck['todo'] === [])
    <p class="ck-done">✓ Alles erledigt.</p>
  @else
    <ul class="ck-todo">
      @foreach ($ck['todo'] as $t)
        <li><a href="{{ $t['url'] }}"><span class="ck-num {{ $t['tone'] }}">{{ $t['count'] }}</span><span>{{ $t['label'] }}</span><span class="go">›</span></a></li>
      @endforeach
    </ul>
  @endif
</div>
