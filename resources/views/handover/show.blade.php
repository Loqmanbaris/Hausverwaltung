@extends('layouts.app')
@section('title', $p->title())
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('handover.body-styles')
<div class="sd-head">
  <h1>{{ $p->title() }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('leases.show', $p->lease_id) }}#uebergabe">Zum Mietvertrag</a>
    <a class="sd-btn" href="{{ route('handover.print', $p) }}" target="_blank" rel="noopener">Drucken</a>
    <a class="sd-btn sec" href="{{ route('documents.pdf', ['art' => 'uebergabe', 'id' => $p->id]) }}">PDF</a>
    <a class="sd-btn" href="{{ route('mail.compose', ['art' => 'uebergabe', 'ids' => $p->id]) }}">Per E-Mail senden</a>
    @if ($p->isDraft())<a class="sd-btn sec" href="{{ route('handover.edit', $p) }}">Bearbeiten</a>@endif
  </div>
</div>
<p>Status: <strong>{{ $p->isDraft() ? 'Entwurf' : 'Abgeschlossen' }}</strong>@if (! $p->isDraft()) am {{ $p->signed_at?->format('d.m.Y') }}, {{ $p->sign_method === 'tablet' ? 'am Tablet unterschrieben' : 'auf Papier unterschrieben' }}@endif</p>
@if (! $p->isDraft() && \Illuminate\Support\Facades\Route::has('workorders.from-handover'))
  @php
    $defects = [];
    foreach ($p->rooms ?? [] as $ri => $room) {
        foreach ($room['items'] ?? [] as $ii => $it) {
            if (($it['state'] ?? '') === 'mangel' || (($it['state'] ?? '') === 'gebrauch' && ! empty($it['note']))) {
                $defects[] = ['key' => $ri.'-'.$ii, 'room' => $room['name'], 'item' => $it['name'], 'note' => $it['note'] ?? '', 'state' => $it['state']];
            }
        }
    }
    $taken = \App\Models\WorkOrder::query()->where('handover_protocol_id', $p->id)->pluck('title')->all();
  @endphp
  @if ($defects !== [])
    <div class="sd-card" style="max-width:56rem;border-left:4px solid #b42318">
      <h2>Mängel als Schadensfall übernehmen</h2>
      <form method="POST" action="{{ route('workorders.from-handover', $p) }}">
        @csrf
        @foreach ($defects as $d)
          @php $done = in_array($d['item'].' ('.$d['room'].')', $taken, true); @endphp
          <label style="display:flex;gap:.5rem;align-items:flex-start;font-weight:500;margin:.25rem 0">
            <input type="checkbox" name="items[]" value="{{ $d['key'] }}" style="width:auto;margin-top:.3rem" @checked($d['state'] === 'mangel' && ! $done) @disabled($done)>
            <span><strong>{{ $d['item'] }}</strong> ({{ $d['room'] }}){{ $d['note'] ? ': '.$d['note'] : '' }}{{ $d['state'] === 'gebrauch' ? ' – Gebrauchsspuren' : '' }}@if ($done) <span class="sd-muted">· schon übernommen</span>@endif</span>
          </label>
        @endforeach
        <button class="sd-btn" type="submit" style="margin-top:.5rem">Als Schadensfälle anlegen</button>
      </form>
    </div>
  @endif
@endif
<div style="background:#fff;border:1px solid #e3e6ea;border-radius:.6rem;padding:1.2rem 1.4rem;max-width:56rem;overflow-x:auto">
  @include('handover.body')
</div>
@endsection
