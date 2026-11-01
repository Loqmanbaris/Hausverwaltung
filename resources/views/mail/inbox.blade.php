@extends('layouts.app')
@section('title', 'Posteingang')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.ib-list{list-style:none;margin:0;padding:0}
.ib-list li{border-top:1px solid var(--line,#e5e7eb)}
.ib-list li:first-child{border-top:0}
.ib-item{display:grid;grid-template-columns:1fr auto;gap:.15rem .8rem;padding:.7rem .3rem;text-decoration:none;color:inherit}
.ib-item:hover{background:#fafafa}
.ib-from{font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ib-date{color:var(--muted,#6b7280);font-size:.85rem;white-space:nowrap;text-align:right}
.ib-subj{grid-column:1/-1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ib-prev{grid-column:1/-1;color:var(--muted,#6b7280);font-size:.88rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.ib-item.new .ib-from,.ib-item.new .ib-subj{font-weight:800}
.ib-item.new .ib-from::before{content:"";display:inline-block;width:.5rem;height:.5rem;border-radius:50%;background:#1d4ed8;margin-right:.4rem;vertical-align:middle}
.ib-tag{display:inline-block;font-size:.72rem;font-weight:700;border-radius:1rem;padding:.02rem .45rem;margin-left:.35rem;background:#eef2ff;color:#3730a3;vertical-align:middle}
.ib-tag.re{background:#e7f6ec;color:#067647}
.ib-filter{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:0 0 1rem}
.ib-filter input,.ib-filter select{width:auto;max-width:100%;font:inherit;padding:.45rem .6rem;border:1px solid var(--line-strong);border-radius:8px;background:#fff}
</style>
<div class="sd-head">
  <h1>Posteingang</h1>
  <div class="sd-actions">
    @if ($receive)
      <form method="POST" action="{{ route('inbox.fetch') }}" style="margin:0">@csrf<button class="sd-btn sec" type="submit">Jetzt abrufen</button></form>
    @endif
    <a class="sd-btn" href="{{ route('mail.write') }}">Neue E-Mail</a>
  </div>
</div>
@include('mail.partials.tabs', ['unreadTab' => $unread])

@unless ($receive)
  <div class="sd-err">Der E-Mail-Empfang ist noch nicht eingerichtet.@if (auth()->user()->isAdmin()) <a href="{{ route('mail.settings') }}">Jetzt einrichten</a> (gleiche Zugangsdaten wie für den Versand).@else Bitte den Administrator fragen.@endif</div>
@endunless
@if ($receive && $lastError)
  <div class="sd-err">Beim letzten Abruf gab es einen Fehler: {{ $lastError }}</div>
@endif

<form class="ib-filter" method="GET" action="{{ route('inbox.index') }}">
  <select name="ansicht" aria-label="Ansicht" onchange="this.form.submit()">
    <option value="eingang" @selected($view === 'eingang')>Posteingang</option>
    <option value="ungelesen" @selected($view === 'ungelesen')>Nur ungelesene</option>
    <option value="archiv" @selected($view === 'archiv')>Archiv</option>
  </select>
  <input type="search" name="q" value="{{ $q }}" placeholder="Absender, Betreff oder Text" aria-label="Suche">
  <button class="sd-btn sec small" type="submit">Suchen</button>
</form>

<div class="sd-card">
  @if ($mails->isEmpty())
    <p class="sd-muted" style="margin:0">{{ $q !== '' ? 'Nichts gefunden.' : ($view === 'archiv' ? 'Das Archiv ist leer.' : 'Keine E-Mails.') }}</p>
  @else
    <ul class="ib-list">
      @foreach ($mails as $m)
        <li>
          <a class="ib-item {{ $m->read_at ? '' : 'new' }}" href="{{ route('inbox.show', $m->id) }}">
            <span class="ib-from">{{ $m->senderLabel() }}
              @if ($m->tenant)<span class="ib-tag">Mieter</span>@elseif ($m->vendor)<span class="ib-tag">Lieferant</span>@elseif ($m->owner)<span class="ib-tag">Eigentümer</span>@endif
              @if ($m->replied_at)<span class="ib-tag re">beantwortet</span>@endif
            </span>
            <span class="ib-date">{{ $m->received_at?->timezone('Europe/Berlin')->isToday() ? $m->received_at->timezone('Europe/Berlin')->format('H:i') : $m->received_at?->timezone('Europe/Berlin')->format('d.m.Y') }}@if ($m->attachments) 📎@endif</span>
            <span class="ib-subj">{{ $m->subject }}</span>
            <span class="ib-prev">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $m->body), 140) }}</span>
          </a>
        </li>
      @endforeach
    </ul>
    <div class="sd-actions" style="margin-top:.8rem">
      @if ($mails->previousPageUrl())<a class="sd-btn sec small" href="{{ $mails->previousPageUrl() }}">Zurück</a>@endif
      @if ($mails->nextPageUrl())<a class="sd-btn sec small" href="{{ $mails->nextPageUrl() }}">Weiter</a>@endif
    </div>
  @endif
</div>
@if ($receive && $lastFetch)
  <p class="sd-muted" style="font-size:.85rem">Zuletzt abgerufen: {{ \Illuminate\Support\Carbon::parse($lastFetch)->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr. Neue E-Mails kommen alle 5 Minuten von selbst.</p>
@endif
@endsection
