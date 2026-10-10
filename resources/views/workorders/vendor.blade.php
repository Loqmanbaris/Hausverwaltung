<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Auftrag {{ $o->number() }}</title>
@include('partials.styles')
@include('partials.sd-styles')
@include('workorders.styles')
<style>.vp{max-width:720px;margin:0 auto;padding:1.2rem 1rem 3rem}.vp h1{font-size:1.4rem}</style>
</head>
<body>
<main class="vp">
  <p class="sd-muted" style="margin:0 0 .3rem">{{ \App\Support\Mail\MailSettings::fromName() }} · Auftrag {{ $o->number() }}</p>
  <h1>{{ $o->title }}</h1>
  @if (session('status'))<div class="alert ok" role="status">{{ session('status') }}</div>@endif
  @if ($errors->any())<div class="sd-err">{{ implode(' ', $errors->all()) }}</div>@endif
  <div class="sd-card">
    <dl style="display:grid;grid-template-columns:max-content 1fr;gap:.35rem 1rem;margin:0">
      <dt class="sd-muted">Status</dt><dd style="margin:0"><span class="wo-st {{ $o->status }}">{{ $o->statusLabel() }}</span></dd>
      <dt class="sd-muted">Ort</dt><dd style="margin:0">{{ \App\Support\Technik\Auftraege::address($o) }}</dd>
      @if ($contact)<dt class="sd-muted">Vor Ort</dt><dd style="margin:0">{{ $contact['name'] }}@if ($contact['phone']), <a href="tel:{{ preg_replace('/[^0-9+]/', '', $contact['phone']) }}">{{ $contact['phone'] }}</a>@endif</dd>@endif
      @if ($o->priority !== 'normal')<dt class="sd-muted">Dringlichkeit</dt><dd style="margin:0"><span class="wo-pr">{{ \App\Models\WorkOrder::PRIORITY[$o->priority] }}</span></dd>@endif
      @if ($o->appointment_at)<dt class="sd-muted">Termin</dt><dd style="margin:0">{{ $o->appointment_at->format('d.m.Y') }}, {{ $o->appointment_at->format('H:i') }} Uhr</dd>@endif
    </dl>
    @if ($o->description)<p style="white-space:pre-line;margin:.8rem 0 0">{{ $o->description }}</p>@endif
  </div>
  @if ($o->photos->isNotEmpty())
    <div class="sd-card"><h2>Fotos</h2><div class="wo-photos">
      @foreach ($o->photos as $ph)<figure><a href="{{ route('vendor.photo', [$token, $ph, 'l']) }}" target="_blank" rel="noopener"><img src="{{ route('vendor.photo', [$token, $ph, 's']) }}" alt="Foto" loading="lazy"></a></figure>@endforeach
    </div></div>
  @endif

  @if ($o->isOpen())
    <div class="sd-card">
      <h2>Termin mitteilen</h2>
      <form method="POST" action="{{ route('vendor.appointment', $token) }}" class="wo-form">@csrf
        <div><label for="v-at">Datum und Uhrzeit</label><input id="v-at" type="datetime-local" name="appointment_at" required value="{{ $o->appointment_at?->format('Y-m-d\TH:i') }}"></div>
        <div><label for="v-n">Hinweis</label><input id="v-n" name="note" maxlength="1000"></div>
        <div><button class="sd-btn" type="submit">Termin senden</button></div>
      </form>
    </div>
    <div class="sd-card">
      <h2>Arbeit erledigt</h2>
      <form method="POST" action="{{ route('vendor.done', $token) }}" enctype="multipart/form-data" class="wo-form">@csrf
        <div class="full"><label for="v-dn">Was wurde gemacht?</label><textarea id="v-dn" name="note" rows="3" maxlength="2000"></textarea></div>
        <div class="full"><label for="v-f">Fotos (optional)</label><input id="v-f" type="file" name="fotos[]" accept="image/*" multiple capture="environment"></div>
        <div><button class="sd-btn" type="submit">Erledigt melden</button></div>
      </form>
    </div>
  @else
    <div class="sd-card"><p style="margin:0">Dieser Auftrag ist abgeschlossen. Bitte senden Sie die Rechnung mit der Auftragsnummer <strong>{{ $o->number() }}</strong>.</p></div>
  @endif
  <p class="sd-muted" style="font-size:.82rem">Diese Seite ist nur über Ihren persönlichen Link erreichbar. Bitte nicht weitergeben.</p>
</main>
</body>
</html>
