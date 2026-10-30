@extends('layouts.app')
@section('title', 'Selbstauskunft eintragen')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Selbstauskunft eintragen</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('applicants.show', $a) }}">Abbrechen</a></div>
</div>
<p class="sd-muted">{{ $a->name() }} · {{ $a->unitLabel() }}. Trage ein, was der Interessent auf dem Papierformular oder mündlich angegeben hat. Alle Felder sind freiwillig.</p>
<form method="POST" action="{{ route('applicants.disclosure.manual.store', $a) }}" class="sd-card">
  @csrf
  @include('applicants._fields', ['values' => $values])
  <label style="display:block;margin:.6rem 0"><input type="checkbox" name="consent" value="1" @checked(old('consent', $consent))> Der Interessent wurde über die Verwendung seiner Daten informiert und hat der Angabe zugestimmt.</label>
  <button class="sd-btn" type="submit">Speichern</button>
</form>
@endsection
