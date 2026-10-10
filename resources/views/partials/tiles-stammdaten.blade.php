<a class="tile" href="{{ route('owners.index') }}">
  <strong>Eigentümer</strong>
  Stammdaten, Bankverbindungen und Freigaben für Mitarbeiter.
</a>
<a class="tile" href="{{ route('properties.index') }}">
  <strong>Objekte und Einheiten</strong>
  Häuser, Wohnungen und Gewerbeflächen.
</a>
<a class="tile" href="{{ route('tenants.index') }}">
  <strong>Mieter</strong>
  Privatpersonen und Firmen mit Ansprechpartnern.
</a>
<a class="tile" href="{{ route('import.index') }}">
  <strong>Import aus Excel</strong>
  Objekte, Einheiten, Mieter, Verträge und Zähler auf einmal übernehmen.
</a>
@php($rentLate = \App\Support\Rent\RentDashboard::overdueCount(auth()->user()))
<a class="tile" href="{{ route('rent.month') }}">
  <strong>Mieten</strong>
  Mietverträge, Mieteingang und offene Posten.
  @if ($rentLate > 0)
    <br><span style="color:#b42318;font-weight:600">{{ $rentLate }} Vertr{{ $rentLate === 1 ? 'ag' : 'äge' }} mit Rückstand</span>
  @endif
  @php($bankOpen = \App\Support\Bank\BankService::openCountFor(auth()->user()))
  @if ($bankOpen > 0)
    <br><span style="color:#8a5a12;font-weight:600">{{ $bankOpen }} {{ $bankOpen === 1 ? 'Kontoumsatz' : 'Kontoumsätze' }} zuzuordnen</span>
  @endif
</a>
@php($dunProp = \App\Support\Dunning\Mahnwesen::proposalCountFor(auth()->user()))
<a class="tile" href="{{ route('dunning.index') }}">
  <strong>Mahnwesen</strong>
  Zahlungserinnerungen und Mahnungen aus den offenen Posten.
  @if ($dunProp > 0)
    <br><span style="color:#b42318;font-weight:600">{{ $dunProp }} Schreiben fällig</span>
  @endif
</a>
<a class="tile" href="{{ route('nk.index') }}">
  <strong>Nebenkosten</strong>
  Nebenkostenabrechnung, Zähler und Umlageschlüssel.
</a>
@php($inqNew = \App\Models\Inquiry::newCountFor(auth()->user()))
<a class="tile" href="{{ route('listings.index') }}">
  <strong>Inserate und Webseite</strong>
  Freie Wohnungen, Montage-Wohnungen und Gewerbe online stellen, Texte für die Portale.
  @if ($inqNew > 0)
    <br><span style="color:#b42318;font-weight:600">{{ $inqNew }} neue Anfrage{{ $inqNew === 1 ? '' : 'n' }}</span>
  @endif
</a>
<a class="tile" href="{{ route('anlage-v.index') }}">
  <strong>Anlage V</strong>
  Einnahmen und Werbungskosten je Objekt für die Steuererklärung.
</a>
@php($rcOpen = \App\Models\Receipt::incompleteCountFor(auth()->user()))
@php($rxOpen = \App\Support\Receipts\RecurringOverview::openCountFor(auth()->user()))
<a class="tile" href="{{ route('receipts.index') }}">
  <strong>Belege</strong>
  Rechnungen und Quittungen als Foto oder PDF erfassen.
  @if ($rcOpen > 0)
    <br><span style="color:#8a5a12;font-weight:600">{{ $rcOpen }} unvollständig</span>
  @endif
  @php($expOpen = \App\Support\Bank\ExpenseMatcher::openCountFor(auth()->user()))
  @if ($expOpen > 0)
    <br><span style="color:#8a5a12;font-weight:600">{{ $expOpen }} {{ $expOpen === 1 ? 'Abbuchung' : 'Abbuchungen' }} ohne Beleg</span>
  @endif
  @if ($rxOpen > 0)
    <br><span style="color:#8a5a12;font-weight:600">{{ $rxOpen }} wiederkehrende Ausgabe{{ $rxOpen === 1 ? '' : 'n' }} ohne Beleg</span>
  @endif
</a>
