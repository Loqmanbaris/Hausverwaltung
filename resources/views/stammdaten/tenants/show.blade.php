@extends('layouts.app')
@section('title', $tenant->display_name)
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php($isAdmin = auth()->user()->isAdmin())
<div class="sd-head">
  <h1>{{ $tenant->display_name }}</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('tenants.edit', $tenant) }}">Bearbeiten</a>
    <a class="sd-btn sec" href="{{ route('tenants.index') }}">Alle Mieter</a>
  </div>
</div>

<div class="sd-card">
  <h2>Stammdaten</h2>
  <dl class="sd-dl">
    <dt>Art</dt><dd>{{ $tenant->typeLabel() }}</dd>
    <dt>Eigentümer</dt><dd><a href="{{ route('owners.show', $tenant->owner) }}">{{ $tenant->owner->name }}</a></dd>
    @if ($tenant->type === 'firma')
      <dt>USt-ID</dt><dd>{{ $tenant->vat_id ?: '–' }}</dd>
    @endif
    <dt>Anschrift</dt><dd>{{ $tenant->addressLine() ?: '–' }}</dd>
    <dt>E-Mail</dt><dd>{{ $tenant->email ?: '–' }}</dd>
    <dt>Telefon</dt><dd>{{ $tenant->phone ?: '–' }}</dd>
    @if ($tenant->notes)
      <dt>Notizen</dt><dd style="white-space:pre-line">{{ $tenant->notes }}</dd>
    @endif
  </dl>
</div>

@php($tenantLeases = \App\Models\Lease::query()->where('tenant_id', $tenant->id)->with('unit.property')->orderByDesc('start_date')->get())
<div class="sd-card">
  <h2>Mietverträge</h2>
  @if ($tenantLeases->isEmpty())
    <p class="sd-muted">Noch kein Mietvertrag.</p>
  @else
    <ul style="margin:.2rem 0 .8rem;padding-left:1.2rem">
      @foreach ($tenantLeases as $tl)
        <li><a href="{{ route('leases.show', $tl) }}">{{ $tl->unit?->label }}, {{ $tl->unit?->property?->name }}</a>: ab {{ $tl->start_date->format('d.m.Y') }}{{ $tl->end_date ? ' bis '.$tl->end_date->format('d.m.Y') : '' }} ({{ $tl->stateLabel() }})</li>
      @endforeach
    </ul>
  @endif
  <a class="sd-btn small" href="{{ route('leases.create', ['mieter' => $tenant->id]) }}">Mietvertrag anlegen</a>
</div>

<div class="sd-card">
  <h2>Ansprechpartner</h2>
  @if ($tenant->contacts->isEmpty())
    <p class="sd-muted">Noch kein Ansprechpartner hinterlegt.@if ($tenant->type === 'firma') Bei Firmen gehen Rechnungen an die Ansprechpartner mit Haken „Rechnungen“.@endif</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Name</th><th>Funktion</th><th>E-Mail</th><th>Telefon</th><th>Rechnungen</th><th></th></tr></thead>
        <tbody>
        @foreach ($tenant->contacts as $contact)
          <tr>
            <td>{{ $contact->name }}</td>
            <td>{{ $contact->role ?: '–' }}</td>
            <td>{{ $contact->email ?: '–' }}</td>
            <td>{{ $contact->phone ?: '–' }}</td>
            <td>{{ $contact->receives_invoices ? 'ja' : 'nein' }}</td>
            <td>
              @if ($isAdmin)
                <form class="sd-inline" method="POST" action="{{ route('tenants.contacts.destroy', [$tenant, $contact]) }}" onsubmit="return confirm('Diesen Ansprechpartner wirklich entfernen?')">
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

  <details style="margin-top:1rem" @if ($errors->has('name') || $errors->has('role')) open @endif>
    <summary><strong>Ansprechpartner hinzufügen</strong></summary>
    <form method="POST" action="{{ route('tenants.contacts.store', $tenant) }}" style="margin-top:.8rem">
      @csrf
      <div class="sd-grid">
        @include('partials.sd-field', ['name' => 'name', 'label' => 'Name', 'required' => true])
        @include('partials.sd-field', ['name' => 'role', 'label' => 'Funktion', 'hint' => 'z. B. Geschäftsführer, Buchhaltung'])
        @include('partials.sd-field', ['name' => 'email', 'label' => 'E-Mail', 'type' => 'email'])
        @include('partials.sd-field', ['name' => 'phone', 'label' => 'Telefon', 'type' => 'tel'])
      </div>
      <label class="sd-check"><input type="checkbox" name="receives_invoices" value="1" @checked(old('receives_invoices'))> Erhält Rechnungen und Abrechnungen</label>
      <button class="sd-btn" type="submit">Ansprechpartner speichern</button>
    </form>
  </details>
</div>

@include('portal.admin-card', ['tenant' => $tenant])

@include('documents.panel', ['docType' => 'tenant', 'docId' => $tenant->id])

@php($mails = \Illuminate\Support\Facades\Schema::hasTable('sent_emails') ? \App\Models\SentEmail::query()->where('tenant_id', $tenant->id)->orderByDesc('id')->limit(10)->get() : collect())
@if ($mails->isNotEmpty())
  <div class="sd-card" id="emails">
    <h2>E-Mails</h2>
    <div class="sd-table-wrap">
      <table class="sd">
        <tbody>
          @foreach ($mails as $m)
            <tr>
              <td style="white-space:nowrap">{{ $m->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}</td>
              <td>{{ $m->kindLabel() }}</td>
              <td>{{ $m->subject }}</td>
              <td>{!! $m->status === 'gesendet' ? '<span style="color:#067647;font-weight:600">gesendet</span>' : '<span style="color:#b42318;font-weight:600">Fehler</span>' !!}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <p style="margin:.6rem 0 0"><a href="{{ route('mail.index', ['q' => $tenant->email ?: $tenant->display_name]) }}">Gesendete E-Mails</a>@if (\Illuminate\Support\Facades\Route::has('inbox.index')) · <a href="{{ route('inbox.index', ['mieter' => $tenant->id]) }}">Empfangene E-Mails</a>@endif</p>
  </div>
@endif

@if ($isAdmin)
  <div class="sd-card">
    <h2>Mieter löschen</h2>
    <form method="POST" action="{{ route('tenants.destroy', $tenant) }}" onsubmit="return confirm('Diesen Mieter wirklich löschen?')">
      @csrf @method('DELETE')
      <button class="sd-btn danger" type="submit">Mieter löschen</button>
    </form>
  </div>
@endif
@endsection
