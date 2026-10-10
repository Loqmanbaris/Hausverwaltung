{{-- Die Erfolgsmeldung (status) zeigt schon das Seitenlayout an, hier nur Fehler. --}}
@if ($errors->any())
  <div class="sd-err">
    <strong>Bitte prüfen:</strong>
    <ul>
      @foreach ($errors->all() as $message)
        <li>{{ $message }}</li>
      @endforeach
    </ul>
  </div>
@endif
