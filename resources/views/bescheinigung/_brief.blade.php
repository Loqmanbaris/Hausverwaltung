{{-- Briefkopf für Schreiben an den Mieter. Erwartet $owner, $mieter, $tenant, $property, $briefdatum --}}
@include('partials.briefkopf', ['bk' => \App\Support\Briefkopf::daten($owner)])
@php($bfOwnerAddr = trim(($owner?->street ?? '').', '.trim(($owner?->zip ?? '').' '.($owner?->city ?? '')), ', '))
<div class="sender">{{ $owner?->name }} · {{ $bfOwnerAddr }}</div>
<div class="addr">
  {{ $mieter }}<br>
  {{ $tenant?->street ?: $property?->street }}<br>
  {{ trim(($tenant?->zip ?: $property?->zip).' '.($tenant?->city ?: $property?->city)) }}
</div>
<div class="meta">{{ $owner?->city ? $owner->city.', ' : '' }}{{ \App\Support\Bescheinigung\Bescheinigung::de($briefdatum) }}</div>
