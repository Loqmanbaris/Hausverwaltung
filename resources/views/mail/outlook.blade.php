@extends('layouts.app')
@php
  use App\Support\Mail\MailView as MV;
  use App\Http\Controllers\InboxController as IC;
  $right = $open || $sent || $compose;   // am Handy: Lesebereich statt Liste
  $back = route('inbox.index', array_filter(['ordner' => $folder, 'q' => $q, 'seite' => $page > 1 ? $page : null]));
  $title = $compose ? 'Neue E-Mail' : ($open ? $open->subject : ($sent ? $sent->subject : IC::FOLDERS[$folder]));
@endphp
@section('title', $title)
@section('main_class', 'wide')
@section('content')
@include('partials.sd-flash')
<style>
.pf{--pf-acc:var(--brand);--pf-s2:#f6f7f9;display:grid;grid-template-columns:minmax(0,1fr);background:#fff;border:1px solid var(--line);border-radius:10px;box-shadow:0 1px 2px rgba(17,24,39,.04);overflow:hidden;min-height:calc(100dvh - 140px)}
.pf .ic{margin:0;width:18px;height:18px;fill:none;stroke:currentColor;stroke-width:1.8;stroke-linecap:round;stroke-linejoin:round;flex:none}
.pf a{text-decoration:none}
.pf-ordnerspalte{display:none;flex-direction:column;gap:6px;padding:14px 10px;background:var(--pf-s2);border-right:1px solid var(--line);min-height:0}
.pf-neu{display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:8px;padding:.6rem .9rem;border-radius:8px;background:var(--pf-acc);color:#fff!important;font-weight:600}
.pf-neu:hover{filter:brightness(.95)}
.pf-konto{font-size:12px;font-weight:600;color:var(--muted);padding:4px 8px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pf-ordnerliste{display:flex;flex-direction:column;gap:2px}
.pf-ordnerliste a,.pf-unten a,.pf-unten button{display:flex;align-items:center;gap:10px;padding:8px 10px;border-radius:6px;color:var(--ink);font-size:14.5px;background:none;border:0;font:inherit;cursor:pointer;width:100%;text-align:left}
.pf-ordnerliste a span{flex:1}
.pf-ordnerliste a b{font-size:13px;color:var(--pf-acc)}
.pf-ordnerliste a:hover,.pf-unten a:hover,.pf-unten button:hover{background:#fff}
.pf-ordnerliste a[aria-current="page"]{background:#fff;font-weight:600;box-shadow:inset 3px 0 0 var(--pf-acc)}
.pf-ordnerliste .ic,.pf-unten .ic{color:var(--pf-acc)}
.pf-unten{margin-top:auto;display:flex;flex-direction:column;gap:2px;font-size:13.5px}
.pf-unten a,.pf-unten button{color:var(--muted);font-size:13.5px}
.pf-unten form{margin:0}
.pf-stand{font-size:12px;color:var(--muted);padding:4px 10px}
.pf-listespalte{display:flex;flex-direction:column;min-width:0;min-height:0}
.pf-listenkopf{display:flex;flex-direction:column;gap:8px;padding:12px 12px 6px;border-bottom:1px solid var(--line)}
.pf-ordnerpillen{display:flex;gap:8px;align-items:center}
.pf-pillen{display:flex;flex-wrap:wrap;gap:6px;flex:1;min-width:0}
.pf-pillen a{padding:5px 11px;border-radius:999px;border:1px solid var(--line);color:var(--ink);font-size:13.5px;white-space:nowrap}
.pf-suchzeile{display:flex;gap:8px;align-items:center}
.pf-suchzeile .pf-suche{flex:1;min-width:0}
.pf-abruf{margin:0;display:flex}
.pf-pillen a[aria-current="page"]{background:var(--ink);border-color:var(--ink);color:#fff}
.pf-pillen a b{color:inherit;opacity:.85}
.pf-klein{display:inline-flex;align-items:center;justify-content:center;padding:8px 10px;border-radius:999px;border:0;background:var(--pf-acc);color:#fff!important;cursor:pointer}
.pf-klein.sec{background:var(--pf-s2);color:var(--ink)!important}
.pf-ordnerpillen form{margin:0;display:flex}
.pf-suche{display:flex;align-items:center;gap:8px;background:var(--pf-s2);border-radius:8px;padding:0 10px;color:var(--muted);margin:0}
.pf-suche input{flex:1;min-width:0;border:0!important;background:transparent!important;padding:9px 0!important;color:var(--ink);outline:none;box-shadow:none!important}
.pf-suche a{color:var(--muted);display:inline-flex}
.pf-ordnertitel{margin:4px 2px 0;font-size:17px}
.pf-liste{flex:1;min-height:0;overflow:auto}
.pf-gruppe{position:sticky;top:0;z-index:1;background:#fff;padding:10px 14px 4px;font-size:12.5px;font-weight:600;color:var(--muted)}
.pf-eintrag{display:grid;grid-template-columns:38px minmax(0,1fr);column-gap:10px;padding:9px 12px 10px 11px;border-left:3px solid transparent;border-bottom:1px solid var(--line);color:var(--ink)}
.pf-eintrag:hover{background:var(--pf-s2)}
.pf-eintrag.aktiv{background:color-mix(in srgb,var(--pf-acc) 11%,#fff)}
.pf-eintrag.neu{border-left-color:var(--pf-acc)}
.pf-avatar{grid-row:1/4;width:36px;height:36px;border-radius:50%;background:var(--av,#0f6cbd);color:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:13.5px;font-weight:600;letter-spacing:.02em;flex:none;margin-top:2px}
.pf-avatar.gross{width:44px;height:44px;font-size:16px}
.pf-z1,.pf-z2{display:flex;gap:8px;align-items:baseline;min-width:0}
.pf-wer,.pf-betr{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pf-wer{font-size:15px}
.pf-betr{font-size:14px}
.pf-zeit{font-size:12.5px;color:var(--muted);white-space:nowrap}
.pf-zeichen{display:inline-flex;gap:4px;color:var(--muted);align-items:center}.pf-zeichen .ic{width:14px;height:14px}
.pf-marke{font-size:11px;font-weight:700;border-radius:999px;padding:0 .45rem;background:#eef2ff;color:#3730a3;white-space:nowrap}
.pf-marke.err{background:#fde8e7;color:#b42318}
.pf-vorschau{grid-column:2;font-size:13.5px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pf-eintrag.neu .pf-wer{font-weight:700}
.pf-eintrag.neu .pf-betr{font-weight:600;color:var(--pf-acc)}
.pf-eintrag.neu .pf-zeit{color:var(--pf-acc);font-weight:600}
.pf-leer{padding:24px 16px;color:var(--muted);margin:0}
.pf-blaettern{display:flex;gap:8px;justify-content:space-between;align-items:center;padding:10px 12px;font-size:13.5px;color:var(--muted)}
.pf-blaettern a{border:1px solid var(--line);border-radius:6px;padding:4px 10px;color:var(--ink)}
.pf-lesespalte{display:none;flex-direction:column;min-width:0;min-height:0}
.pf-leiste{display:flex;align-items:center;gap:2px;padding:8px 10px;border-bottom:1px solid var(--line);overflow-x:auto;scrollbar-width:none;background:#fff}
.pf-leiste form{display:contents}
.pf-platz{flex:1}
.pf-knopf{display:inline-flex;align-items:center;gap:7px;padding:7px 10px;border-radius:6px;border:0;background:transparent;color:var(--ink)!important;font:inherit;font-size:14px;cursor:pointer;white-space:nowrap}
.pf-knopf:hover{background:var(--pf-s2)}
.pf-knopf .ic{color:var(--pf-acc)}
.pf-gefahr .ic{color:#b42318}
.pf-senden{display:inline-flex;align-items:center;gap:8px;margin-right:6px;padding:.5rem .95rem;border-radius:8px;border:0;background:var(--pf-acc);color:#fff;font:inherit;font-weight:600;cursor:pointer}
.pf-senden:disabled{opacity:.6;cursor:default}
.pf-datei{position:absolute;width:1px;height:1px;opacity:0;overflow:hidden}
.pf-inhalt{flex:1;min-height:0;overflow:auto;padding:18px 22px 28px;display:flex;flex-direction:column;gap:14px}
.pf-betreff{margin:0;font-size:21px;line-height:1.3;font-weight:600;overflow-wrap:anywhere}
.pf-absender{display:flex;gap:12px;align-items:flex-start}
.pf-absender .pf-avatar{grid-row:auto;margin:0}
.pf-absender-text{flex:1;min-width:0}
.pf-absender-name{overflow-wrap:anywhere}
.pf-empf{font-size:13.5px;margin-top:2px;overflow-wrap:anywhere;color:var(--muted)}
.pf-datum{font-size:13px;white-space:nowrap;color:var(--muted)}
.pf-bezug{display:flex;flex-wrap:wrap;gap:6px}
.pf-kunde{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;background:#e7f6ec;color:#067647!important;font-size:13.5px;font-weight:600}
.pf-kunde .ic{width:15px;height:15px}
.pf-anhaenge{display:flex;flex-wrap:wrap;gap:8px}
.pf-anhaenge:empty{display:none}
.pf-anhang{display:flex;align-items:center;gap:10px;padding:8px 12px;border:1px solid var(--line);border-radius:8px;color:var(--ink);max-width:280px;background:#fff;font-size:13.5px}
.pf-anhang:hover{border-color:var(--muted)}
.pf-anhang>span{display:flex;flex-direction:column;min-width:0}
.pf-anhang-name{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-weight:500}
.pf-anhang .ic{width:24px;height:24px;color:var(--pf-acc)}
.pf-anhaenge-neu{padding:8px 14px 0}
.pf-hinweis{background:var(--pf-s2);border-radius:6px;padding:8px 12px;font-size:13.5px}
.pf-hinweis.err{background:#fde8e7;color:#b42318}
.pf-html{width:100%;min-height:200px;height:420px;border:0;border-radius:6px;background:#fff;display:block}
.pf-text{white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.55;font-size:15px}
.pf-text blockquote{margin:6px 0 6px 2px;padding-left:12px;border-left:2px solid var(--line);color:var(--muted);white-space:pre-wrap}
.pf-trenner{border:0;border-top:1px solid var(--line);margin:10px 0 4px}
.pf-leerbereich{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;color:var(--muted);padding:40px 16px;text-align:center}
.pf-leerbereich .ic{width:56px;height:56px;stroke-width:1.2;opacity:.5}
.pf-schreibform{flex:1;display:flex;flex-direction:column;min-height:0;margin:0}
.pf-feld{display:flex;align-items:center;gap:10px;border-bottom:1px solid var(--line);padding:0 14px}
.pf-feld label{width:30px;color:var(--muted);font-size:14px;margin:0;font-weight:400}
.pf-feld input{flex:1;min-width:0;border:0!important;background:transparent!important;padding:11px 0!important;color:var(--ink);font-size:15px;outline:none;box-shadow:none!important;border-radius:0!important}
.pf-feld-betreff input{font-size:16px;font-weight:600}
.pf-feld-haken{padding:9px 14px;font-size:14px;gap:8px}
.pf-feld-haken input{flex:none;width:auto}
.pf-texteingabe{flex:1;min-height:340px;border:0!important;background:transparent!important;padding:14px!important;font:inherit;font-size:15px;line-height:1.55;color:var(--ink);resize:vertical;outline:none;box-shadow:none!important;border-radius:0!important}
.pf-absenderzeile{padding:8px 14px;border-top:1px solid var(--line);font-size:12.5px;color:var(--muted)}
.pf.pf-rechts .pf-listespalte{display:none}
.pf.pf-rechts .pf-lesespalte{display:flex}
@media (max-width:760px){
  .pf{margin:-1rem -1.25rem 0;border-radius:0;border-inline:0;min-height:calc(100dvh - 60px)}
  .pf-knopf span{display:none}.pf-knopf{padding:9px 11px}
  .pf-inhalt{padding:14px 14px 24px}
  .pf-absender{flex-wrap:wrap}.pf-datum{width:100%;padding-left:56px}
}
/* breit: Liste und Lesebereich nebeneinander */
@media (min-width:1100px){
  .pf{grid-template-columns:minmax(320px,370px) minmax(0,1fr);height:calc(100dvh - 140px);min-height:520px}
  .pf .pf-listespalte,.pf.pf-rechts .pf-listespalte{display:flex;border-right:1px solid var(--line)}
  .pf .pf-lesespalte{display:flex}
  .pf-nurklein{display:none!important}
}
/* sehr breit: zusätzlich die Ordnerspalte */
@media (min-width:1440px){
  .pf{grid-template-columns:210px minmax(310px,390px) minmax(0,1fr)}
  .pf-ordnerspalte{display:flex}
  .pf-ordnerpillen{display:none}
  .pf-abruf{display:none}
}
@media (max-width:1599px){.pf-kurz span{display:none}}
</style>

@if ($receive && $lastError)
  <div class="sd-err" style="margin-bottom:.8rem">Beim letzten Abruf gab es einen Fehler: {{ $lastError }}@if (auth()->user()->isAdmin()) · <a href="{{ route('mail.settings') }}">E-Mail einrichten</a>@endif</div>
@endif

<div class="pf{{ $right ? ' pf-rechts' : '' }}">
  {{-- Ordner --}}
  <aside class="pf-ordnerspalte" aria-label="Ordner">
    <a class="pf-neu" href="{{ route('inbox.index', ['schreiben' => 1]) }}">{!! MV::icon('pen') !!}Neue E-Mail</a>
    <div class="pf-konto">{{ $account ?: 'Postfach' }}</div>
    <nav class="pf-ordnerliste">
      @foreach (IC::FOLDERS as $k => $n)
        <a href="{{ route('inbox.index', ['ordner' => $k]) }}" @if ($k === $folder) aria-current="page" @endif>{!! MV::icon(IC::FOLDER_ICONS[$k]) !!}<span>{{ $n }}</span>@if ($k === 'eingang' && $unread)<b>{{ $unread }}</b>@endif</a>
      @endforeach
    </nav>
    <div class="pf-unten">
      @if ($receive)
        <form method="POST" action="{{ route('inbox.fetch') }}">@csrf<button type="submit">{!! MV::icon('refresh') !!}Jetzt abrufen</button></form>
        @if ($lastFetch)<div class="pf-stand">Abgerufen {{ \Illuminate\Support\Carbon::parse($lastFetch)->timezone('Europe/Berlin')->format('d.m. H:i') }} · alle 5 Min.</div>@endif
      @endif
      @if (auth()->user()->isAdmin())<a href="{{ route('mail.settings') }}">{!! MV::icon('gear') !!}Postfach und Signatur</a>@endif
    </div>
  </aside>

  {{-- Liste --}}
  <section class="pf-listespalte" aria-label="{{ IC::FOLDERS[$folder] }}">
    <div class="pf-listenkopf">
      <div class="pf-ordnerpillen">
        <span class="pf-pillen">@foreach (IC::FOLDERS as $k => $n)<a href="{{ route('inbox.index', ['ordner' => $k]) }}" @if ($k === $folder) aria-current="page" @endif>{{ $n }}@if ($k === 'eingang' && $unread) <b>{{ $unread }}</b>@endif</a>@endforeach</span>
        <a class="pf-klein" href="{{ route('inbox.index', ['schreiben' => 1]) }}" title="Neue E-Mail" aria-label="Neue E-Mail">{!! MV::icon('pen') !!}</a>
      </div>
      <div class="pf-suchzeile">
      <form class="pf-suche" method="GET" action="{{ route('inbox.index') }}" role="search">
        <input type="hidden" name="ordner" value="{{ $folder }}">
        {!! MV::icon('search') !!}<input name="q" type="search" value="{{ $q }}" placeholder="In „{{ IC::FOLDERS[$folder] }}“ suchen" aria-label="E-Mails suchen">
        @if ($q !== '')<a href="{{ route('inbox.index', ['ordner' => $folder]) }}" title="Suche aufheben" aria-label="Suche aufheben">{!! MV::icon('x') !!}</a>@endif
      </form>
      @if ($receive)<form method="POST" action="{{ route('inbox.fetch') }}" class="pf-abruf">@csrf<button class="pf-klein sec" type="submit" title="Jetzt abrufen" aria-label="Jetzt abrufen">{!! MV::icon('refresh') !!}</button></form>@endif
      </div>
      <h2 class="pf-ordnertitel">{{ $q !== '' ? 'Suchergebnisse' : IC::FOLDERS[$folder] }}</h2>
    </div>
    <div class="pf-liste">
      @if (! $receive && $folder !== 'gesendet')
        <p class="pf-leer">Der E-Mail-Empfang ist noch nicht eingerichtet.@if (auth()->user()->isAdmin()) <a href="{{ route('mail.settings') }}" style="text-decoration:underline">Jetzt einrichten</a>@else Bitte den Administrator fragen.@endif</p>
      @elseif ($list->isEmpty())
        <p class="pf-leer">{{ $q !== '' ? 'Keine E-Mails gefunden.' : 'Hier ist nichts.' }}</p>
      @endif
      @php($group = '')
      @foreach ($list as $m)
        @if ($folder === 'gesendet')
          @php($when = $m->created_at)
          @php($first = \App\Support\Mail\Imap\MimeParser::firstAddress(explode(' · Cc:', (string) $m->recipients)[0]))
          @php($active = $sent && $sent->id === $m->id)
        @else
          @php($when = $m->received_at ?? $m->created_at)
          @php($active = $open && $open->id === $m->id)
        @endif
        @php($g = MV::group($when))
        @if ($g !== $group)@php($group = $g)<div class="pf-gruppe">{{ $g }}</div>@endif
        @if ($folder === 'gesendet')
          <a class="pf-eintrag{{ $active ? ' aktiv' : '' }}" href="{{ route('inbox.index', array_filter(['ordner' => 'gesendet', 'id' => $m->id, 'q' => $q, 'seite' => $page > 1 ? $page : null])) }}" @if ($active) aria-current="true" @endif>
            {!! MV::avatar('', $first[0] ?: '?') !!}
            <span class="pf-z1"><span class="pf-wer">An: {{ $m->recipients ?: '–' }}</span><span class="pf-zeit">{{ MV::shortTime($when) }}</span></span>
            <span class="pf-z2"><span class="pf-betr">{{ $m->subject }}</span><span class="pf-zeichen">@if ($m->status !== 'gesendet')<span class="pf-marke err">Fehler</span>@elseif ($m->kind !== 'frei' && $m->kind !== 'antwort')<span class="pf-marke">{{ $m->kindLabel() }}</span>@endif @if ($m->attachments){!! MV::icon('clip') !!}@endif</span></span>
            <span class="pf-vorschau">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', (string) $m->body), 140) }}</span>
          </a>
        @else
          @php($files = array_values(array_filter($m->attachments ?? [], fn ($a) => empty($a['inline']))))
          <a class="pf-eintrag{{ $m->read_at ? '' : ' neu' }}{{ $active ? ' aktiv' : '' }}" href="{{ route('inbox.index', array_filter(['ordner' => $folder, 'id' => $m->id, 'q' => $q, 'seite' => $page > 1 ? $page : null])) }}" @if ($active) aria-current="true" @endif>
            {!! MV::avatar((string) $m->from_name, $m->from_address) !!}
            <span class="pf-z1"><span class="pf-wer">{{ $m->senderLabel() }}</span><span class="pf-zeit">{{ MV::shortTime($when) }}</span></span>
            <span class="pf-z2"><span class="pf-betr">{{ $m->subject }}</span><span class="pf-zeichen">@if ($m->tenant_id)<span class="pf-marke">Mieter</span>@elseif ($m->vendor_id)<span class="pf-marke">Lieferant</span>@endif @if ($m->replied_at)<span title="Beantwortet">{!! MV::icon('reply') !!}</span>@endif @if ($files)<span title="Mit Anhang">{!! MV::icon('clip') !!}</span>@endif</span></span>
            <span class="pf-vorschau">{{ \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', (string) $m->body), 140) }}</span>
          </a>
        @endif
      @endforeach
      @if ($pages > 1)
        <div class="pf-blaettern">
          @if ($page > 1)<a href="{{ route('inbox.index', array_filter(['ordner' => $folder, 'q' => $q, 'seite' => $page - 1])) }}">‹ Neuer</a>@else<span></span>@endif
          <span>Seite {{ $page }} von {{ $pages }}</span>
          @if ($page < $pages)<a href="{{ route('inbox.index', array_filter(['ordner' => $folder, 'q' => $q, 'seite' => $page + 1])) }}">Älter ›</a>@else<span></span>@endif
        </div>
      @endif
    </div>
  </section>

  {{-- Lesebereich --}}
  <section class="pf-lesespalte" aria-label="Lesebereich">
  @if ($compose)
    @php($c = $compose)
    @php($isReply = in_array($c['mode'], ['antworten', 'allen'], true))
    @php($cancel = $c['ref'] ? route('inbox.index', ['ordner' => $c['ref']->archived ? 'archiv' : 'eingang', 'id' => $c['ref']->id]) : $back)
    <form class="pf-schreibform" method="POST" action="{{ $isReply ? route('inbox.reply', $c['ref']->id) : route('mail.write.send') }}" enctype="multipart/form-data">
      @csrf
      @if ($c['mode'] === 'weiter')<input type="hidden" name="weiter" value="{{ $c['ref']->id }}">@endif
      <div class="pf-leiste">
        <button class="pf-senden" type="submit" @disabled(! $send)>{!! MV::icon('send') !!}<span>Senden</span></button>
        <label class="pf-knopf">{!! MV::icon('clip') !!}<span>Datei anfügen</span><input class="pf-datei" name="dateien[]" type="file" multiple data-dateien="pf-dateiliste"></label>
        @if (auth()->user()->isAdmin())<a class="pf-knopf" href="{{ route('mail.settings') }}#f-mail_signature">{!! MV::icon('sign') !!}<span>Signatur</span></a>@endif
        <span class="pf-platz"></span>
        <a class="pf-knopf" href="{{ $cancel }}" data-verwerfen>{!! MV::icon('trash') !!}<span>Verwerfen</span></a>
      </div>
      @unless ($send)<div class="pf-hinweis err" style="margin:10px 14px 0">Der E-Mail-Versand ist noch nicht eingerichtet.@if (auth()->user()->isAdmin()) <a href="{{ route('mail.settings') }}" style="text-decoration:underline">Jetzt einrichten</a>@endif</div>@endunless
      <div class="pf-feld"><label for="pf-an">An</label><input id="pf-an" name="to" type="text" inputmode="email" autocomplete="email" required value="{{ old('to', $c['to']) }}" @if (old('to', $c['to']) === '') autofocus @endif></div>
      <div class="pf-feld"><label for="pf-cc">Cc</label><input id="pf-cc" name="cc" type="text" inputmode="email" value="{{ old('cc', $c['cc']) }}"></div>
      <div class="pf-feld pf-feld-betreff"><input name="subject" type="text" required maxlength="200" value="{{ old('subject', $c['subject']) }}" placeholder="Betreff hinzufügen" aria-label="Betreff"></div>
      @if ($c['mode'] === 'weiter' && collect($c['ref']->attachments ?? [])->where('inline', '!=', true)->isNotEmpty())
        <label class="pf-feld pf-feld-haken"><input type="checkbox" name="mit_anhaengen" value="1" @checked(old('mit_anhaengen', '1') === '1')> Anhänge mitschicken ({{ collect($c['ref']->attachments)->where('inline', '!=', true)->pluck('name')->implode(', ') }})</label>
      @endif
      <div id="pf-dateiliste" class="pf-anhaenge pf-anhaenge-neu"></div>
      <textarea name="body" class="pf-texteingabe" aria-label="Nachricht" required @if (old('to', $c['to']) !== '') autofocus @endif>{{ old('body', $c['body']) }}</textarea>
      <div class="pf-absenderzeile">Von: {{ $fromName }} &lt;{{ $account }}&gt; · Eine Kopie geht an die Kopie-Adresse aus „E-Mail einrichten“.</div>
    </form>
  @elseif ($open)
    @php($files = array_values(array_filter(array_map(fn ($a, $i) => $a + ['i' => $i], $open->attachments ?? [], array_keys($open->attachments ?? [])), fn ($a) => empty($a['inline']))))
    @php($hasHtml = (string) $open->body_html !== '')
    @php($here = ['ordner' => $folder, 'id' => $open->id])
    <div class="pf-leiste">
      <a class="pf-knopf pf-nurklein" href="{{ $back }}" aria-label="Zurück zur Liste">{!! MV::icon('back') !!}</a>
      <a class="pf-knopf" href="{{ route('inbox.index', ['schreiben' => 1, 'wie' => 'antworten'] + $here) }}">{!! MV::icon('reply') !!}<span>Antworten</span></a>
      <a class="pf-knopf" href="{{ route('inbox.index', ['schreiben' => 1, 'wie' => 'allen'] + $here) }}">{!! MV::icon('replyall') !!}<span>Allen antworten</span></a>
      <a class="pf-knopf" href="{{ route('inbox.index', ['schreiben' => 1, 'wie' => 'weiter'] + $here) }}">{!! MV::icon('forward') !!}<span>Weiterleiten</span></a>
      <span class="pf-platz"></span>
      <form method="POST" action="{{ route('inbox.unread', $open->id) }}">@csrf<button class="pf-knopf pf-kurz" type="submit" title="Als ungelesen markieren">{!! MV::icon('mail') !!}<span>Ungelesen</span></button></form>
      <form method="POST" action="{{ route('inbox.archive', $open->id) }}">@csrf<button class="pf-knopf pf-kurz" type="submit" title="{{ $open->archived ? 'Zurück in den Posteingang' : 'Archivieren' }}">{!! MV::icon($open->archived ? 'inbox' : 'archive') !!}<span>{{ $open->archived ? 'Posteingang' : 'Archivieren' }}</span></button></form>
    </div>
    <article class="pf-inhalt">
      <h2 class="pf-betreff">{{ $open->subject }}</h2>
      <div class="pf-absender">
        {!! MV::avatar((string) $open->from_name, $open->from_address, 'gross') !!}
        <div class="pf-absender-text">
          <div class="pf-absender-name"><strong>{{ $open->from_name ?: $open->from_address }}</strong>@if ($open->from_name) <span class="muted">&lt;{{ $open->from_address }}&gt;</span>@endif</div>
          <div class="pf-empf">An: {{ $open->to_addresses ?: '–' }}@if ($open->cc) · Cc: {{ $open->cc }}@endif @if ($open->reply_to) · Antwort an: {{ $open->reply_to }}@endif</div>
        </div>
        <time class="pf-datum">{{ MV::longTime($open->received_at) }}</time>
      </div>
      @if ($open->tenant || $open->owner || $open->vendor || $open->applicant || $open->replied_at)
        <div class="pf-bezug">
          @if ($open->tenant && \Illuminate\Support\Facades\Route::has('tenants.show'))<a class="pf-kunde" href="{{ route('tenants.show', $open->tenant_id) }}">{!! MV::icon('user') !!}Mieter: {{ $open->tenant->display_name }}</a>@endif
          @if ($open->owner && \Illuminate\Support\Facades\Route::has('owners.show'))<a class="pf-kunde" href="{{ route('owners.show', $open->owner_id) }}">{!! MV::icon('user') !!}Eigentümer: {{ $open->owner->name }}</a>@endif
          @if ($open->vendor && \Illuminate\Support\Facades\Route::has('vendors.show'))<a class="pf-kunde" href="{{ route('vendors.show', $open->vendor_id) }}">{!! MV::icon('user') !!}Lieferant: {{ $open->vendor->name }}</a>@endif
          @if ($open->applicant && \Illuminate\Support\Facades\Route::has('applicants.show'))<a class="pf-kunde" href="{{ route('applicants.show', $open->applicant_id) }}">{!! MV::icon('user') !!}Interessent</a>@endif
          @if ($open->replied_at)<span class="pf-hinweis" style="padding:4px 10px">{!! MV::icon('reply') !!} Beantwortet am {{ $open->replied_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</span>@endif
        </div>
      @endif
      @if ($files)
        <div class="pf-anhaenge">
          @foreach ($files as $a)
            <a class="pf-anhang" href="{{ route('inbox.attachment', [$open->id, $a['i']]) }}" target="_blank" rel="noopener">{!! MV::icon(str_starts_with((string) $a['mime'], 'image/') ? 'image' : 'file') !!}<span><span class="pf-anhang-name">{{ $a['name'] }}</span><span class="muted">{{ MV::size((int) ($a['size'] ?? 0)) }}</span></span></a>
          @endforeach
        </div>
      @endif
      @if ($hasHtml && MV::hasExternalImages((string) $open->body_html) && ! $images)
        <div class="pf-hinweis">Bilder aus dem Internet wurden zum Schutz der Privatsphäre nicht geladen. <a href="{{ route('inbox.index', $here + ['bilder' => 1]) }}" style="text-decoration:underline">Bilder anzeigen</a></div>
      @endif
      @if ($hasHtml)
        <iframe class="pf-html" title="Inhalt der E-Mail" sandbox="allow-same-origin allow-popups allow-popups-to-escape-sandbox" data-mail-html src="{{ route('inbox.html', ['id' => $open->id] + ($images ? ['bilder' => 1] : [])) }}"></iframe>
      @else
        <div class="pf-text">{!! trim((string) $open->body) !== '' ? MV::textHtml((string) $open->body) : '<span class="muted">(kein Text)</span>' !!}</div>
      @endif
    </article>
  @elseif ($sent)
    <div class="pf-leiste">
      <a class="pf-knopf pf-nurklein" href="{{ $back }}" aria-label="Zurück zur Liste">{!! MV::icon('back') !!}</a>
      @php($firstTo = \App\Support\Mail\Imap\MimeParser::firstAddress(explode(' · Cc:', (string) $sent->recipients)[0])[0])
      @if ($firstTo !== '')<a class="pf-knopf" href="{{ route('inbox.index', ['schreiben' => 1, 'an' => $firstTo]) }}">{!! MV::icon('pen') !!}<span>Neue E-Mail an Empfänger</span></a>@endif
      <span class="pf-platz"></span>
    </div>
    <article class="pf-inhalt">
      <h2 class="pf-betreff">{{ $sent->subject }}</h2>
      <div class="pf-absender">
        {!! MV::avatar($fromName, (string) $account, 'gross') !!}
        <div class="pf-absender-text">
          <div class="pf-absender-name"><strong>{{ $fromName }}</strong> <span class="muted">&lt;{{ $account }}&gt;</span>@if ($sent->user) <span class="muted">· gesendet von {{ $sent->user->name }}</span>@endif</div>
          <div class="pf-empf">An: {{ $sent->recipients ?: '–' }}</div>
        </div>
        <time class="pf-datum">{{ MV::longTime($sent->created_at) }}</time>
      </div>
      <div class="pf-bezug">
        <span class="pf-marke" style="font-size:12.5px;padding:3px 10px">{{ $sent->kindLabel() }}</span>
        @if ($sent->tenant && \Illuminate\Support\Facades\Route::has('tenants.show'))<a class="pf-kunde" href="{{ route('tenants.show', $sent->tenant_id) }}">{!! MV::icon('user') !!}Mieter: {{ $sent->tenant->display_name }}</a>@endif
      </div>
      @if ($sent->status !== 'gesendet')<div class="pf-hinweis err">Nicht gesendet: {{ $sent->error }}</div>@endif
      @if ($sent->attachments)
        <div class="pf-anhaenge">@foreach ($sent->attachments as $a)<span class="pf-anhang">{!! MV::icon('file') !!}<span><span class="pf-anhang-name">{{ $a }}</span></span></span>@endforeach</div>
      @endif
      <div class="pf-text">{!! MV::textHtml((string) $sent->body) !!}</div>
    </article>
  @else
    <div class="pf-leerbereich">{!! MV::icon('mail') !!}<p>Wähle eine E-Mail aus, um sie zu lesen.</p></div>
  @endif
  </section>
</div>

<script>
(function () {
  document.querySelectorAll('iframe[data-mail-html]').forEach(function (f) {
    var fit = function () {
      try { var d = f.contentDocument; if (d && d.documentElement && d.body) f.style.height = Math.max(200, d.documentElement.scrollHeight, d.body.scrollHeight) + 8 + 'px'; } catch (e) {}
    };
    f.addEventListener('load', function () { fit(); setTimeout(fit, 400); setTimeout(fit, 1500); });
    fit();
    window.addEventListener('resize', fit);
  });
  document.querySelectorAll('input[data-dateien]').forEach(function (i) {
    var box = document.getElementById(i.getAttribute('data-dateien'));
    if (!box) return;
    i.addEventListener('change', function () {
      box.textContent = '';
      Array.prototype.forEach.call(i.files, function (d) {
        var s = document.createElement('span');
        s.className = 'pf-anhang';
        s.textContent = '📎 ' + d.name + ' · ' + (d.size >= 1048576 ? (d.size / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(d.size / 1024)) + ' KB');
        box.appendChild(s);
      });
    });
  });
  var form = document.querySelector('.pf-schreibform');
  if (form) {
    var changed = false, sending = false;
    form.addEventListener('input', function () { changed = true; });
    form.addEventListener('submit', function () { sending = true; var k = form.querySelector('.pf-senden'); if (k) setTimeout(function () { k.disabled = true; k.lastChild.textContent = 'Wird gesendet …'; }, 0); });
    var drop = form.querySelector('[data-verwerfen]');
    if (drop) drop.addEventListener('click', function (e) { if (changed && !confirm('Diesen Entwurf verwerfen?')) e.preventDefault(); else sending = true; });
    window.addEventListener('beforeunload', function (e) { if (changed && !sending) { e.preventDefault(); e.returnValue = ''; } });
  }
  var active = document.querySelector('.pf-eintrag.aktiv');
  if (active && active.scrollIntoView) active.scrollIntoView({ block: 'nearest' });
})();
</script>
@endsection
