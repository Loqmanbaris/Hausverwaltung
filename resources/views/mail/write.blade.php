@extends('layouts.app')
@section('title', $fwd ? 'Weiterleiten' : 'Neue E-Mail')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>{{ $fwd ? 'Weiterleiten' : 'Neue E-Mail' }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ $fwd ? route('inbox.show', $fwd->id) : route('inbox.index') }}">Zurück</a></div>
</div>
@include('mail.partials.tabs')

@unless ($send)
  <div class="sd-err">Der E-Mail-Versand ist noch nicht eingerichtet.@if (auth()->user()->isAdmin()) <a href="{{ route('mail.settings') }}">Jetzt einrichten</a>@else Bitte den Administrator fragen.@endif</div>
@endunless

<form method="POST" action="{{ route('mail.write.send') }}" enctype="multipart/form-data" class="sd-card">
  @csrf
  @if ($fwd)<input type="hidden" name="weiter" value="{{ $fwd->id }}">@endif
  <div class="sd-field"><label for="f-to">An *</label><input id="f-to" name="to" type="text" required value="{{ old('to', $to) }}" placeholder="name@beispiel.de (mehrere mit Komma trennen)" autocomplete="email"></div>
  <div class="sd-field"><label for="f-subject">Betreff *</label><input id="f-subject" name="subject" type="text" required maxlength="200" value="{{ old('subject', $subject) }}"></div>
  <div class="sd-field"><label for="f-body">Text *</label><textarea id="f-body" name="body" rows="14" required style="width:100%;font:inherit;padding:.6rem .7rem;border:1px solid var(--line-strong);border-radius:8px">{{ old('body', $body) }}</textarea></div>
  @if ($fwd && $fwd->attachments)
    <label style="display:flex;gap:.5rem;align-items:center;font-weight:500;margin:0 0 .8rem">
      <input type="checkbox" name="mit_anhaengen" value="1" @checked(old('mit_anhaengen', '1') === '1')>
      Anhänge mitschicken ({{ implode(', ', array_column($fwd->attachments, 'name')) }})
    </label>
  @endif
  <div class="sd-field"><label for="f-dateien">Anhänge (höchstens 5, je 10 MB)</label><input id="f-dateien" name="dateien[]" type="file" multiple></div>
  <p class="sd-muted" style="font-size:.88rem">Gesendet wird von der Absender-Adresse aus „E-Mail einrichten“. Eine Kopie geht wie immer an die Kopie-Adresse.</p>
  <div class="sd-actions"><button class="sd-btn" type="submit" @disabled(! $send)>Senden</button></div>
</form>
@endsection
