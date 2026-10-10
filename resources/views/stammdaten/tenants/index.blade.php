@extends('layouts.app')
@section('title', 'Mieter')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Mieter</h1>
  <a class="sd-btn" href="{{ route('tenants.create', $ownerId ? ['eigentuemer' => $ownerId] : []) }}">Neuer Mieter</a>
</div>

<form method="GET" action="{{ route('tenants.index') }}" class="sd-card">
  <div class="sd-grid">
    <div class="sd-field">
      <label for="f-q">Suche nach Name</label>
      <input id="f-q" type="text" name="q" value="{{ $search }}">
    </div>
    <div class="sd-field">
      <label for="f-eig">Eigentümer</label>
      <select id="f-eig" name="eigentuemer">
        <option value="">alle</option>
        @foreach ($owners as $owner)
          <option value="{{ $owner->id }}" @selected($ownerId === (int) $owner->id)>{{ $owner->name }}</option>
        @endforeach
      </select>
    </div>
  </div>
  <button class="sd-btn small" type="submit">Filtern</button>
  <a class="sd-btn sec small" href="{{ route('tenants.index') }}">Zurücksetzen</a>
</form>

@if ($tenants->isEmpty())
  <p class="sd-muted">Keine Mieter gefunden.</p>
@else
  <div class="sd-table-wrap">
    <table class="sd">
      <thead><tr><th>Name</th><th>Art</th><th>Ort</th><th>Eigentümer</th></tr></thead>
      <tbody>
      @foreach ($tenants as $tenant)
        <tr>
          <td><a href="{{ route('tenants.show', $tenant) }}">{{ $tenant->display_name }}</a></td>
          <td>{{ $tenant->typeLabel() }}</td>
          <td>{{ $tenant->city ?: '–' }}</td>
          <td>{{ $tenant->owner->name }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
