@extends('layouts.app')
@section('title', $c->title())
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('contracts.document-styles')
<style>
.cs-paper{background:#fff;border:1px solid #e3e6ea;border-radius:.6rem;padding:1.4rem 1.6rem;max-width:52rem}
@media (max-width:640px){.cs-paper{padding:1rem}}
.cs-st{display:inline-block;border-radius:1rem;padding:.05rem .6rem;font-size:.85rem;font-weight:600}
.cs-entwurf{background:#f1f3f5;color:#475467}.cs-unterschrieben{background:#ecfdf3;color:#067647}.cs-storniert{background:#fdecec;color:#b42318}
.cs-hint{background:#fffaeb;border:1px solid #fedf89;border-radius:.6rem;padding:.6rem .9rem;margin:0 0 1rem;color:#7a4b00}
</style>
<div class="sd-head">
  <h1>{{ $c->title() }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('leases.show', $c->lease_id) }}#vertragsdokument">Zum Mietvertrag</a>
    <a class="sd-btn sec" href="{{ route('contracts.print', $c) }}" target="_blank" rel="noopener">Drucken</a>
    <a class="sd-btn sec" href="{{ route('documents.pdf', ['art' => 'vertrag', 'id' => $c->id]) }}">PDF</a>
    @if ($c->status !== 'storniert')<a class="sd-btn" href="{{ route('mail.compose', ['art' => 'vertrag', 'ids' => $c->id]) }}">Per E-Mail senden</a>@endif
  </div>
</div>
<p>Status: <span class="cs-st cs-{{ $c->status }}">{{ $c->statusLabel() }}</span>
  @if ($c->isSigned()) · {{ $c->sign_method === 'tablet' ? 'am Tablet' : 'auf Papier' }} unterschrieben am {{ $c->signed_at?->format('d.m.Y') }}@endif
  · {{ $c->lease?->unit?->label }}, {{ $c->lease?->tenant?->display_name }}</p>

@if ($c->isDraft())
  @if ($paper)
    <div class="cs-hint"><strong>Bitte auf Papier unterschreiben:</strong> Bei Wohnraum mit fester Laufzeit oder Kündigungsverzicht über einem Jahr verlangt das Gesetz die Schriftform (§ 550 BGB). Eine Unterschrift am Tablet reicht dafür nicht. Also zweimal ausdrucken, beide unterschreiben, und ein Exemplar hier als Scan ablegen.</div>
  @endif
  <div class="sd-card">
    <h2>Nächster Schritt</h2>
    <div class="sd-actions" style="justify-content:flex-start">
      @unless ($paper)<a class="sd-btn" href="{{ route('contracts.sign', $c) }}">Am Tablet unterschreiben</a>@endunless
      <a class="sd-btn {{ $paper ? '' : 'sec' }}" href="{{ route('contracts.print', $c) }}" target="_blank" rel="noopener">Zum Unterschreiben ausdrucken</a>
      <a class="sd-btn sec" href="{{ route('contracts.edit', $c) }}">Angaben ändern</a>
      <form method="POST" action="{{ route('contracts.destroy', $c) }}" class="sd-inline" onsubmit="return confirm('Entwurf löschen?')">@csrf @method('DELETE')<button class="sd-btn sec" type="submit">Entwurf löschen</button></form>
    </div>
  </div>
@endif

@if ($c->status !== 'storniert')
  <div class="sd-card">
    <h2>Unterschriebenes Exemplar</h2>
    @if ($c->scan_path)
      <p style="margin-top:0"><a href="{{ route('contracts.scan.show', $c) }}" target="_blank" rel="noopener">{{ $c->scan_name ?: 'Scan ansehen' }}</a></p>
    @else
      <p class="sd-muted" style="margin-top:0">{{ $c->isSigned() ? 'Am Tablet unterschrieben. Ein Scan ist nicht nötig, kann aber zusätzlich abgelegt werden.' : 'Nach der Unterschrift auf Papier hier den Scan oder ein Foto ablegen.' }}</p>
    @endif
    <form method="POST" action="{{ route('contracts.scan', $c) }}" enctype="multipart/form-data">
      @csrf
      <input type="file" name="scan" accept="application/pdf,image/jpeg,image/png" required>
      <button class="sd-btn sec small" type="submit">{{ $c->scan_path ? 'Scan ersetzen' : 'Scan ablegen' }}</button>
    </form>
  </div>
@endif

<div class="cs-paper">
  @include('contracts.document', ['c' => $c, 'sig' => $sig])
</div>

@if (auth()->user()->isAdmin() && $c->status !== 'storniert' && ! $c->isDraft())
  <div class="sd-card" style="margin-top:1rem">
    <h2>Stornieren</h2>
    <p class="sd-muted">Nur wenn der Vertrag nicht gilt, z. B. bei einem Fehler. Er bleibt zur Nachverfolgung gespeichert.</p>
    <form method="POST" action="{{ route('contracts.cancel', $c) }}" onsubmit="return confirm('Vertrag stornieren?')">@csrf<button class="sd-btn danger small" type="submit">Stornieren</button></form>
  </div>
@endif
@endsection
