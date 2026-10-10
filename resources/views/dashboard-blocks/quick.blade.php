<div class="ck-quick">
  @forelse ($quick as $q)
    <a class="ck-qbtn" href="{{ $q['url'] }}">{{ $q['label'] }}</a>
  @empty
    <span class="sd-muted">Keine Knöpfe gewählt.</span>
  @endforelse
  <a class="ck-qedit" href="{{ route('dashboard.layout') }}#schnellzugriff">Knöpfe ändern</a>
</div>
