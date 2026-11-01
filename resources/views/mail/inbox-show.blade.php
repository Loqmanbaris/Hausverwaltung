@extends('layouts.app')
@section('title', $mail->subject)
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.is-meta{display:grid;grid-template-columns:auto 1fr;gap:.2rem .8rem;font-size:.92rem;margin:0 0 .8rem}
.is-meta dt{color:var(--muted,#6b7280)}
.is-meta dd{margin:0;overflow-wrap:anywhere}
.is-body{white-space:pre-wrap;overflow-wrap:anywhere;font-size:.95rem;line-height:1.5;background:#fafafa;border:1px solid var(--line,#e5e7eb);border-radius:8px;padding:.8rem .9rem;margin:0}
.is-att{display:flex;flex-wrap:wrap;gap:.45rem;margin:.8rem 0 0}
.is-att a{display:inline-flex;align-items:center;gap:.35rem;border:1px solid var(--line-strong,#d1d5db);border-radius:8px;padding:.35rem .6rem;text-decoration:none;color:inherit;font-size:.88rem;background:#fff}
.is-att small{color:var(--muted,#6b7280)}
.is-links{display:flex;flex-wrap:wrap;gap:.4rem;margin:0 0 .8rem}
.is-links a{font-size:.85rem;font-weight:700;border-radius:1rem;padding:.12rem .6rem;background:#eef2ff;color:#3730a3;text-decoration:none}
.is-text{width:100%;font:inherit;padding:.6rem .7rem;border:1px solid var(--line-strong);border-radius:8px}
</style>
<div class="sd-head">
  <h1 style="overflow-wrap:anywhere">{{ $mail->subject }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('inbox.index') }}">Zurück</a>
  </div>
</div>
@include('mail.partials.tabs')

<div class="sd-card">
  <dl class="is-meta">
    <dt>Von</dt><dd><strong>{{ $mail->from_name ?: $mail->from_address }}</strong>@if ($mail->from_name) &lt;{{ $mail->from_address }}&gt;@endif</dd>
    @if ($mail->reply_to)<dt>Antwort an</dt><dd>{{ $mail->reply_to }}</dd>@endif
    @if ($mail->to_addresses)<dt>An</dt><dd>{{ $mail->to_addresses }}</dd>@endif
    @if ($mail->cc)<dt>Kopie</dt><dd>{{ $mail->cc }}</dd>@endif
    <dt>Datum</dt><dd>{{ $mail->received_at?->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr @if ($mail->replied_at)· <span style="color:#067647">beantwortet {{ $mail->replied_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</span>@endif</dd>
  </dl>
  @if ($mail->tenant || $mail->owner || $mail->vendor || $mail->applicant)
    <div class="is-links">
      @if ($mail->tenant && \Illuminate\Support\Facades\Route::has('tenants.show'))<a href="{{ route('tenants.show', $mail->tenant_id) }}">Mieter: {{ $mail->tenant->display_name }}</a>@endif
      @if ($mail->owner && \Illuminate\Support\Facades\Route::has('owners.show'))<a href="{{ route('owners.show', $mail->owner_id) }}">Eigentümer: {{ $mail->owner->name }}</a>@endif
      @if ($mail->vendor && \Illuminate\Support\Facades\Route::has('vendors.show'))<a href="{{ route('vendors.show', $mail->vendor_id) }}">Lieferant: {{ $mail->vendor->name }}</a>@endif
      @if ($mail->applicant && \Illuminate\Support\Facades\Route::has('applicants.show'))<a href="{{ route('applicants.show', $mail->applicant_id) }}">Interessent</a>@endif
    </div>
  @endif
  <div class="is-body">{{ $mail->body }}</div>
  @if ($mail->attachments)
    <div class="is-att">
      @foreach ($mail->attachments as $i => $a)
        <a href="{{ route('inbox.attachment', [$mail->id, $i]) }}" target="_blank" rel="noopener">📎 {{ $a['name'] }} <small>{{ $a['size'] >= 1048576 ? number_format($a['size'] / 1048576, 1, ',', '.').' MB' : max(1, round($a['size'] / 1024)).' KB' }}</small></a>
      @endforeach
    </div>
  @endif
  <div class="sd-actions" style="margin-top:1rem">
    @if ($send)<a class="sd-btn" href="#antworten" onclick="document.getElementById('f-body').focus()">Antworten</a>@endif
    <a class="sd-btn sec" href="{{ route('mail.write', ['weiter' => $mail->id]) }}">Weiterleiten</a>
    <form method="POST" action="{{ route('inbox.archive', $mail->id) }}" style="margin:0">@csrf<button class="sd-btn sec" type="submit">{{ $mail->archived ? 'Zurück in den Posteingang' : 'Archivieren' }}</button></form>
    <form method="POST" action="{{ route('inbox.unread', $mail->id) }}" style="margin:0">@csrf<button class="sd-btn sec" type="submit">Als ungelesen markieren</button></form>
  </div>
</div>

@if ($send)
  <form method="POST" action="{{ route('inbox.reply', $mail->id) }}" enctype="multipart/form-data" class="sd-card" id="antworten">
    @csrf
    <h2>Antworten</h2>
    <div class="sd-field"><label for="f-to">An</label><input id="f-to" name="to" type="text" required value="{{ old('to', $mail->replyAddress()) }}"></div>
    <div class="sd-field"><label for="f-subject">Betreff</label><input id="f-subject" name="subject" type="text" required maxlength="200" value="{{ old('subject', $replySubject) }}"></div>
    <div class="sd-field"><label for="f-body">Text</label><textarea id="f-body" class="is-text" name="body" rows="12" required>{{ old('body', $replyBody) }}</textarea></div>
    <div class="sd-field"><label for="f-dateien">Anhänge (höchstens 5, je 10 MB)</label><input id="f-dateien" name="dateien[]" type="file" multiple></div>
    <div class="sd-actions"><button class="sd-btn" type="submit">Antwort senden</button></div>
  </form>
@else
  <div class="sd-err">Antworten geht erst, wenn der E-Mail-Versand eingerichtet ist.</div>
@endif
@endsection
