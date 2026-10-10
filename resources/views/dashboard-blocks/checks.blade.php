<details class="sd-card ck-more">
  <summary>Stammdaten zu ergänzen ({{ array_sum(array_column($checks, 'count')) }})</summary>
    <p class="sd-muted" style="margin:.6rem 0">Diese Angaben fehlen noch. Sie sind keine Pflicht, werden aber später gebraucht.</p>
    @foreach ($checks as $check)
      <div style="margin-bottom:.9rem">
        <strong>{{ $check['title'] }} ({{ $check['count'] }})</strong>
        <div class="sd-muted" style="font-size:.9rem">{{ $check['hint'] }}</div>
        <ul class="db-list">
          @foreach ($check['items'] as $item)
            <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
          @endforeach
          @if ($check['count'] > count($check['items']))
            <li class="sd-muted">und {{ $check['count'] - count($check['items']) }} weitere</li>
          @endif
        </ul>
      </div>
    @endforeach
</details>
