{{-- Vertragstext. Erwartet $c (LeaseContract) und $sig (Unterschriften als data:-Adressen oder null). --}}
@php
  $h = $c->header ?? [];
  $tenantRole = ($h['kind'] ?? '') === 'montage' ? 'Mieter (Auftraggeber)' : 'Mieter';
@endphp
<div class="ct-doc">
  <h1 class="ct-title">{{ $h['title'] ?? $c->title() }}</h1>
  <p class="ct-between">Zwischen</p>
  <p class="ct-party"><strong>{{ $h['vermieter']['name'] ?: '________________' }}</strong><br>{{ $h['vermieter']['anschrift'] ?: '________________' }}<br><span class="ct-role">– Vermieter –</span></p>
  <p class="ct-between">und</p>
  <p class="ct-party"><strong>{{ $h['mieter']['name'] ?: '________________' }}</strong>@if (! empty($h['mieter']['vertreter']))<br>vertreten durch {{ $h['mieter']['vertreter'] }}@endif<br>{{ $h['mieter']['anschrift'] ?: '________________' }}
    @if (! empty($h['weitere_mieter']))<br>sowie: {!! nl2br(e($h['weitere_mieter'])) !!}@endif
    <br><span class="ct-role">– {{ $tenantRole }} –</span></p>
  <p class="ct-between">wird folgender Vertrag geschlossen:</p>

  @foreach ($c->body ?? [] as $s)
    <section class="ct-sec">
      <h2>§ {{ $s['no'] }} {{ $s['title'] }}</h2>
      @foreach ($s['paragraphs'] as $p)
        <p>{!! nl2br(e($p)) !!}</p>
      @endforeach
    </section>
  @endforeach

  <div class="ct-signs">
    <p>{{ $c->sign_place ?: (($h['ort'] ?? '') ?: '________________') }}, den {{ $c->signed_at ? $c->signed_at->format('d.m.Y') : '________________' }}</p>
    <div class="ct-sign-row">
      <div class="ct-sign">
        @if (! empty($sig['landlord']))<img src="{{ $sig['landlord'] }}" alt="Unterschrift Vermieter">@endif
        <div class="ct-line">Vermieter{{ $c->landlord_signer ? ': '.$c->landlord_signer : '' }}</div>
      </div>
      <div class="ct-sign">
        @if (! empty($sig['tenant']))<img src="{{ $sig['tenant'] }}" alt="Unterschrift Mieter">@endif
        <div class="ct-line">{{ $tenantRole }}{{ $c->tenant_signer ? ': '.$c->tenant_signer : '' }}</div>
      </div>
    </div>
  </div>
  <p class="ct-foot">Vertrag Nr. {{ $c->id }} · erstellt am {{ $h['erstellt'] ?? $c->created_at->format('d.m.Y') }} · Prüfsumme {{ $c->shortHash() }}@if ($c->sign_method === 'tablet') · elektronisch unterschrieben am {{ $c->signed_at?->format('d.m.Y H:i') }} Uhr @endif</p>
</div>
