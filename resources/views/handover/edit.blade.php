@extends('layouts.app')
@section('title', $p->title())
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php
  use App\Support\Handover\HandoverService;
  $states = HandoverService::STATES;
  $lease = $p->lease;
  $isOut = $p->kind === 'auszug';
@endphp
<style>
.ho-room{border:1px solid var(--line);border-radius:.7rem;background:var(--surface);padding:.8rem 1rem;margin:0 0 1rem}
.ho-room-head{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-bottom:.4rem}
.ho-room-head input{flex:1 1 12rem;min-width:0;font-weight:700;font-size:1.05rem;padding:.4rem .5rem;border:1px solid var(--line-strong);border-radius:.4rem}
.ho-item{display:grid;grid-template-columns:minmax(9rem,1.1fr) auto minmax(8rem,1fr);gap:.4rem .6rem;align-items:center;padding:.45rem 0;border-top:1px solid #f1f3f5}
@media (max-width:1100px){.ho-item{grid-template-columns:1fr;gap:.35rem}}
.ho-item input[type=text]{width:100%;padding:.4rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit}
.ho-item .nm{border-color:transparent!important;background:transparent;font-weight:600;padding-left:0!important}
.ho-seg{display:flex;flex-wrap:wrap;gap:.25rem}
.ho-seg label{position:relative}
.ho-seg input{position:absolute;opacity:0;width:1px;height:1px}
.ho-seg span{display:inline-block;padding:.4rem .6rem;border:1px solid var(--line-strong);border-radius:.4rem;font-size:.9rem;cursor:pointer;background:var(--surface);white-space:nowrap}
.ho-seg input:checked+span.s-gut{background:var(--ok-bg);border-color:#12b76a;color:var(--ok);font-weight:600}
.ho-seg input:checked+span.s-gebrauch{background:var(--brass-bg);border-color:#f79009;color:#93370d;font-weight:600}
.ho-seg input:checked+span.s-mangel{background:var(--danger-bg);border-color:#f04438;color:var(--danger);font-weight:600}
.ho-seg input:checked+span.s-na{background:var(--surface-2);border-color:#98a2b3;color:#344054;font-weight:600}
.ho-seg input:focus-visible+span{outline:3px solid #2f6fd0}
.ho-prev{grid-column:1/-1;font-size:.85rem;color:var(--muted);margin-top:-.2rem}
.ho-prev.new{color:var(--danger);font-weight:600}
.ho-photos{display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0}
.ho-photos figure{margin:0;position:relative}
.ho-photos img{width:110px;height:82px;object-fit:cover;border-radius:.35rem;display:block;background:var(--surface-2)}
.ho-photos button{position:absolute;top:2px;right:2px;border:0;background:rgba(0,0,0,.6);color:#fff;border-radius:.3rem;font-size:.75rem;padding:.1rem .35rem;cursor:pointer}
.ho-cam{display:inline-flex;align-items:center;gap:.4rem;padding:.45rem .8rem;border:1px dashed #98a2b3;border-radius:.5rem;cursor:pointer;color:var(--ink);background:var(--surface-2)}
.ho-cam input{display:none}
.ho-keys{display:grid;grid-template-columns:1fr 6rem;gap:.4rem .6rem;max-width:30rem}
.ho-keys input{width:100%;padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit}
.ho-meter{display:grid;grid-template-columns:1fr 10rem;gap:.4rem .8rem;align-items:center;padding:.35rem 0;border-top:1px solid #f1f3f5}
.ho-meter input{width:100%;padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;text-align:right}
.ho-bar{position:sticky;bottom:0;background:var(--surface);border-top:1px solid var(--line);padding:.6rem 0;display:flex;flex-wrap:wrap;gap:.5rem;z-index:5}
.ho-date{padding:.5rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit}
</style>

<div class="sd-head">
  <h1>{{ $p->title() }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('leases.show', $p->lease_id) }}#uebergabe">Zum Mietvertrag</a>
    <a class="sd-btn sec" href="{{ route('handover.print', $p) }}" target="_blank" rel="noopener">Vorschau drucken</a>
  </div>
</div>
<p class="sd-muted">{{ $lease?->unit?->label }}, {{ $lease?->unit?->property?->addressLine() }} · {{ $lease?->tenant?->display_name }}
  @if ($p->compare)· Vergleich mit dem Einzug vom {{ $p->compare->handover_on->format('d.m.Y') }}@endif</p>

<form method="POST" action="{{ route('handover.update', $p) }}" id="ho-form">
  @csrf @method('PUT')

  <div class="sd-card">
    <h2>Allgemein</h2>
    <div class="sd-grid">
      <div class="sd-field"><label for="ho-date">Datum der Übergabe *</label><input class="ho-date" id="ho-date" type="date" name="handover_on" required value="{{ old('handover_on', $p->handover_on->format('Y-m-d')) }}"></div>
      @include('partials.sd-field', ['name' => 'attendees', 'label' => 'Anwesend', 'value' => $p->attendees, 'hint' => 'z. B. Hans Müller (Mieter), Loqman Baris (Vermieter)'])
      @if ($isOut)
        @include('partials.sd-field', ['name' => 'new_address', 'label' => 'Neue Anschrift des Mieters', 'value' => $p->new_address, 'hint' => 'Für Kaution und Nebenkostenabrechnung'])
      @endif
    </div>
  </div>

  <div class="sd-card">
    <h2>Zählerstände</h2>
    @if ($p->readings->isEmpty())
      <p class="sd-muted" style="margin:0">Für diese Wohnung sind keine Zähler angelegt. Zähler legst du beim Objekt unter „Zähler“ an.</p>
    @else
      <p class="sd-muted" style="margin-top:0">Werden beim Abschließen mit dem Datum der Übergabe in die Zählerstände übernommen.</p>
      @foreach ($p->readings as $r)
        @php($last = $r->meter?->readings()->where('read_on', '<=', $p->handover_on->format('Y-m-d'))->orderByDesc('read_on')->first())
        <div class="ho-meter">
          <label for="rd-{{ $r->id }}">{{ $r->meter?->title() }}<br><span class="sd-muted" style="font-size:.85rem">@if ($last)zuletzt {{ str_replace('.', ',', rtrim(rtrim((string) $last->value, '0'), '.')) }} am {{ $last->read_on->format('d.m.Y') }}@else noch kein Stand @endif · {{ \App\Models\Meter::UNITS[$r->meter?->kind] ?? '' }}</span></label>
          <input id="rd-{{ $r->id }}" type="text" inputmode="decimal" name="readings[{{ $r->id }}]" value="{{ old('readings.'.$r->id, $r->value !== null ? str_replace('.', ',', rtrim(rtrim((string) $r->value, '0'), '.')) : '') }}">
        </div>
      @endforeach
    @endif
  </div>

  <div class="sd-card" id="schluessel">
    <h2>Schlüssel</h2>
    <div class="ho-keys">
      <strong>Art</strong><strong>Anzahl</strong>
      @foreach ($p->keys ?? [] as $k => $key)
        <input type="text" name="keys[{{ $k }}][label]" value="{{ $key['label'] }}" aria-label="Schlüssel">
        <input type="text" inputmode="numeric" name="keys[{{ $k }}][count]" value="{{ $key['count'] }}" aria-label="Anzahl">
      @endforeach
    </div>
    <button class="sd-btn sec small" type="submit" name="action" value="add_key" style="margin-top:.5rem">Weiteren Schlüssel</button>
  </div>

  <h2 style="margin:1.4rem 0 .6rem">Räume</h2>
  @foreach ($p->rooms ?? [] as $ri => $room)
    <div class="ho-room" id="raum{{ $ri }}">
      <div class="ho-room-head">
        <input type="text" name="rooms[{{ $ri }}][name]" value="{{ $room['name'] }}" aria-label="Raum">
        <button type="button" class="sd-btn sec small" onclick="if(confirm('Raum aus dem Protokoll entfernen?')){this.closest('.ho-room').remove();}">Raum entfernen</button>
      </div>
      @foreach ($room['items'] ?? [] as $ii => $item)
        @php($pv = $prev[mb_strtolower($room['name'])][mb_strtolower($item['name'])] ?? null)
        <div class="ho-item">
          <input class="nm" type="text" name="rooms[{{ $ri }}][items][{{ $ii }}][name]" value="{{ $item['name'] }}" aria-label="Prüfpunkt">
          <div class="ho-seg" role="radiogroup" aria-label="{{ $item['name'] }}">
            @foreach ($states as $sk => $sl)
              <label><input type="radio" name="rooms[{{ $ri }}][items][{{ $ii }}][state]" value="{{ $sk }}" @checked(($item['state'] ?? '') === $sk)><span class="s-{{ $sk }}">{{ $sl }}</span></label>
            @endforeach
          </div>
          <input type="text" name="rooms[{{ $ri }}][items][{{ $ii }}][note]" value="{{ $item['note'] ?? '' }}" placeholder="Bemerkung" aria-label="Bemerkung">
          @if ($pv)
            <div @class(['ho-prev', 'new' => HandoverService::isNewDefect($prev, $room['name'], $item['name'], $item['state'] ?? '')])>Beim Einzug: {{ $states[$pv['state']] ?? 'nicht erfasst' }}{{ $pv['note'] ? ' – '.$pv['note'] : '' }}{{ HandoverService::isNewDefect($prev, $room['name'], $item['name'], $item['state'] ?? '') ? ' → neuer Mangel' : '' }}</div>
          @endif
        </div>
      @endforeach
      <div class="sd-field" style="margin-top:.6rem"><label for="rn-{{ $ri }}">Bemerkung zum Raum</label><textarea id="rn-{{ $ri }}" name="rooms[{{ $ri }}][note]" rows="2">{{ $room['note'] ?? '' }}</textarea></div>
      <div class="ho-photos">
        @foreach ($photos->get($ri, collect()) as $ph)
          <figure>
            <a href="{{ route('handover.photos.show', [$p, $ph, 'l']) }}" target="_blank" rel="noopener"><img src="{{ route('handover.photos.show', [$p, $ph, 's']) }}" alt="Foto {{ $room['name'] }}" loading="lazy"></a>
            <button type="submit" form="pd-{{ $ph->id }}" onclick="return confirm('Foto löschen?') && hoSaveFirst(event, 'pd-{{ $ph->id }}')" aria-label="Foto löschen">✕</button>
          </figure>
        @endforeach
        <label class="ho-cam">📷 Foto aufnehmen oder auswählen<input type="file" name="fotos[]" form="pf-{{ $ri }}" accept="image/*" capture="environment" multiple onchange="hoUpload('pf-{{ $ri }}')"></label>
      </div>
    </div>
  @endforeach

  <div class="sd-card">
    <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
      <input type="text" name="new_room" placeholder="Name des Raums, z. B. Abstellraum" style="flex:1 1 14rem;min-width:0;padding:.5rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit">
      <button class="sd-btn sec" type="submit" name="action" value="add_room">Raum hinzufügen</button>
    </div>
  </div>

  <div class="sd-card">
    <h2>Allgemeine Bemerkungen</h2>
    <textarea name="note" rows="4" style="width:100%;box-sizing:border-box;padding:.5rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit" placeholder="z. B. vereinbarte Arbeiten, Fristen, Übernahme von Einrichtungsgegenständen">{{ old('note', $p->note) }}</textarea>
  </div>

  <div class="ho-bar">
    <button class="sd-btn" type="submit" name="action" value="save">Speichern</button>
    <button class="sd-btn" type="submit" name="action" value="sign">Speichern und abschließen …</button>
  </div>
</form>

@foreach ($p->rooms ?? [] as $ri => $room)
  <form id="pf-{{ $ri }}" method="POST" action="{{ route('handover.photos.store', $p) }}" enctype="multipart/form-data" hidden>@csrf<input type="hidden" name="room" value="{{ $ri }}"></form>
@endforeach
@foreach ($p->photos as $ph)
  <form id="pd-{{ $ph->id }}" method="POST" action="{{ route('handover.photos.destroy', [$p, $ph]) }}" hidden>@csrf @method('DELETE')</form>
@endforeach

<form method="POST" action="{{ route('handover.destroy', $p) }}" onsubmit="return confirm('Entwurf mit allen Fotos löschen?')" style="margin-top:1.5rem">
  @csrf @method('DELETE')
  <button class="sd-btn sec small" type="submit">Entwurf löschen</button>
</form>

<script>
// Vor Foto-Aktionen erst die Eingaben speichern, damit nichts verloren geht
function hoSave() {
  var f = document.getElementById('ho-form'), d = new FormData(f);
  d.set('action', 'save');
  return fetch(f.action, { method: 'POST', body: d, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } });
}
function hoUpload(id) {
  document.body.style.cursor = 'wait';
  hoSave().finally(function () { document.getElementById(id).submit(); });
}
function hoSaveFirst(e, id) {
  e.preventDefault();
  hoSave().finally(function () { document.getElementById(id).submit(); });
  return false;
}
</script>
@endsection
