@extends('portal.layout')
@section('title', 'Schaden melden')
@section('content')
<h1>Schaden melden</h1>
<p class="sd-muted">Bei einem Notfall (Wasserrohrbruch, Gasgeruch, Heizungsausfall im Winter) rufen Sie bitte sofort die Notfallnummer an, die Ihnen genannt wurde.</p>
<form method="POST" action="{{ route('portal.damage.store') }}" class="sd-card">
  @csrf
  @if ($leases->count() > 1)
    <div class="row"><label for="s-l">Wohnung</label><select id="s-l" name="lease_id">@foreach ($leases as $l)<option value="{{ $l->id }}">{{ $l->unit?->label }}, {{ $l->unit?->property?->addressLine() }}</option>@endforeach</select></div>
  @else
    <input type="hidden" name="lease_id" value="{{ $leases->first()?->id }}">
  @endif
  <div class="row"><label for="s-t">Worum geht es?</label><input id="s-t" name="title" maxlength="120" required value="{{ old('title') }}" placeholder="z. B. Wasserfleck an der Decke im Bad"></div>
  <div class="row"><label for="s-d">Beschreibung</label><textarea id="s-d" name="text" rows="5" maxlength="3000" required>{{ old('text') }}</textarea></div>
  <button class="sd-btn" type="submit">Meldung senden</button>
</form>
@endsection
