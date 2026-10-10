@extends('layouts.app')
@section('title', 'Kaufpreis und Wert')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@include('finance.styles')
@php($money = fn ($k) => old($k, $v->{$k} === null ? '' : \App\Support\Money::input($v->{$k})))
<div class="sd-head">
  <h1>Kaufpreis und Wert: {{ $property->name }}</h1>
  <div class="sd-actions"><a class="sd-btn sec" href="{{ route('properties.show', $property) }}#finanzierung">Abbrechen</a></div>
</div>
<form method="POST" action="{{ route('properties.values.update', $property) }}" class="sd-card">
  @csrf @method('PUT')
  <div class="fi-form">
    <div><label for="v-price">Kaufpreis</label><input id="v-price" name="purchase_price" inputmode="decimal" value="{{ $money('purchase_price') }}"><small>Für Bruttorendite und Kaufpreisfaktor.</small></div>
    <div><label for="v-costs">Kaufnebenkosten</label><input id="v-costs" name="purchase_costs" inputmode="decimal" value="{{ $money('purchase_costs') }}"><small>Grunderwerbsteuer, Notar, Grundbuch, Makler.</small></div>
    <div><label for="v-date">Kaufdatum</label><input id="v-date" type="date" name="purchase_date" value="{{ old('purchase_date', $v->purchase_date?->format('Y-m-d')) }}"></div>
    <div><label for="v-eq">Eingesetztes Eigenkapital</label><input id="v-eq" name="equity" inputmode="decimal" value="{{ $money('equity') }}"><small>Leer = Kaufpreis + Nebenkosten − Darlehen.</small></div>
    <div><label for="v-mv">Geschätzter Wert heute</label><input id="v-mv" name="market_value" inputmode="decimal" value="{{ $money('market_value') }}"><small>Eigene Schätzung, z. B. aus Bodenrichtwert oder Vergleichsangeboten.</small></div>
    <div><label for="v-mvd">Stand der Schätzung</label><input id="v-mvd" type="date" name="market_value_date" value="{{ old('market_value_date', $v->market_value_date?->format('Y-m-d')) }}"></div>
    <div style="grid-column:1/-1"><label for="v-note">Notiz</label><textarea id="v-note" name="note" rows="2" maxlength="2000">{{ old('note', $v->note) }}</textarea></div>
  </div>
  <div style="margin-top:.8rem"><button class="sd-btn" type="submit">Speichern</button></div>
</form>
@endsection
