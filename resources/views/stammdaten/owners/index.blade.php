@extends('layouts.app')
@section('title', 'Eigentümer')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Eigentümer</h1>
  @if (auth()->user()->isAdmin())
    <a class="sd-btn" href="{{ route('owners.create') }}">Neuer Eigentümer</a>
  @endif
</div>

@if ($owners->isEmpty())
  <p class="sd-muted">
    @if (auth()->user()->isAdmin())
      Es ist noch kein Eigentümer angelegt. Lege zuerst die Eigentümer an, danach Objekte, Einheiten und Mieter.
    @else
      Dir wurde noch kein Eigentümer freigegeben. Der Administrator stellt das beim jeweiligen Eigentümer unter „Freigaben“ ein.
    @endif
  </p>
@else
  <div class="sd-table-wrap">
    <table class="sd">
      <thead><tr><th>Name</th><th>Ort</th><th>Steuerstatus</th><th>Objekte</th></tr></thead>
      <tbody>
      @foreach ($owners as $owner)
        <tr>
          <td><a href="{{ route('owners.show', $owner) }}">{{ $owner->name }}</a></td>
          <td>{{ $owner->city ?: '–' }}</td>
          <td>{{ $owner->taxStatusLabel() }}</td>
          <td>{{ $owner->properties_count }}</td>
        </tr>
      @endforeach
      </tbody>
    </table>
  </div>
@endif
@endsection
