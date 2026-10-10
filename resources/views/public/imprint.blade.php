@extends('layouts.public')
@section('title', 'Impressum – '.config('app.name'))
@section('content')
<div class="pub-text">
  <h1>Impressum</h1>

  @if (! $complete && auth()->check() && auth()->user()->isAdmin())
    <div class="alert warn">Die Angaben sind noch nicht vollständig. Bitte unter „Einstellungen“ ergänzen. Diesen Hinweis sehen nur Administratoren.</div>
  @endif

  <h2>Angaben gemäß § 5 DDG</h2>
  <p>
    {{ $s['imprint_name'] ?? 'Angaben werden ergänzt' }}<br>
    @if ($s['imprint_street']){{ $s['imprint_street'] }}<br>@endif
    @if ($s['imprint_zip'] || $s['imprint_city']){{ trim(($s['imprint_zip'] ?? '').' '.($s['imprint_city'] ?? '')) }}@endif
  </p>

  <h2>Kontakt</h2>
  <p>
    @if ($s['imprint_email'])E-Mail: <a href="mailto:{{ $s['imprint_email'] }}">{{ $s['imprint_email'] }}</a><br>@endif
    @if ($s['imprint_phone'])Telefon: {{ $s['imprint_phone'] }}@endif
  </p>

  @if ($s['imprint_vat_id'])
    <h2>Umsatzsteuer-ID</h2>
    <p>Umsatzsteuer-Identifikationsnummer gemäß § 27a Umsatzsteuergesetz: {{ $s['imprint_vat_id'] }}</p>
  @endif
</div>
@endsection
