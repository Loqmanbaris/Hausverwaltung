@extends('layouts.app')
@section('title', 'Gesendete E-Mails')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.ml-st{font-size:.75rem;font-weight:700;border-radius:1rem;padding:.08rem .5rem;white-space:nowrap}
.ml-st.ok{background:#e7f6ec;color:#067647}
.ml-st.err{background:#fde8e7;color:#b42318}
.ml-body{white-space:pre-line;font-size:.9rem;background:#fafafa;border:1px solid var(--line);border-radius:8px;padding:.7rem .8rem;margin:.4rem 0 0}
.ml-filter{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center;margin:0 0 1rem}
.ml-filter input,.ml-filter select{width:auto;max-width:100%;font:inherit;padding:.45rem .6rem;border:1px solid var(--line-strong);border-radius:8px;background:#fff}
</style>
<div class="sd-head">
  <h1>Gesendete E-Mails</h1>
  @if (auth()->user()->isAdmin())<div class="sd-actions"><a class="sd-btn sec" href="{{ route('mail.settings') }}">E-Mail einrichten</a></div>@endif
</div>
@unless ($configured)
  <div class="sd-err">Der E-Mail-Versand ist noch nicht eingerichtet.@if (auth()->user()->isAdmin()) <a href="{{ route('mail.settings') }}">Jetzt einrichten</a>@endif</div>
@endunless
<p class="sd-muted">E-Mails verschickst du direkt bei der Mahnung, der Nebenkostenabrechnung, dem Mietvertrag, dem Übergabeprotokoll, der Ratenvereinbarung, der Anfrage oder als Rundschreiben beim Objekt. Hier siehst du alles, was gesendet wurde.</p>

<form class="ml-filter" method="GET" action="{{ route('mail.index') }}">
  <select name="art" aria-label="Art">
    <option value="">Alle Arten</option>
    @foreach (\App\Models\SentEmail::KINDS as $k => $l)<option value="{{ $k }}" @selected($kind === $k)>{{ $l }}</option>@endforeach
  </select>
  <input type="search" name="q" value="{{ $q }}" placeholder="Empfänger oder Betreff" aria-label="Suche">
  <label style="display:flex;gap:.35rem;align-items:center;margin:0;font-weight:500"><input type="checkbox" name="status" value="fehler" @checked(request('status') === 'fehler')> nur Fehler</label>
  <button class="sd-btn sec small" type="submit">Anzeigen</button>
</form>

<div class="sd-card">
  @if ($mails->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine E-Mails.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Datum</th><th>Art</th><th>An</th><th>Betreff</th><th>Status</th></tr></thead>
        <tbody>
          @foreach ($mails as $m)
            <tr>
              <td style="white-space:nowrap">{{ $m->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}<div class="sd-muted" style="font-size:.82rem">{{ $m->user?->name }}</div></td>
              <td>{{ $m->kindLabel() }}</td>
              <td>{{ $m->recipients ?: '–' }}@if ($m->tenant)<div class="sd-muted" style="font-size:.82rem"><a href="{{ route('tenants.show', $m->tenant_id) }}">{{ $m->tenant->display_name }}</a></div>@endif</td>
              <td>
                <details><summary>{{ $m->subject }}</summary>
                  <div class="ml-body">{{ $m->body }}</div>
                  @if ($m->attachments)<div class="sd-muted" style="font-size:.85rem;margin-top:.3rem">Anhang: {{ implode(', ', $m->attachments) }}</div>@endif
                </details>
              </td>
              <td>@if ($m->status === 'gesendet')<span class="ml-st ok">gesendet</span>@else<span class="ml-st err">Fehler</span><div style="font-size:.82rem;color:#b42318;max-width:22rem">{{ $m->error }}</div>@endif</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <div class="sd-actions" style="margin-top:.8rem">
      @if ($mails->previousPageUrl())<a class="sd-btn sec small" href="{{ $mails->previousPageUrl() }}">Zurück</a>@endif
      @if ($mails->nextPageUrl())<a class="sd-btn sec small" href="{{ $mails->nextPageUrl() }}">Weiter</a>@endif
    </div>
  @endif
</div>
@endsection
