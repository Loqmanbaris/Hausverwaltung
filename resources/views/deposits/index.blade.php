@extends('layouts.app')
@section('title', 'Kautionen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  $eur = fn ($c) => number_format($c / 100, 2, ',', '.').' €';
  $tabs = ['laufend' => 'Laufende Mietverhältnisse', 'rueckgabe' => 'Ausgezogen, noch nicht abgerechnet', 'abgerechnet' => 'Abgerechnet'];
@endphp
<div class="sd-head"><h1>Kautionen</h1></div>
<p class="sd-muted">Alle Mietverträge mit Kaution: Ratenzahlung, Bestand, Zinsen und Abrechnung beim Auszug. Den Betrag der Kaution trägst du wie bisher im Mietvertrag ein.</p>
<div class="sd-card" style="padding:.6rem .8rem">
  <div style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center">
    @foreach ($tabs as $k => $label)<a class="sd-btn small {{ $tab === $k ? '' : 'sec' }}" href="{{ route('deposit.index', array_filter(['stand' => $k, 'eigentuemer' => $ownerId])) }}">{{ $label }}</a>@endforeach
    @if ($owners->count() > 1)
      <form method="GET" style="margin-left:auto"><input type="hidden" name="stand" value="{{ $tab }}"><select name="eigentuemer" onchange="this.form.submit()" aria-label="Eigentümer" style="padding:.35rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit"><option value="">Alle Eigentümer</option>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected($ownerId === $o->id)>{{ $o->name }}</option>@endforeach</select></form>
    @endif
  </div>
</div>
<div class="sd-card">
  @if ($rows === [])
    <p class="sd-muted" style="margin:0">Hier ist nichts.</p>
  @else
    <div class="sd-table-wrap"><table class="sd">
      <thead><tr><th>Mietvertrag</th><th class="rt-num">Vereinbart</th><th class="rt-num">Eingezahlt</th><th class="rt-num">Bestand</th><th>Stand</th></tr></thead>
      <tbody>
        @foreach ($rows as $r)
          @php
            $l = $r['lease'];
            $s = $r['s'];
          @endphp
          <tr>
            <td><a href="{{ route('deposit.show', $l) }}">{{ $l->title() }}</a>@if ($l->end_date)<br><span class="sd-muted">Auszug {{ $l->end_date->format('d.m.Y') }}</span>@endif</td>
            <td class="rt-num">{{ $eur($s['agreed']) }}</td>
            <td class="rt-num">{{ $eur($s['paid']) }}</td>
            <td class="rt-num">{{ $eur($s['held']) }}</td>
            <td>
              @if ($s['settled']) <span class="sd-badge">abgerechnet</span>
              @elseif ($r['ended']) <span class="sd-badge" style="background:#fffbeb;color:#92400e">Abrechnung offen</span>
              @elseif ($s['overdue'] > 0) <span class="sd-badge" style="background:#fef2f2;color:#b91c1c">{{ $eur($s['overdue']) }} überfällig</span>
              @elseif ($s['open'] > 0) <span class="sd-badge">{{ $eur($s['open']) }} noch offen</span>
              @else <span class="sd-badge" style="background:#ecfdf5;color:#065f46">vollständig</span> @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table></div>
  @endif
</div>
@endsection
