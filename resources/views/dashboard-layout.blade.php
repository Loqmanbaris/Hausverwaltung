@extends('layouts.app')
@section('title', 'Übersicht anpassen')
@section('content')
@include('partials.sd-styles')
<style>
.dl-list{list-style:none;margin:0;padding:0}
.dl-item{display:flex;align-items:center;gap:.8rem;padding:.65rem .2rem;border-top:1px solid var(--line);background:#fff}
.dl-item:first-child{border-top:0}
.dl-item.off .dl-txt{opacity:.5}
.dl-txt{flex:1;min-width:0}
.dl-txt strong{display:block}
.dl-txt span{font-size:.88rem;color:var(--muted)}
.dl-size{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);border:1px solid var(--line);border-radius:1rem;padding:.05rem .5rem;white-space:nowrap}
.dl-move{display:flex;gap:.25rem}
.dl-move button{width:2.1rem;height:2.1rem;border:1px solid var(--line-strong);background:#fff;border-radius:8px;cursor:pointer;font-size:1rem;line-height:1}
.dl-move button:hover{border-color:var(--ink)}
.dl-move button:disabled{opacity:.3;cursor:default}
.dl-color{display:flex;align-items:center;gap:.4rem}
.dl-color select{font:inherit;font-size:.9rem;padding:.35rem .5rem;border:1px solid var(--line-strong);border-radius:8px;background:#fff;max-width:13rem}
.dl-dot{width:1.1rem;height:1.1rem;border-radius:50%;border:1px solid var(--line-strong);flex:none;background:#fff}
.dl-item{border-left:4px solid transparent;padding-left:.5rem}
.dl-switch{display:flex;align-items:center;gap:.4rem;margin:0;font-weight:600;font-size:.9rem;white-space:nowrap}
.dl-switch input{width:1.15rem;height:1.15rem;accent-color:var(--brand)}
.dl-quick{display:grid;grid-template-columns:repeat(auto-fill,minmax(14rem,1fr));gap:.4rem .9rem}
.dl-quick label{display:flex;gap:.5rem;align-items:center;font-weight:500;margin:0;padding:.3rem 0}
.dl-quick input{width:1.1rem;height:1.1rem;accent-color:var(--brand)}
.dl-actions{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center;margin-top:1rem}
@media (max-width:760px){.dl-size{display:none}.dl-item{gap:.5rem;flex-wrap:wrap}.dl-txt{flex-basis:calc(100% - 6rem)}.dl-color{margin-left:4.8rem}}
</style>

<div class="sd-head">
  <h1>Übersicht anpassen</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('dashboard') }}">Zurück zur Übersicht</a></div>
</div>
<p class="sd-muted">Wähle, welche Bereiche du auf deiner Übersicht sehen willst, gib jedem Bereich eine Farbe und bring sie mit den Pfeilen in deine Reihenfolge. Die Einstellung gilt nur für dich.</p>

<form method="POST" action="{{ route('dashboard.layout.update') }}" id="dl-form">
  @csrf
  @method('PUT')
  <div class="sd-card">
    <h2>Bereiche und Reihenfolge</h2>
    <ol class="dl-list" id="dl-list">
      @foreach ($blocks as $b)
        <li @class(['dl-item', 'off' => ! $b['on']])>
          <input type="hidden" name="order[]" value="{{ $b['key'] }}">
          <div class="dl-move">
            <button type="button" data-move="-1" aria-label="{{ $b['label'] }} nach oben">↑</button>
            <button type="button" data-move="1" aria-label="{{ $b['label'] }} nach unten">↓</button>
          </div>
          <div class="dl-txt"><strong>{{ $b['label'] }}</strong><span>{{ $b['desc'] }}</span></div>
          <span class="dl-size">{{ $b['wide'] ? 'ganze Breite' : 'halbe Breite' }}</span>
          <span class="dl-color">
            <i class="dl-dot" data-dot></i>
            <select name="color[{{ $b['key'] }}]" aria-label="Farbe für {{ $b['label'] }}">
              @foreach (\App\Support\Dashboard\Layout::colorChoices($b['key']) as $ck => [$cl, $cv])
                <option value="{{ $ck }}" data-c="{{ $cv }}" @selected($b['color'] === $ck)>{{ $cl }}</option>
              @endforeach
            </select>
          </span>
          <label class="dl-switch"><input type="checkbox" name="on[]" value="{{ $b['key'] }}" @checked($b['on'])> anzeigen</label>
        </li>
      @endforeach
    </ol>
    <p class="sd-muted" style="font-size:.88rem;margin:.6rem 0 0">Zwei Bereiche mit halber Breite stehen nebeneinander, auf dem Handy untereinander.</p>
  </div>

  <div class="sd-card" id="schnellzugriff">
    <h2>Knöpfe im Schnellzugriff</h2>
    <div class="dl-quick">
      @foreach ($quick as $q)
        <label><input type="checkbox" name="quick[]" value="{{ $q['key'] }}" @checked($q['on'])> {{ $q['label'] }}</label>
      @endforeach
    </div>
  </div>

  <div class="dl-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ route('dashboard') }}">Abbrechen</a>
  </div>
</form>

<form method="POST" action="{{ route('dashboard.layout.reset') }}" style="margin-top:1.5rem" onsubmit="return confirm('Übersicht wieder auf den Anfang setzen?')">
  @csrf
  <button class="link sd-muted" type="submit">Auf Standard zurücksetzen</button>
</form>

<script>
(function () {
  var list = document.getElementById('dl-list');
  function refresh() {
    var items = list.children;
    for (var i = 0; i < items.length; i++) {
      items[i].querySelector('[data-move="-1"]').disabled = i === 0;
      items[i].querySelector('[data-move="1"]').disabled = i === items.length - 1;
    }
  }
  list.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-move]');
    if (!b) return;
    var li = b.closest('li');
    if (b.dataset.move === '-1' && li.previousElementSibling) list.insertBefore(li, li.previousElementSibling);
    if (b.dataset.move === '1' && li.nextElementSibling) list.insertBefore(li.nextElementSibling, li);
    refresh();
    b.focus();
  });
  function paint(sel) {
    var li = sel.closest('li'), opt = sel.options[sel.selectedIndex], c = opt.getAttribute('data-c');
    var dot = li.querySelector('[data-dot]');
    if (sel.value === 'bunt') { dot.style.background = 'conic-gradient(#2563eb 0 25%,#059669 0 50%,#ea580c 0 75%,#dc2626 0)'; li.style.borderLeftColor = '#2563eb'; }
    else { dot.style.background = c || '#fff'; li.style.borderLeftColor = c || 'transparent'; }
  }
  list.addEventListener('change', function (e) {
    if (e.target.name === 'on[]') e.target.closest('li').classList.toggle('off', !e.target.checked);
    if (e.target.tagName === 'SELECT') paint(e.target);
  });
  list.querySelectorAll('select').forEach(paint);
  refresh();
})();
</script>
@endsection
