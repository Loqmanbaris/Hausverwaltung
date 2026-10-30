@extends('portal.layout')
@section('title', 'Zählerstände')
@section('content')
<h1>Zählerstand melden</h1>
@if ($meters->isEmpty())
  <p class="sd-muted">Für Ihre Wohnung sind keine Zähler hinterlegt. Bitte melden Sie sich bei uns.</p>
@else
  @foreach ($meters as $m)
    <form method="POST" action="{{ route('portal.reading') }}" class="sd-card">
      @csrf
      <input type="hidden" name="meter_id" value="{{ $m->id }}">
      <strong>{{ \App\Models\Meter::KINDS[$m->kind] ?? $m->kind }}{{ $m->label ? ' · '.$m->label : '' }}</strong>
      <p class="sd-muted" style="margin:.2rem 0">Zähler-Nr. {{ $m->number ?: '–' }}@if ($m->last) · zuletzt {{ number_format((float) $m->last->value, 3, ',', '.') }} am {{ $m->last->read_on->format('d.m.Y') }}@endif</p>
      <div class="row" style="grid-template-columns:1fr 1fr">
        <div><label for="d-{{ $m->id }}">Datum</label><input id="d-{{ $m->id }}" type="date" name="read_on" value="{{ date('Y-m-d') }}" max="{{ date('Y-m-d') }}"></div>
        <div><label for="v-{{ $m->id }}">Neuer Stand</label><input id="v-{{ $m->id }}" name="value" inputmode="decimal" placeholder="z. B. 1234,5" required></div>
      </div>
      <button class="sd-btn" type="submit">Stand melden</button>
    </form>
  @endforeach
@endif
@endsection
