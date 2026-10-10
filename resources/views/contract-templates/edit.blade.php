@extends('layouts.app')
@section('title', 'Vorlage '.$label)
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('contracts.document-styles')
<style>
.tp-sec{border:1px solid #e3e6ea;border-radius:.6rem;padding:.7rem .9rem;margin:0 0 .8rem;background:#fff}
.tp-sec.off{opacity:.55}
.tp-top{display:flex;flex-wrap:wrap;gap:.4rem;align-items:center}
.tp-top input[type=text]{flex:1 1 16rem;min-width:0;padding:.45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;font-weight:600}
.tp-sec textarea{width:100%;box-sizing:border-box;min-height:9rem;margin-top:.5rem;padding:.5rem;border:1px solid #c5ccd3;border-radius:.4rem;font:13px/1.45 ui-monospace,Consolas,monospace}
.tp-ph{columns:2 18rem;font-size:.88rem;margin:0;padding-left:1rem}
.tp-ph code{background:#f1f3f5;padding:.05rem .25rem;border-radius:.2rem}
.tp-prev{background:#fff;border:1px solid #e3e6ea;border-radius:.6rem;padding:1rem 1.2rem;max-height:70vh;overflow:auto}
</style>
<div class="sd-head">
  <h1>Vorlage {{ $label }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('contract-templates.index') }}">Alle Vorlagen</a></div>
</div>
<p class="sd-muted">Stand: {{ $custom ? 'angepasst' : 'Muster' }}. Jeder Abschnitt wird ein Paragraf (§). Abschnitte ohne Haken oder ohne Text nach dem Einsetzen der Werte erscheinen nicht im Vertrag.</p>

<details class="sd-card">
  <summary><strong>So funktionieren Platzhalter</strong></summary>
  <ul style="margin:.6rem 0">
    <li>@verbatim<code>{{mieter_name}}</code>@endverbatim wird durch den Wert ersetzt. Fehlt er, steht im Vertrag eine Leerstelle zum Ausfüllen.</li>
    <li><code>[[wenn kaution]]…[[/wenn]]</code> erscheint nur, wenn es eine Kaution gibt; <code>[[wenn nicht befristet]]…[[/wenn]]</code> nur, wenn nicht.</li>
    <li><code>(#)</code> am Anfang eines Absatzes wird zur laufenden Nummer (1), (2) … Eine Leerzeile beginnt einen neuen Absatz.</li>
  </ul>
  <ul class="tp-ph">
    @foreach ($placeholders as $k => $d)<li><code>{{ $k }}</code> – {{ $d }}</li>@endforeach
  </ul>
</details>

<form method="POST" action="{{ route('contract-templates.update', $kind) }}" id="tp-form">
  @csrf @method('PUT')
  <div id="tp-list">
    @foreach (old('s', $sections) as $i => $s)
      <div class="tp-sec {{ ! empty($s['enabled']) ? '' : 'off' }}" id="p{{ $i }}">
        <input type="hidden" data-n="key" name="s[{{ $i }}][key]" value="{{ $s['key'] }}">
        <div class="tp-top">
          <label title="Im Vertrag verwenden"><input type="hidden" data-n="enabled0" name="s[{{ $i }}][enabled]" value="0"><input type="checkbox" data-n="enabled" name="s[{{ $i }}][enabled]" value="1" @checked(! empty($s['enabled'])) onchange="this.closest('.tp-sec').classList.toggle('off', !this.checked)"> aktiv</label>
          <input type="text" data-n="title" name="s[{{ $i }}][title]" value="{{ $s['title'] }}" maxlength="120" aria-label="Überschrift">
          <button type="button" class="sd-btn sec small" onclick="tpMove(this,-1)" title="Nach oben">↑</button>
          <button type="button" class="sd-btn sec small" onclick="tpMove(this,1)" title="Nach unten">↓</button>
          <button type="button" class="sd-btn sec small" onclick="if(confirm('Abschnitt entfernen?')){this.closest('.tp-sec').remove();tpRenumber();}">Entfernen</button>
        </div>
        <textarea data-n="text" name="s[{{ $i }}][text]" aria-label="Text">{{ $s['text'] }}</textarea>
      </div>
    @endforeach
  </div>
  <div class="sd-actions" style="justify-content:flex-start;margin-bottom:1rem">
    <button class="sd-btn" type="submit">Vorlage speichern</button>
    <button class="sd-btn sec" type="submit" name="add" value="1">Paragraf hinzufügen</button>
  </div>
</form>
@if ($custom)
  <form method="POST" action="{{ route('contract-templates.reset', $kind) }}" onsubmit="return confirm('Alle Änderungen verwerfen und das Muster wiederherstellen?')" style="margin-bottom:1rem">
    @csrf
    <button class="sd-btn sec small" type="submit">Auf Muster zurücksetzen</button>
  </form>
@endif

<div class="sd-card">
  <h2>Vorschau (gespeicherter Stand, Platzhalter als «Name»)</h2>
  <div class="tp-prev ct-doc">
    @foreach ($preview as $s)
      <section class="ct-sec"><h2>§ {{ $s['no'] }} {{ $s['title'] }}</h2>@foreach ($s['paragraphs'] as $p)<p>{!! nl2br(e($p)) !!}</p>@endforeach</section>
    @endforeach
  </div>
</div>

<script>
function tpMove(btn, d) {
  var el = btn.closest('.tp-sec'), list = el.parentNode;
  if (d < 0 && el.previousElementSibling) list.insertBefore(el, el.previousElementSibling);
  if (d > 0 && el.nextElementSibling) list.insertBefore(el.nextElementSibling, el);
  tpRenumber();
}
function tpRenumber() {
  document.querySelectorAll('#tp-list .tp-sec').forEach(function (sec, i) {
    sec.querySelectorAll('[data-n]').forEach(function (f) {
      var n = f.dataset.n === 'enabled0' ? 'enabled' : f.dataset.n;
      f.name = 's[' + i + '][' + n + ']';
    });
  });
}
</script>
@endsection
