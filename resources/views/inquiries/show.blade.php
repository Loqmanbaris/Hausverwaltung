@extends('layouts.app')
@section('title', 'Anfrage')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php use App\Models\Inquiry; $l = $inquiry->listing; @endphp
<style>.iq-dl{display:grid;grid-template-columns:max-content 1fr;gap:.3rem 1rem;margin:0}.iq-dl dt{color:var(--muted)}.iq-dl dd{margin:0;overflow-wrap:anywhere}</style>
<div class="sd-head">
  <h1>Anfrage von {{ $inquiry->name }}</h1>
  <div class="sd-actions">
    @if (\Illuminate\Support\Facades\Schema::hasTable('work_orders') && ($wo = \App\Models\WorkOrder::query()->where('inquiry_id', $inquiry->id)->first()))<a class="sd-btn" href="{{ route('workorders.show', $wo) }}">Schadensfall {{ $wo->number() }}</a>@endif
    @if ($inquiry->email)<a class="sd-btn" href="{{ route('mail.compose', ['art' => 'anfrage', 'ids' => $inquiry->id]) }}">Per E-Mail antworten</a>@endif
    @if ($inquiry->topic !== 'schaden')<form method="POST" action="{{ route('applicants.from_inquiry', $inquiry) }}" style="display:inline">@csrf<button class="sd-btn sec" type="submit">Als Interessent übernehmen</button></form>@endif
    <a class="sd-btn sec" href="{{ route('inquiries.index') }}">Alle Anfragen</a>
  </div>
</div>
<div class="sd-card">
  <dl class="iq-dl">
    <dt>Eingang</dt><dd>{{ $inquiry->created_at->format('d.m.Y H:i') }} Uhr</dd>
    <dt>Zu</dt><dd>@if ($l)<a href="{{ route('listings.edit', $l) }}">{{ $l->title }}</a> ({{ $l->unit?->label }}, {{ $l->unit?->property?->name }})@else allgemeine Anfrage @endif</dd>
    <dt>Name</dt><dd>{{ $inquiry->name }}</dd>
    @if ($inquiry->company)<dt>Firma</dt><dd>{{ $inquiry->company }}</dd>@endif
    <dt>E-Mail</dt><dd><a href="mailto:{{ $inquiry->email }}?subject={{ rawurlencode('Ihre Anfrage'.($l ? ': '.$l->title : '')) }}">{{ $inquiry->email }}</a></dd>
    @if ($inquiry->phone)<dt>Telefon</dt><dd><a href="tel:{{ preg_replace('/[^0-9+]/', '', $inquiry->phone) }}">{{ $inquiry->phone }}</a></dd>@endif
    @if ($inquiry->persons)<dt>Personen</dt><dd>{{ $inquiry->persons }}</dd>@endif
    @if ($inquiry->date_from)<dt>Zeitraum</dt><dd>{{ $inquiry->date_from->format('d.m.Y') }} bis {{ $inquiry->date_to?->format('d.m.Y') ?? 'offen' }}</dd>@endif
  </dl>
  <h3 style="font-size:1rem;margin:1rem 0 .3rem">Nachricht</h3>
  <p style="white-space:pre-line;margin:0">{{ $inquiry->message }}</p>
  @if ($l && $l->kind === 'montage')
    <p style="margin:1rem 0 0"><a class="sd-btn small" href="{{ route('bookings.index', ['unit' => $l->unit_id, 'anfrage' => $inquiry->id, 'monat' => $inquiry->date_from?->format('Y-m')]) }}#neu">Als Reservierung eintragen</a></p>
  @endif
</div>
<div class="sd-card">
  <h2>Bearbeitung</h2>
  <form method="POST" action="{{ route('inquiries.update', $inquiry) }}">
    @csrf @method('PUT')
    @include('partials.sd-select', ['name' => 'status', 'label' => 'Status', 'options' => Inquiry::STATUS, 'value' => $inquiry->status, 'noBlank' => true])
    @include('partials.sd-textarea', ['name' => 'note', 'label' => 'Notiz (intern)', 'value' => $inquiry->note])
    <button class="sd-btn" type="submit">Speichern</button>
  </form>
</div>
<div class="sd-card">
  <form method="POST" action="{{ route('inquiries.destroy', $inquiry) }}" onsubmit="return confirm('Anfrage endgültig löschen?')">@csrf @method('DELETE')<button class="sd-btn danger small" type="submit">Anfrage löschen</button></form>
</div>
@endsection
