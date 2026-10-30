@extends('layouts.app')
@section('title', 'Laufende Verträge')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Models\ServiceContract;
  $eur = fn ($v) => number_format((float) $v, 2, ',', '.').' €';
  $sel = 'padding:.35rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit';
  $stateStyle = ['dringend' => 'background:#fef2f2;color:#b91c1c', 'bald' => 'background:#fffbeb;color:#92400e', 'abgelaufen' => 'background:#fef2f2;color:#b91c1c', 'ok' => '', 'jederzeit' => '', 'gekuendigt' => 'background:#eef2ff;color:#3730a3', 'beendet' => ''];
@endphp
<div class="sd-head">
  <h1>Laufende Verträge</h1>
  <div class="sd-actions"><a class="sd-btn" href="{{ route('svc.create', array_filter(['objekt' => $propertyId])) }}">Vertrag anlegen</a></div>
</div>
<p class="sd-muted">Strom, Gas, Wasser, Versicherung, Hausmeister und andere Verträge mit Anbietern: mit Laufzeit, Kündigungsfrist und Kosten. Das CRM erinnert dich rechtzeitig vor dem letzten Kündigungstermin.</p>

@if ($attention !== [])
  <div class="sd-card">
    <h2>Jetzt kümmern</h2>
    <div class="sd-table-wrap"><table class="sd"><tbody>
      @foreach ($attention as $a)
        @php
          $c = $a['contract'];
          $i = $a['info'];
        @endphp
        <tr>
          <td><a href="{{ route('svc.show', $c) }}">{{ $c->title }}</a><br><span class="sd-muted">{{ $c->place() }}</span></td>
          <td><span class="sd-badge" style="{{ $stateStyle[$i['state']] ?? '' }}">{{ $i['label'] }}</span></td>
          <td class="rt-num">{{ $i['days'] !== null ? ($i['days'] < 0 ? 'seit '.abs($i['days']).' Tagen' : 'in '.$i['days'].' Tagen') : '' }}</td>
        </tr>
      @endforeach
    </tbody></table></div>
  </div>
@endif

<form method="GET" class="sd-card" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:flex-end">
  <div class="sd-field" style="margin:0"><label for="f-o">Eigentümer</label><select id="f-o" name="eigentuemer" style="{{ $sel }}"><option value="">Alle</option>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected($ownerId === $o->id)>{{ $o->name }}</option>@endforeach</select></div>
  <div class="sd-field" style="margin:0"><label for="f-p">Objekt</label><select id="f-p" name="objekt" style="{{ $sel }}"><option value="">Alle</option>@foreach ($properties as $p)<option value="{{ $p->id }}" @selected($propertyId === $p->id)>{{ $p->name }}</option>@endforeach</select></div>
  <div class="sd-field" style="margin:0"><label for="f-a">Art</label><select id="f-a" name="art" style="{{ $sel }}"><option value="">Alle</option>@foreach (ServiceContract::KINDS as $k => $label)<option value="{{ $k }}" @selected($kind === $k)>{{ $label }}</option>@endforeach</select></div>
  <div class="sd-field" style="margin:0"><label for="f-s">Stand</label><select id="f-s" name="stand" style="{{ $sel }}"><option value="aktiv" @selected($stand === 'aktiv')>Laufend</option><option value="alle" @selected($stand === 'alle')>Alle</option><option value="beendet" @selected($stand === 'beendet')>Beendet</option></select></div>
  <button class="sd-btn small" type="submit">Anzeigen</button>
</form>

<div class="sd-card">
  @if ($items->isEmpty())
    <p class="sd-muted" style="margin:0">Hier ist noch nichts eingetragen.</p>
  @else
    <div class="sd-table-wrap"><table class="sd">
      <thead><tr><th>Vertrag</th><th>Objekt</th><th>Laufzeit und Kündigung</th><th class="rt-num">Kosten im Jahr</th></tr></thead>
      <tbody>
        @foreach ($items as $c)
          @php($i = $infos[$c->id])
          <tr>
            <td><a href="{{ route('svc.show', $c) }}">{{ $c->title }}</a><br><span class="sd-muted">{{ $c->kindLabel() }}{{ $c->provider ? ' · '.$c->provider : '' }}</span></td>
            <td>{{ $c->place() }}</td>
            <td><span class="sd-badge" style="{{ $stateStyle[$i['state']] ?? '' }}">{{ $i['label'] }}</span></td>
            <td class="rt-num">{{ $c->amount !== null ? $eur($c->annualCost()) : '–' }}@if ($c->amount !== null)<br><span class="sd-muted">{{ $eur($c->amount) }} {{ $c->intervalLabel() }}</span>@endif</td>
          </tr>
        @endforeach
        <tr class="rt-sum"><td colspan="3">Summe der laufenden Verträge</td><td class="rt-num">{{ $eur($total) }}</td></tr>
      </tbody>
    </table></div>
    @if ($byKind->count() > 1)
      <p class="sd-muted" style="margin:.6rem 0 0">Nach Art: @foreach ($byKind as $k => $v){{ ServiceContract::KINDS[$k] ?? $k }} {{ $eur($v) }}{{ ! $loop->last ? ' · ' : '' }}@endforeach</p>
    @endif
  @endif
</div>
@endsection
