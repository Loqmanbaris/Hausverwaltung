@if ($errors->any())
  <div class="alert error"><ul style="margin:0;padding-left:1.1rem">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif
<form class="pl-form" method="POST" action="{{ route('public.send') }}">
  @csrf
  <input type="hidden" name="t" value="{{ $formToken }}">
  @if ($listing)<input type="hidden" name="listing" value="{{ $listing->id }}">@endif
  @php($topic = $topic ?? old('topic'))
  @if ($topic === 'schaden')<input type="hidden" name="topic" value="schaden">@endif
  <div class="pl-hp" aria-hidden="true"><label for="c-website">Webseite (bitte leer lassen)</label><input id="c-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>
  <div class="row">
    <div><label for="c-name">Name *</label><input id="c-name" type="text" name="name" required maxlength="120" autocomplete="name" value="{{ old('name') }}"></div>
    <div><label for="c-company">Firma</label><input id="c-company" type="text" name="company" maxlength="120" autocomplete="organization" value="{{ old('company') }}"></div>
    <div><label for="c-email">E-Mail *</label><input id="c-email" type="email" name="email" required maxlength="190" autocomplete="email" value="{{ old('email') }}"></div>
    <div><label for="c-phone">Telefon</label><input id="c-phone" type="tel" name="phone" maxlength="60" autocomplete="tel" value="{{ old('phone') }}"></div>
  </div>
  @if ($topic === 'schaden')
    <label for="c-wohnung">Adresse und Wohnung *</label>
    <input id="c-wohnung" type="text" name="wohnung" required maxlength="190" placeholder="z. B. Hauptstraße 5, Erdgeschoss links" value="{{ old('wohnung') }}">
  @endif
  @if ($listing && $listing->kind === 'montage')
    <div class="row">
      <div><label for="c-from">Anreise</label><input id="c-from" type="date" name="date_from" min="{{ date('Y-m-d') }}" value="{{ old('date_from') }}"></div>
      <div><label for="c-to">Abreise</label><input id="c-to" type="date" name="date_to" min="{{ date('Y-m-d') }}" value="{{ old('date_to') }}"></div>
      <div><label for="c-persons">Personen</label><input id="c-persons" type="number" name="persons" min="1" max="50" value="{{ old('persons') }}"></div>
    </div>
  @endif
  <label for="c-message">Nachricht *</label>
  <textarea id="c-message" name="message" required minlength="10" maxlength="5000">{{ old('message', $listing ? 'Guten Tag, ich interessiere mich für „'.$listing->title.'“. ' : '') }}</textarea>
  @if ($topic === 'schaden')<small style="color:var(--muted)">Bitte beschreiben: Was ist kaputt, seit wann, und wann können wir in die Wohnung? Bei Gefahr bitte sofort anrufen.</small>@endif
  <label class="check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> <span>Ich bin einverstanden, dass meine Angaben zur Bearbeitung der Anfrage gespeichert werden. Mehr dazu in der <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Datenschutzerklärung</a>.</span></label>
  <button class="pub-btn" type="submit">{{ $topic === 'schaden' ? 'Schaden melden' : 'Anfrage senden' }}</button>
</form>
