@extends('portal.layout')
@section('title', 'Anmelden')
@section('content')
<h1>Mieterportal</h1>
@if ($info)<p class="sd-muted">{{ $info }}</p>@endif
@if ($sent)
  <div class="ok" role="status">Wenn zu dieser Adresse ein Zugang besteht, haben wir Ihnen einen Anmelde-Link geschickt. Er gilt 20 Minuten. Bitte schauen Sie auch im Spam-Ordner nach.</div>
@endif
<p>Hier sehen Sie Ihr Mieterkonto und Ihre Dokumente, melden Zählerstände und Schäden. Geben Sie die E-Mail-Adresse ein, die wir von Ihnen haben. Sie bekommen einen Link zum Anmelden, ein Passwort brauchen Sie nicht.</p>
<form method="POST" action="{{ route('portal.link') }}" class="sd-card">
  @csrf
  <div class="row"><label for="p-email">Ihre E-Mail-Adresse</label><input id="p-email" type="email" name="email" required autocomplete="email" value="{{ old('email') }}"></div>
  <button class="sd-btn" type="submit">Anmelde-Link senden</button>
</form>
@endsection
