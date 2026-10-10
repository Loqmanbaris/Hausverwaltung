@extends('layouts.app')
@section('title', $owner->name)
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php($isAdmin = auth()->user()->isAdmin())
<div class="sd-head">
  <h1>{{ $owner->name }}</h1>
  <div class="sd-actions">
    @if ($isAdmin)
      <a class="sd-btn sec" href="{{ route('owners.edit', $owner) }}">Bearbeiten</a>
      <a class="sd-btn sec" href="{{ route('owners.access', $owner) }}">Freigaben</a>
    @endif
    <a class="sd-btn sec" href="{{ route('owners.index') }}">Alle Eigentümer</a>
  </div>
</div>

<div class="sd-card">
  <h2>Stammdaten</h2>
  <dl class="sd-dl">
    <dt>Anschrift</dt><dd>{{ $owner->street ?: '–' }}@if ($owner->addressLine()), {{ $owner->addressLine() }}@endif</dd>
    <dt>Steuerstatus</dt><dd>{{ $owner->taxStatusLabel() }}</dd>
    <dt>Steuernummer</dt><dd>{{ $owner->tax_number ?: '–' }}</dd>
    <dt>USt-ID</dt><dd>{{ $owner->vat_id ?: '–' }}</dd>
    <dt>Gläubiger-ID</dt><dd>{{ $owner->creditor_id ?: '–' }}</dd>
    <dt>E-Mail</dt><dd>{{ $owner->email ?: '–' }}</dd>
    <dt>Telefon</dt><dd>{{ $owner->phone ?: '–' }}</dd>
    @if ($owner->notes)
      <dt>Notizen</dt><dd style="white-space:pre-line">{{ $owner->notes }}</dd>
    @endif
  </dl>
</div>

<div class="sd-card">
  <h2>Bankverbindungen</h2>
  @if ($owner->bankAccounts->isEmpty())
    <p class="sd-muted">Noch keine Bankverbindung hinterlegt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Bezeichnung</th><th>Kontoinhaber</th><th>IBAN</th><th>Bank</th><th></th></tr></thead>
        <tbody>
        @foreach ($owner->bankAccounts as $account)
          <tr>
            <td>{{ $account->label ?: 'Konto' }} @if ($account->is_primary)<span class="sd-badge">Hauptkonto</span>@endif</td>
            <td>{{ $account->account_holder }}</td>
            <td style="white-space:nowrap">{{ $account->formattedIban() }}</td>
            <td>{{ $account->bank_name ?: '–' }}@if ($account->bic) ({{ $account->bic }})@endif</td>
            <td>
              @if ($isAdmin)
                <form class="sd-inline" method="POST" action="{{ route('owners.accounts.destroy', [$owner, $account]) }}" onsubmit="return confirm('Diese Bankverbindung wirklich entfernen?')">
                  @csrf @method('DELETE')
                  <button class="sd-btn danger small" type="submit">Entfernen</button>
                </form>
              @endif
            </td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif

  @if ($isAdmin)
    <details style="margin-top:1rem" @if ($errors->has('iban') || $errors->has('account_holder')) open @endif>
      <summary><strong>Bankverbindung hinzufügen</strong></summary>
      <form method="POST" action="{{ route('owners.accounts.store', $owner) }}" style="margin-top:.8rem">
        @csrf
        <div class="sd-grid">
          @include('partials.sd-field', ['name' => 'label', 'label' => 'Bezeichnung', 'hint' => 'z. B. Mietkonto oder Kautionskonto'])
          @include('partials.sd-field', ['name' => 'account_holder', 'label' => 'Kontoinhaber', 'value' => $owner->name, 'required' => true])
          @include('partials.sd-field', ['name' => 'iban', 'label' => 'IBAN', 'required' => true, 'autocomplete' => 'off'])
          @include('partials.sd-field', ['name' => 'bic', 'label' => 'BIC'])
          @include('partials.sd-field', ['name' => 'bank_name', 'label' => 'Bank'])
        </div>
        <label class="sd-check"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary'))> Als Hauptkonto verwenden</label>
        <button class="sd-btn" type="submit">Bankverbindung speichern</button>
      </form>
    </details>
  @endif
</div>

<div class="sd-card">
  <h2>Objekte</h2>
  @if ($owner->properties->isEmpty())
    <p class="sd-muted">Noch kein Objekt angelegt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Objekt</th><th>Ort</th><th>Einheiten</th></tr></thead>
        <tbody>
        @foreach ($owner->properties as $property)
          <tr>
            <td><a href="{{ route('properties.show', $property) }}">{{ $property->name }}</a></td>
            <td>{{ $property->city ?: '–' }}</td>
            <td>{{ $property->units_count }}</td>
          </tr>
        @endforeach
        </tbody>
      </table>
    </div>
  @endif
  <p><a class="sd-btn small" href="{{ route('properties.create', ['eigentuemer' => $owner->id]) }}">Objekt anlegen</a></p>
</div>

<div class="sd-card">
  <h2>Mieter</h2>
  <p>{{ $tenantCount }} Mieter bei diesem Eigentümer.</p>
  <p class="sd-actions">
    <a class="sd-btn sec small" href="{{ route('tenants.index', ['eigentuemer' => $owner->id]) }}">Mieter ansehen</a>
    <a class="sd-btn small" href="{{ route('tenants.create', ['eigentuemer' => $owner->id]) }}">Mieter anlegen</a>
  </p>
</div>

@include('documents.panel', ['docType' => 'owner', 'docId' => $owner->id])

@if ($isAdmin)
  <div class="sd-card">
    <h2>Eigentümer löschen</h2>
    <p class="sd-muted">Nur möglich, wenn keine Objekte und Mieter mehr vorhanden sind.</p>
    <form method="POST" action="{{ route('owners.destroy', $owner) }}" onsubmit="return confirm('Diesen Eigentümer wirklich löschen?')">
      @csrf @method('DELETE')
      <button class="sd-btn danger" type="submit">Eigentümer löschen</button>
    </form>
  </div>
@endif
@endsection
