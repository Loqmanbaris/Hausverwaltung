@extends('layouts.app')
@section('title', 'Anfragen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Models\Inquiry;
  $tabs = ['offen' => 'Offen', 'neu' => 'Neu', 'in_bearbeitung' => 'In Bearbeitung', 'erledigt' => 'Erledigt', 'alle' => 'Alle'];
  $tabCount = ['offen' => ($counts['neu'] ?? 0) + ($counts['in_bearbeitung'] ?? 0), 'neu' => $counts['neu'] ?? 0, 'in_bearbeitung' => $counts['in_bearbeitung'] ?? 0, 'erledigt' => $counts['erledigt'] ?? 0, 'alle' => $counts->sum()];
@endphp
<style>
.iq-tabs{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 1rem}
.iq-tabs a{padding:.4rem .8rem;border:1px solid #c5ccd3;border-radius:2rem;text-decoration:none;color:var(--ink);background:#fff}
.iq-tabs a.on{background:var(--ink);color:#fff;border-color:var(--ink)}
.iq-neu{font-weight:700}
</style>
<div class="sd-head">
  <h1>Anfragen</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('listings.index') }}">Inserate</a></div>
</div>
<p class="sd-muted">Anfragen aus dem Kontaktformular der Webseite. Erledigte Anfragen werden nach sechs Monaten automatisch gelöscht.</p>
<div class="iq-tabs">
  @foreach ($tabs as $k => $label)
    <a href="{{ route('inquiries.index', ['status' => $k]) }}" @class(['on' => $status === $k])>{{ $label }} ({{ $tabCount[$k] }})</a>
  @endforeach
</div>
<div class="sd-card">
  @if ($items->isEmpty())
    <p class="sd-muted" style="margin:0">Keine Anfragen.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Eingang</th><th>Von</th><th>Zu</th><th>Status</th></tr></thead>
        <tbody>
          @foreach ($items as $i)
            <tr @class(['iq-neu' => $i->status === 'neu'])>
              <td>{{ $i->created_at->format('d.m.Y H:i') }}</td>
              <td>@if ($i->topic === 'schaden')<span class="sd-badge" style="background:#fdecec;color:#b42318">Schaden</span> @endif<a href="{{ route('inquiries.show', $i) }}">{{ $i->name }}</a>{{ $i->company ? ', '.$i->company : '' }}<br><span class="sd-muted">{{ \Illuminate\Support\Str::limit($i->message, 80) }}</span></td>
              <td>{{ $i->listing?->title ?? 'allgemeine Anfrage' }}@if ($i->date_from)<br><span class="sd-muted">{{ $i->date_from->format('d.m.') }}–{{ $i->date_to?->format('d.m.Y') }}</span>@endif</td>
              <td>{{ Inquiry::STATUS[$i->status] ?? $i->status }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
