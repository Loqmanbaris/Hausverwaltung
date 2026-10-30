@extends('layouts.app')
@section('title', 'Kalender-Abo')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
@php $box = 'padding:.45rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;width:100%'; @endphp
<div class="sd-head"><h1>Kalender-Abo</h1></div>
<p class="sd-muted">Fristen und Termine aus dem CRM erscheinen in deinem Handy- oder Computer-Kalender: Vertragsbeginn und -ende, Mieterhöhungen, Kündigungsfristen, Wartungen, Handwerker, Besichtigungen, Zinsbindungen und ablaufende Dokumente. Du siehst nur Termine zu den Eigentümern, für die du freigegeben bist.</p>

@if (! $feed || ! $url)
  <div class="sd-card">
    <h2>Noch nicht eingerichtet</h2>
    <p class="sd-muted" style="margin-top:0">Mit einem Klick erzeugt das CRM deinen persönlichen Kalender-Link.</p>
    <form method="POST" action="{{ route('calendar.create') }}">@csrf<button class="sd-btn" type="submit">Kalender-Abo einrichten</button></form>
  </div>
@else
  <div class="sd-card">
    <h2>Dein Link</h2>
    <div class="sd-field"><label for="f-url">Kalender-Adresse</label><input id="f-url" readonly value="{{ $url }}" onclick="this.select()" style="{{ $box }}"></div>
    <p style="margin:.4rem 0 0;display:flex;flex-wrap:wrap;gap:.5rem">
      <a class="sd-btn" href="{{ $webcal }}">In Kalender-App öffnen</a>
      <button class="sd-btn sec" type="button" onclick="navigator.clipboard.writeText(document.getElementById('f-url').value).then(function(){this.textContent='Kopiert'}.bind(this))">Link kopieren</button>
    </p>
    <p class="sd-muted" style="margin:.6rem 0 0">Wer diesen Link kennt, sieht deine Termine mit Namen von Mietern. Gib ihn nicht weiter. Ging er verloren, erzeuge unten einen neuen.@if ($feed->last_fetched_at) Zuletzt abgerufen: {{ $feed->last_fetched_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr.@else Noch nicht abgerufen.@endif</p>
  </div>

  <div class="sd-card">
    <h2>So trägst du ihn ein</h2>
    <ul style="margin:.2rem 0 0;padding-left:1.2rem;display:grid;gap:.4rem">
      <li><strong>iPhone:</strong> Auf „In Kalender-App öffnen“ tippen und das Abo bestätigen. Oder: Einstellungen → Kalender → Accounts → Account hinzufügen → Andere → Kalenderabo hinzufügen, Adresse einfügen.</li>
      <li><strong>Android und Google Kalender:</strong> Am Computer calendar.google.com öffnen, links bei „Weitere Kalender“ auf „+“ und „Per URL“, Adresse einfügen. Er erscheint danach auch im Handy. Google gleicht Abos nur selten ab, neue Termine können bis zu einen Tag brauchen.</li>
      <li><strong>Outlook:</strong> Kalender hinzufügen → Aus dem Internet abonnieren, Adresse einfügen.</li>
    </ul>
  </div>

  <form method="POST" action="{{ route('calendar.update') }}" class="sd-card">
    @csrf @method('PUT')
    <h2>Was soll drinstehen?</h2>
    <div style="display:grid;gap:.4rem">
      @foreach ($categories as $k => $label)
        <label><input type="checkbox" name="categories[]" value="{{ $k }}" @checked(in_array($k, $selected, true))> {{ $label }}</label>
      @endforeach
    </div>
    <button class="sd-btn small" type="submit" style="margin-top:.6rem">Speichern</button>
  </form>

  <div class="sd-card">
    <h2>Link verwalten</h2>
    <div style="display:flex;flex-wrap:wrap;gap:.5rem">
      <form method="POST" action="{{ route('calendar.create') }}" onsubmit="return confirm('Neuen Link erzeugen? Der alte funktioniert dann nicht mehr und du musst den neuen in jedem Kalender eintragen.')">@csrf<button class="sd-btn sec" type="submit">Neuen Link erzeugen</button></form>
      <form method="POST" action="{{ route('calendar.destroy') }}" onsubmit="return confirm('Kalender-Abo beenden? Der Link funktioniert dann nicht mehr.')">@csrf @method('DELETE')<button class="sd-btn sec" type="submit">Abo beenden</button></form>
    </div>
  </div>
@endif
@endsection
