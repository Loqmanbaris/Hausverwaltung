{{-- Dokumente bei Eigentümer, Objekt, Einheit, Mieter oder Mietvertrag. Erwartet $docType und $docId. --}}
@php
  $docArchived = request()->query('archiv') === '1';
  try {
      $docs = \App\Support\Ablage\Ablage::listFor($docType, (int) $docId, auth()->user(), $docArchived);
  } catch (\Throwable) {
      $docs = null;
  }
  $docCats = \App\Support\Ablage\Ablage::categoriesFor($docType);
  $docTypeLabels = \App\Support\Ablage\Ablage::TYPES;
@endphp
@if ($docs !== null)
@include('documents.styles')
<div class="sd-card" id="dokumente">
  <h2>Dokumente{{ $docs->isNotEmpty() ? ' ('.$docs->where('archived', false)->count().')' : '' }}</h2>
  @if ($docs->isEmpty())
    <p class="sd-muted" style="margin:0 0 .6rem">Noch keine Dokumente abgelegt.</p>
  @else
    <table class="dk-list">
      @foreach ($docs as $d)
        @php($st = $d->archived ? null : $d->expiryState())
        <tr @if ($d->archived) style="opacity:.6" @endif>
          <td>
            <div class="dk-name">
              <span @class(['dk-ico', 'img' => $d->isImage()])>{{ $d->isImage() ? 'BILD' : 'PDF' }}</span>
              <div style="min-width:0">
                <a href="{{ route('documents.file', $d) }}" target="_blank" rel="noopener">{{ $d->title }}</a>
                <div class="dk-sub">
                  {{ $d->doc_date?->format('d.m.Y') ?? $d->created_at->format('d.m.Y') }} · {{ $d->sizeLabel() }}
                  @if ($d->attach_type !== $docType) · bei {{ $docTypeLabels[$d->attach_type][0] }}@endif
                  @if ($d->source === 'email') · per E-Mail versendet @endif
                </div>
                @if ($d->note)<div class="dk-sub" style="white-space:pre-line">{{ $d->note }}</div>@endif
              </div>
            </div>
          </td>
          <td class="dk-hide"><span class="dk-tag cat">{{ $d->categoryLabel() }}</span></td>
          <td>
            @if ($d->archived)<span class="dk-tag arch">archiviert</span>
            @elseif ($st === 'abgelaufen')<span class="dk-tag abgelaufen">abgelaufen {{ $d->expires_on->format('d.m.Y') }}</span>
            @elseif ($st === 'bald')<span class="dk-tag bald">läuft ab {{ $d->expires_on->format('d.m.Y') }}</span>
            @elseif ($st === 'ok')<span class="dk-tag ok">gültig bis {{ $d->expires_on->format('d.m.Y') }}</span>
            @endif
          </td>
          <td class="dk-acts">
            <a class="sd-btn sec small" href="{{ route('documents.file', ['document' => $d, 'download' => 1]) }}" title="Herunterladen">↓</a>
            <a class="sd-btn sec small" href="{{ route('documents.edit', $d) }}">Ändern</a>
          </td>
        </tr>
      @endforeach
    </table>
  @endif
  <p style="margin:.6rem 0 0;font-size:.88rem">
    @if ($docArchived)<a href="{{ url()->current() }}#dokumente">Archiv ausblenden</a>@else<a href="{{ url()->current() }}?archiv=1#dokumente">Archiv zeigen</a>@endif
  </p>

  <details class="dk-up" style="margin-top:.8rem" @if ($errors->has('files') || $errors->has('category')) open @endif>
    <summary>+ Dokument ablegen</summary>
    <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data">
      @csrf
      <input type="hidden" name="attach_type" value="{{ $docType }}">
      <input type="hidden" name="attach_id" value="{{ $docId }}">
      @error('files')<div class="sd-err" style="margin-top:.6rem">{{ $message }}</div>@enderror
      @error('category')<div class="sd-err" style="margin-top:.6rem">{{ $message }}</div>@enderror
      <div class="dk-grid">
        <div style="grid-column:1/-1">
          <label for="dk-files-{{ $docType }}">Datei(en) *</label>
          <input id="dk-files-{{ $docType }}" type="file" name="files[]" multiple required accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/*">
          <div class="dk-hint">PDF oder Foto, je Datei höchstens 10 MB. Word- und Excel-Dateien bitte vorher als PDF speichern.</div>
        </div>
        <div>
          <label for="dk-cat-{{ $docType }}">Art *</label>
          <select id="dk-cat-{{ $docType }}" name="category" required data-dk-cat>
            @foreach ($docCats as $k => [$label, $types, $exp, $hint])
              <option value="{{ $k }}" data-exp="{{ $exp ? 1 : 0 }}" data-hint="{{ $hint }}" @selected(old('category') === $k)>{{ $label }}</option>
            @endforeach
          </select>
          <div class="dk-hint" data-dk-hint></div>
        </div>
        <div><label for="dk-title-{{ $docType }}">Bezeichnung</label><input id="dk-title-{{ $docType }}" type="text" name="title" maxlength="190" value="{{ old('title') }}" placeholder="leer = Dateiname"></div>
        <div><label for="dk-date-{{ $docType }}">Datum des Dokuments</label><input id="dk-date-{{ $docType }}" type="date" name="doc_date" value="{{ old('doc_date') }}"></div>
        <div><label for="dk-exp-{{ $docType }}">Gültig bis / Frist</label><input id="dk-exp-{{ $docType }}" type="date" name="expires_on" value="{{ old('expires_on') }}"></div>
        <div><label for="dk-rem-{{ $docType }}">Erinnern … Tage vorher</label><input id="dk-rem-{{ $docType }}" type="number" name="remind_days" min="1" max="365" value="{{ old('remind_days', 30) }}"></div>
        <div style="grid-column:1/-1"><label for="dk-note-{{ $docType }}">Notiz</label><textarea id="dk-note-{{ $docType }}" name="note" rows="2" maxlength="2000">{{ old('note') }}</textarea></div>
      </div>
      <div style="margin-top:.7rem"><button class="sd-btn" type="submit">Ablegen</button></div>
    </form>
  </details>
</div>
<script>
document.querySelectorAll('[data-dk-cat]').forEach(function (sel) {
  var hint = sel.parentNode.querySelector('[data-dk-hint]');
  function upd() { var o = sel.options[sel.selectedIndex]; hint.textContent = o.getAttribute('data-hint') || ''; }
  sel.addEventListener('change', upd); upd();
});
</script>
@endif
