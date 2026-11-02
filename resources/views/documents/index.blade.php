@extends('layouts.app')
@section('title', 'Dokumente')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('documents.styles')
<style>
.dk-filter{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:0 0 1rem}
.dk-filter input,.dk-filter select{width:auto;max-width:100%;font:inherit;padding:.45rem .6rem;border:1px solid var(--line-strong);border-radius:8px;background:var(--surface)}
.dk-chips{display:flex;flex-wrap:wrap;gap:.5rem;margin:0 0 1rem}
.dk-chip{display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--line-strong);border-radius:999px;padding:.35rem .85rem;text-decoration:none;color:var(--ink);font-weight:600;font-size:.9rem;background:var(--surface)}
.dk-chip.on{border-color:var(--ink);background:var(--primary);color:var(--on-primary)}
.dk-chip b{background:var(--danger-bg);color:var(--danger);border-radius:1rem;padding:0 .45rem;font-size:.8rem}
.dk-chip.on b{background:var(--surface)}
</style>
<div class="sd-head">
  <h1>Dokumente</h1>
</div>
<p class="sd-muted">Hier siehst du alle abgelegten Dokumente. Neue Dokumente legst du direkt beim Eigentümer, Objekt, bei der Einheit, beim Mieter oder beim Mietvertrag ab (Bereich „Dokumente“ unten auf der Seite). Gesendete Mahnungen, Abrechnungen, Verträge und Protokolle werden automatisch abgelegt.</p>

<div class="dk-chips">
  <a @class(['dk-chip', 'on' => $frist === '']) href="{{ route('documents.index') }}">Alle</a>
  <a @class(['dk-chip', 'on' => $frist === 'bald']) href="{{ route('documents.index', ['frist' => 'bald']) }}">Fristen in 90 Tagen @if ($counts['bald'] + $counts['abgelaufen'] > 0)<b>{{ $counts['bald'] + $counts['abgelaufen'] }}</b>@endif</a>
  <a @class(['dk-chip', 'on' => $frist === 'abgelaufen']) href="{{ route('documents.index', ['frist' => 'abgelaufen']) }}">Abgelaufen @if ($counts['abgelaufen'] > 0)<b>{{ $counts['abgelaufen'] }}</b>@endif</a>
</div>

<form class="dk-filter" method="GET" action="{{ route('documents.index') }}">
  @if ($frist)<input type="hidden" name="frist" value="{{ $frist }}">@endif
  @if ($owners->count() > 1)
    <select name="eigentuemer" aria-label="Eigentümer"><option value="">Alle Eigentümer</option>@foreach ($owners as $o)<option value="{{ $o->id }}" @selected($ownerId === (int) $o->id)>{{ $o->name }}</option>@endforeach</select>
  @endif
  <select name="kategorie" aria-label="Art"><option value="">Alle Arten</option>@foreach (\App\Support\Ablage\Ablage::CATEGORIES as $k => [$l])<option value="{{ $k }}" @selected($cat === $k)>{{ $l }}</option>@endforeach</select>
  <input type="search" name="q" value="{{ $q }}" placeholder="Bezeichnung oder Notiz" aria-label="Suche">
  <label style="display:flex;gap:.35rem;align-items:center;margin:0;font-weight:500"><input type="checkbox" name="archiv" value="1" @checked(request('archiv') === '1')> mit Archiv</label>
  <button class="sd-btn sec small" type="submit">Anzeigen</button>
</form>

<div class="sd-card">
  @if ($docs->isEmpty())
    <p class="sd-muted" style="margin:0">Keine Dokumente gefunden.</p>
  @else
    <table class="dk-list">
      @foreach ($docs as $d)
        @php($t = $targets[$d->id])
        @php($st = $d->archived ? null : $d->expiryState())
        <tr>
          <td>
            <div class="dk-name">
              <span @class(['dk-ico', 'img' => $d->isImage()])>{{ $d->isImage() ? 'BILD' : 'PDF' }}</span>
              <div style="min-width:0">
                <a href="{{ route('documents.file', $d) }}" target="_blank" rel="noopener">{{ $d->title }}</a>
                <div class="dk-sub">{{ \App\Support\Ablage\Ablage::TYPES[$d->attach_type][0] }}: @if ($t)<a href="{{ $t[3] }}">{{ $t[2] }}</a>@else – @endif · {{ $d->doc_date?->format('d.m.Y') ?? $d->created_at->format('d.m.Y') }}</div>
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
            @if ($st === 'abgelaufen' || $st === 'bald')
              <form method="POST" action="{{ route('documents.archive', $d) }}">@csrf<button class="sd-btn sec small" type="submit" title="Erledigt – erscheint nicht mehr bei den Fristen">Erledigt</button></form>
            @endif
            <a class="sd-btn sec small" href="{{ route('documents.edit', $d) }}">Ändern</a>
          </td>
        </tr>
      @endforeach
    </table>
    <div class="sd-actions" style="margin-top:.8rem">
      @if ($docs->previousPageUrl())<a class="sd-btn sec small" href="{{ $docs->previousPageUrl() }}">Zurück</a>@endif
      @if ($docs->nextPageUrl())<a class="sd-btn sec small" href="{{ $docs->nextPageUrl() }}">Weiter</a>@endif
    </div>
  @endif
</div>
@endsection
