@extends('layouts.app')
@section('title', 'Vertragsvorlagen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head"><h1>Vertragsvorlagen</h1></div>
<p class="sd-muted">Aus diesen Vorlagen entstehen die Mietverträge. Die mitgelieferten Texte sind Muster ohne Gewähr. Bitte vor dem ersten Einsatz rechtlich prüfen lassen und bei Bedarf hier anpassen. Änderungen gelten nur für neu erstellte Verträge, bestehende bleiben unverändert.</p>
<div class="sd-card">
  <table class="sd">
    <thead><tr><th>Vorlage</th><th>Paragrafen</th><th>Stand</th><th></th></tr></thead>
    <tbody>
      @foreach ($kinds as $k => $info)
        <tr>
          <td>{{ $info['label'] }}</td>
          <td>{{ $info['count'] }}</td>
          <td>{{ $info['custom'] ? 'angepasst' : 'Muster' }}</td>
          <td><a class="sd-btn sec small" href="{{ route('contract-templates.edit', $k) }}">Bearbeiten</a></td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endsection
