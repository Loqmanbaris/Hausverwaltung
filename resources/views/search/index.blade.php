@extends('layouts.app')
@section('title', 'Suche')
@section('content')
@include('partials.sd-styles')
<div class="sd-head">
  <h1>Suche</h1>
</div>

<form method="GET" action="{{ route('search') }}" role="search" style="display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:1rem">
  <input type="text" name="q" value="{{ $q }}" maxlength="100" autofocus
         placeholder="Name, Ort, Straße, E-Mail oder Telefon"
         aria-label="Suchbegriff"
         style="flex:1;min-width:240px;padding:.6rem;border:1px solid var(--line-strong);border-radius:.45rem;font:inherit">
  <button class="sd-btn" type="submit">Suchen</button>
</form>

@if ($q === '')
  <p class="sd-muted">Die Suche findet Eigentümer, Objekte, Einheiten, Mieter und Ansprechpartner. Mehrere Wörter grenzen den Treffer ein, z. B. „Müller Bremen“.</p>
@elseif ($tooShort)
  <p class="sd-muted">Bitte mindestens zwei Zeichen eingeben.</p>
@elseif ($total === 0)
  <p class="sd-muted">Keine Treffer für „{{ $q }}“.</p>
@else
  <p class="sd-muted">{{ $total }} Treffer für „{{ $q }}“.</p>

  @if ($results['owners']['items']->isNotEmpty())
    <div class="sd-card">
      <h2>Eigentümer</h2>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Name</th><th>Ort</th><th>Steuerstatus</th></tr></thead>
          <tbody>
          @foreach ($results['owners']['items'] as $owner)
            <tr>
              <td><a href="{{ route('owners.show', $owner) }}">{{ $owner->name }}</a></td>
              <td>{{ $owner->city ?: '–' }}</td>
              <td>{{ $owner->taxStatusLabel() }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @if ($results['owners']['more'])<p class="sd-muted" style="margin:.6rem 0 0">Weitere Treffer vorhanden. Bitte genauer suchen.</p>@endif
    </div>
  @endif

  @if ($results['properties']['items']->isNotEmpty())
    <div class="sd-card">
      <h2>Objekte</h2>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Objekt</th><th>Anschrift</th><th>Eigentümer</th></tr></thead>
          <tbody>
          @foreach ($results['properties']['items'] as $property)
            <tr>
              <td><a href="{{ route('properties.show', $property) }}">{{ $property->name }}</a></td>
              <td>{{ $property->addressLine() ?: '–' }}</td>
              <td>{{ $property->owner?->name ?? '–' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @if ($results['properties']['more'])<p class="sd-muted" style="margin:.6rem 0 0">Weitere Treffer vorhanden. Bitte genauer suchen.</p>@endif
    </div>
  @endif

  @if ($results['units']['items']->isNotEmpty())
    <div class="sd-card">
      <h2>Einheiten</h2>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Einheit</th><th>Objekt</th><th>Nutzung</th><th>Fläche</th><th></th></tr></thead>
          <tbody>
          @foreach ($results['units']['items'] as $unit)
            <tr>
              <td>{{ $unit->label }}@if ($unit->floor) <span class="sd-muted">({{ $unit->floor }})</span>@endif</td>
              <td>
                @if ($unit->property)
                  <a href="{{ route('properties.show', $unit->property) }}">{{ $unit->property->name }}</a>
                  <span class="sd-muted">{{ $unit->property->owner?->name }}</span>
                @else
                  –
                @endif
              </td>
              <td>{{ $unit->usageLabel() }}</td>
              <td>{{ $unit->area_sqm ? number_format((float) $unit->area_sqm, 2, ',', '.').' m²' : '–' }}</td>
              <td><a class="sd-btn sec small" href="{{ route('units.edit', $unit) }}">Bearbeiten</a></td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @if ($results['units']['more'])<p class="sd-muted" style="margin:.6rem 0 0">Weitere Treffer vorhanden. Bitte genauer suchen.</p>@endif
    </div>
  @endif

  @if ($results['tenants']['items']->isNotEmpty())
    <div class="sd-card">
      <h2>Mieter</h2>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Name</th><th>Art</th><th>Ort</th><th>Eigentümer</th></tr></thead>
          <tbody>
          @foreach ($results['tenants']['items'] as $tenant)
            <tr>
              <td><a href="{{ route('tenants.show', $tenant) }}">{{ $tenant->display_name }}</a></td>
              <td>{{ $tenant->typeLabel() }}</td>
              <td>{{ $tenant->city ?: '–' }}</td>
              <td>{{ $tenant->owner?->name ?? '–' }}</td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @if ($results['tenants']['more'])<p class="sd-muted" style="margin:.6rem 0 0">Weitere Treffer vorhanden. Bitte genauer suchen.</p>@endif
    </div>
  @endif

  @if ($results['contacts']['items']->isNotEmpty())
    <div class="sd-card">
      <h2>Ansprechpartner</h2>
      <div class="sd-table-wrap">
        <table class="sd">
          <thead><tr><th>Name</th><th>Funktion</th><th>Mieter</th></tr></thead>
          <tbody>
          @foreach ($results['contacts']['items'] as $contact)
            <tr>
              <td>{{ $contact->name }}</td>
              <td>{{ $contact->role ?: '–' }}</td>
              <td>
                @if ($contact->tenant)
                  <a href="{{ route('tenants.show', $contact->tenant) }}">{{ $contact->tenant->display_name }}</a>
                @else
                  –
                @endif
              </td>
            </tr>
          @endforeach
          </tbody>
        </table>
      </div>
      @if ($results['contacts']['more'])<p class="sd-muted" style="margin:.6rem 0 0">Weitere Treffer vorhanden. Bitte genauer suchen.</p>@endif
    </div>
  @endif
@endif
@endsection
