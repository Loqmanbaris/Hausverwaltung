{{-- Eine Zeile in „Mein Tag“: eigenes To-do/Termin oder Frist aus dem CRM --}}
@php($own = $r['source'] === 'own')
<div class="mt-row {{ $r['done'] ? 'mt-done' : '' }}">
  @if ($own && $r['kind'] === 'todo')
    <form method="POST" action="{{ route('tag.done', $r['id']) }}" style="margin:0">@csrf<input type="hidden" name="datum" value="{{ $r['date'] }}"><button class="mt-check" type="submit" title="{{ $r['done'] ? 'Wieder öffnen' : 'Erledigt' }}" aria-label="{{ $r['done'] ? 'Wieder öffnen' : 'Erledigt' }}">{{ $r['done'] ? '✓' : '' }}</button></form>
  @else
    <div class="mt-dot"><i @if ($r['source'] === 'frist') class="frist" @endif></i></div>
  @endif
  <div class="mt-time">{{ $r['time'] ?? '' }}</div>
  <div class="mt-main">
    @if ($r['source'] === 'frist')<span class="mt-tag {{ ($r['tone'] ?? '') === 'warn' ? 'warn' : '' }}">Frist</span>@elseif (! $own || $r['kind'] !== 'todo')<span class="mt-tag">Termin</span>@endif
    @if ($own && ! empty($r['recurring']))<span class="mt-tag">↻</span>@endif
    @if (! $own && ($r['url'] ?? '') !== '')<a class="mt-title" href="{{ $r['url'] }}">{{ $r['title'] }}</a>@else<span class="mt-title">{{ $r['title'] }}</span>@endif
    @php($sub = collect([$r['end'] ? 'bis '.$r['end'] : null, $r['detail'] !== '' ? $r['detail'] : null])->filter()->implode(' · '))
    @if ($sub !== '')<div class="mt-sub">{{ $sub }}</div>@endif
    @if ($own && ! empty($r['recurring']) && ! $r['done'])
      <form method="POST" action="{{ route('tag.skip', $r['id']) }}" style="margin:0" onsubmit="return confirm('Nur diesen Tag auslassen?')">@csrf<input type="hidden" name="datum" value="{{ $r['date'] }}"><button type="submit" class="mt-skip">diesen Tag auslassen</button></form>
    @endif
  </div>
  @if ($own)<a class="mt-edit" href="{{ route('tag.edit', $r['id']) }}" title="Bearbeiten" aria-label="Bearbeiten">✎</a>@endif
</div>
