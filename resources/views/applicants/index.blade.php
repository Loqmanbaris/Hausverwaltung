@extends('layouts.app')
@section('title', 'Interessenten')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Models\Applicant;
  $open = collect(Applicant::OPEN)->sum(fn ($s) => (int) ($counts[$s] ?? 0));
@endphp
<div class="sd-head">
  <h1>Interessenten</h1>
  <div class="sd-actions"><a class="sd-btn" href="{{ route('applicants.create') }}">Interessent anlegen</a></div>
</div>
<p class="sd-muted">Alle, die eine Wohnung mieten möchten: von der Anfrage über Besichtigung und Selbstauskunft bis zur Zu- oder Absage und zum Mietvertrag. Ihre Daten werden nach einer Frist automatisch gelöscht (offen sechs Monate, nach Absage oder Vertrag drei Monate).</p>

@if ($pending->isNotEmpty())
  <div class="sd-card">
    <h2>Anfragen von der Webseite ({{ $pending->count() }})</h2>
    <p class="sd-muted" style="margin-top:0">Diese Anfragen sind noch keine Interessenten.</p>
    <div class="sd-table-wrap"><table class="sd"><tbody>
      @foreach ($pending as $q)
        <tr>
          <td>{{ $q->name }}<br><span class="sd-muted">{{ $q->created_at->format('d.m.Y') }}</span></td>
          <td>{{ $q->listing?->title ?? 'allgemeine Anfrage' }}@if ($q->listing?->unit)<br><span class="sd-muted">{{ $q->listing->unit->label }}, {{ $q->listing->unit->property?->name }}</span>@endif</td>
          <td style="white-space:nowrap">
            <form method="POST" action="{{ route('applicants.from_inquiry', $q) }}" style="display:inline">@csrf<button class="sd-btn small" type="submit">Als Interessent übernehmen</button></form>
            <a class="sd-btn sec small" href="{{ route('inquiries.show', $q) }}">Ansehen</a>
          </td>
        </tr>
      @endforeach
    </tbody></table></div>
  </div>
@endif

<div class="sd-card" style="padding:.6rem .8rem">
  <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center">
    <a class="sd-btn small {{ $status === 'offen' ? '' : 'sec' }}" href="{{ route('applicants.index', array_filter(['eigentuemer' => $ownerId])) }}">Laufend ({{ $open }})</a>
    @foreach (Applicant::STATUS as $k => $label)
      <a class="sd-btn small {{ $status === $k ? '' : 'sec' }}" href="{{ route('applicants.index', array_filter(['stand' => $k, 'eigentuemer' => $ownerId])) }}">{{ $label }} ({{ (int) ($counts[$k] ?? 0) }})</a>
    @endforeach
    @if ($owners->count() > 1)
      <form method="GET" style="margin-left:auto"><input type="hidden" name="stand" value="{{ $status }}"><select name="eigentuemer" onchange="this.form.submit()" aria-label="Eigentümer" style="padding:.35rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit"><option value="">Alle Eigentümer</option>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected($ownerId === $o->id)>{{ $o->name }}</option>@endforeach</select></form>
    @endif
  </div>
</div>

<div class="sd-card">
  @if ($items->isEmpty())
    <p class="sd-muted" style="margin:0">Hier ist nichts.</p>
  @else
    <div class="sd-table-wrap"><table class="sd">
      <thead><tr><th>Name</th><th>Wohnung</th><th>Stand</th><th>Nächster Schritt</th><th></th></tr></thead>
      <tbody>
        @foreach ($items as $a)
          @php
            $next = $a->viewings->where('status', 'geplant')->where('starts_at', '>=', now()->startOfDay())->first();
            $d = $a->disclosure;
          @endphp
          <tr>
            <td><a href="{{ route('applicants.show', $a) }}">{{ $a->name() }}</a><br><span class="sd-muted">{{ $a->created_at->format('d.m.Y') }} · {{ Applicant::SOURCES[$a->source] ?? '' }}</span></td>
            <td>{{ $a->unitLabel() }}</td>
            <td><span class="sd-badge">{{ $a->statusLabel() }}</span></td>
            <td>
              @if ($next) Besichtigung {{ $next->starts_at->format('d.m. H:i') }}
              @elseif ($d && $d->status === 'angefordert') Selbstauskunft offen
              @elseif ($d && $d->isFilled() && $a->status === 'selbstauskunft') Selbstauskunft prüfen
              @elseif ($a->status === 'neu') Antworten
              @else – @endif
            </td>
            <td><a class="sd-btn sec small" href="{{ route('applicants.show', $a) }}">Öffnen</a></td>
          </tr>
        @endforeach
      </tbody>
    </table></div>
  @endif
</div>
@endsection
