@extends('layouts.app')
@section('title', 'E-Mail senden')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.mc-items{width:100%;border-collapse:collapse}
.mc-items td{padding:.55rem .4rem;border-top:1px solid var(--line);vertical-align:middle}
.mc-items tr:first-child td{border-top:0}
.mc-items input[type=text]{width:100%;min-width:14rem;font:inherit;padding:.4rem .55rem;border:1px solid var(--line-strong);border-radius:7px}
.mc-items .warn{color:#b42318;font-size:.85rem}
.mc-ph{display:flex;flex-wrap:wrap;gap:.35rem;margin:.35rem 0 0}
.mc-ph button{font:inherit;font-size:.8rem;border:1px solid var(--line-strong);background:#fff;border-radius:1rem;padding:.12rem .55rem;cursor:pointer}
.mc-ph button:hover{border-color:var(--ink)}
.mc-text{width:100%;font:inherit;padding:.6rem .7rem;border:1px solid var(--line-strong);border-radius:8px}
.mc-pdf{display:inline-flex;align-items:center;gap:.35rem;font-size:.88rem;white-space:nowrap}
.mc-pdf::before{content:"PDF";font-size:.65rem;font-weight:800;background:#b42318;color:#fff;border-radius:3px;padding:.05rem .3rem}
</style>
<div class="sd-head">
  <h1>{{ $box['title'] }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ $box['back'] }}">Zurück</a></div>
</div>

@unless ($configured)
  <div class="sd-err">Der E-Mail-Versand ist noch nicht eingerichtet. @if (auth()->user()->isAdmin())<a href="{{ route('mail.settings') }}">Jetzt einrichten</a>@else Bitte den Administrator fragen. @endif</div>
@endunless

<form method="POST" action="{{ route('mail.send') }}" enctype="multipart/form-data" id="mc-form">
  @csrf
  <input type="hidden" name="art" value="{{ $query['art'] }}">
  <input type="hidden" name="ids" value="{{ $query['ids'] }}">
  @if ($query['vertrag'])<input type="hidden" name="vertrag" value="{{ $query['vertrag'] }}">@endif

  <div class="sd-card">
    <h2>{{ count($box['items']) === 1 ? 'Empfänger' : 'Empfänger ('.count($box['items']).')' }}</h2>
    <table class="mc-items">
      @foreach ($box['items'] as $it)
        @php($addr = old('to.'.$it['key'], implode(', ', $it['addresses'])))
        <tr>
          <td style="width:2rem"><input type="checkbox" name="items[]" value="{{ $it['key'] }}" aria-label="{{ $it['label'] }} auswählen" @checked(old('items') ? in_array($it['key'], old('items'), true) : $it['addresses'] !== [])></td>
          <td><strong>{{ $it['label'] }}</strong>@if ($it['addresses'] === [])<div class="warn">Keine E-Mail-Adresse hinterlegt – Adresse eintragen oder per Post schicken.</div>@endif</td>
          <td style="width:40%"><input type="text" name="to[{{ $it['key'] }}]" value="{{ $addr }}" placeholder="name@beispiel.de" aria-label="E-Mail-Adresse für {{ $it['label'] }}"></td>
          <td style="width:1%">@if ($it['pdf_name'])<a class="mc-pdf" href="{{ route('mail.preview', array_filter($query) + ['key' => $it['key']]) }}" target="_blank" rel="noopener" title="{{ $it['pdf_name'] }}">ansehen</a>@endif</td>
        </tr>
      @endforeach
    </table>
    @if ($copy)<p class="sd-muted" style="font-size:.88rem;margin:.6rem 0 0">Eine Blindkopie geht an {{ $copy }}. Jede E-Mail geht einzeln an ihren Empfänger, niemand sieht die anderen Adressen.</p>@endif
  </div>

  <div class="sd-card">
    <h2>Nachricht</h2>
    <div class="sd-field">
      <label for="mc-subject">Betreff *</label>
      <input id="mc-subject" name="subject" type="text" maxlength="200" required value="{{ old('subject', $subject) }}">
    </div>
    <div class="sd-field">
      <label for="mc-body">Text *</label>
      <textarea id="mc-body" class="mc-text" name="body" rows="14" required>{{ old('body', $body) }}</textarea>
      @if (! $single)
        <small class="sd-muted">Die Wörter in eckigen Klammern werden für jeden Empfänger ersetzt. Zum Einfügen anklicken:</small>
        <div class="mc-ph">@foreach ($box['placeholders'] as $ph)<button type="button" data-ph="[{{ $ph }}]">[{{ $ph }}]</button>@endforeach</div>
      @endif
    </div>
    @if ($box['upload'])
      <div class="sd-field">
        <label for="mc-file">Datei anhängen (optional)</label>
        <input id="mc-file" name="datei" type="file" accept=".pdf,.jpg,.jpeg,.png">
        <small class="sd-muted">PDF, JPG oder PNG, höchstens 10 MB.</small>
      </div>
    @endif
    @php($withPdf = collect($box['items'])->filter(fn ($i) => $i['pdf_name'])->count())
    @if ($withPdf > 0)
      <p class="sd-muted" style="margin:.2rem 0 0">Im Anhang: {{ $withPdf === 1 ? $box['items'][0]['pdf_name'] : 'je Empfänger das eigene Schreiben als PDF' }}</p>
    @endif
  </div>

  <div class="sd-actions">
    <button class="sd-btn" type="submit" @disabled(! $configured) id="mc-send">{{ count($box['items']) === 1 ? 'E-Mail senden' : 'E-Mails senden' }}</button>
    <a class="sd-btn sec" href="{{ $box['back'] }}">Abbrechen</a>
  </div>
</form>
<script>
(function () {
  var body = document.getElementById('mc-body');
  document.querySelectorAll('[data-ph]').forEach(function (b) {
    b.addEventListener('click', function () {
      var s = body.selectionStart || 0, e = body.selectionEnd || 0, t = b.getAttribute('data-ph');
      body.value = body.value.slice(0, s) + t + body.value.slice(e);
      body.focus(); body.selectionStart = body.selectionEnd = s + t.length;
    });
  });
  document.getElementById('mc-form').addEventListener('submit', function () {
    var btn = document.getElementById('mc-send');
    setTimeout(function () { btn.disabled = true; btn.textContent = 'Wird gesendet …'; }, 0);
  });
})();
</script>
@endsection
