@extends('portal.layout')
@section('title', 'Konto')
@section('content')
<h1>Ihr Mieterkonto</h1>
@forelse ($account as $a)
  <div class="sd-card">
    <strong>{{ $a['lease']->unit?->label }}, {{ $a['lease']->unit?->property?->addressLine() }}</strong>
    <p style="margin:.4rem 0">@if ($a['open'] > 0.004)Offen: <strong>{{ number_format($a['open'], 2, ',', '.') }} €</strong>@else Alles bezahlt.@endif</p>
    @if ($a['rows'] !== [])
      <table>
        <thead><tr><th>Posten</th><th>Fällig</th><th class="n">Soll</th><th class="n">Bezahlt</th><th class="n">Offen</th></tr></thead>
        <tbody>@foreach ($a['rows'] as $r)<tr><td>{{ $r['label'] }}</td><td>{{ $r['due']->format('d.m.Y') }}</td><td class="n">{{ number_format($r['total'], 2, ',', '.') }}</td><td class="n">{{ number_format($r['paid'], 2, ',', '.') }}</td><td class="n">{{ $r['open'] > 0.004 ? number_format($r['open'], 2, ',', '.') : '–' }}</td></tr>@endforeach</tbody>
      </table>
    @endif
    @if ($a['payments']->isNotEmpty())
      <p style="margin:.8rem 0 .2rem"><strong>Eingegangene Zahlungen</strong></p>
      <table><tbody>@foreach ($a['payments'] as $p)<tr><td>{{ $p->paid_on->format('d.m.Y') }}</td><td class="n">{{ number_format((float) $p->amount, 2, ',', '.') }} €</td></tr>@endforeach</tbody></table>
    @endif
  </div>
@empty
  <p class="sd-muted">Es ist kein Mietvertrag hinterlegt.</p>
@endforelse
<p class="sd-muted">Stimmt etwas nicht? Schreiben Sie uns bitte, wir prüfen es.</p>
@endsection
