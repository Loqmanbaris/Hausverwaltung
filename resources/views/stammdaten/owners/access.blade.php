@extends('layouts.app')
@section('title', 'Freigaben – '.$owner->name)
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Freigaben: {{ $owner->name }}</h1>
</div>
<p class="sd-muted">Mitarbeiter sehen und bearbeiten nur die Eigentümer, die hier angehakt sind. Neue Mitarbeiter starten ohne Zugriff. Administratoren sehen immer alles.</p>

<form method="POST" action="{{ route('owners.access.update', $owner) }}">
  @csrf @method('PUT')
  <div class="sd-card">
    @if ($employees->isEmpty())
      <p class="sd-muted">Es gibt noch keine Mitarbeiter. Lege sie unter „Benutzer“ an.</p>
    @else
      @foreach ($employees as $employee)
        <label class="sd-check">
          <input type="checkbox" name="users[]" value="{{ $employee->id }}" @checked(in_array($employee->id, old('users', $granted)))>
          <span>{{ $employee->name }} <span class="sd-muted">({{ $employee->email }})</span>@if ($employee->isLocked()) <span class="sd-badge">gesperrt</span>@endif</span>
        </label>
      @endforeach
    @endif
  </div>
  <div class="sd-actions">
    <button class="sd-btn" type="submit">Speichern</button>
    <a class="sd-btn sec" href="{{ route('owners.show', $owner) }}">Abbrechen</a>
  </div>
</form>
@endsection
