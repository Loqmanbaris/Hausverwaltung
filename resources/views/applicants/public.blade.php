<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Selbstauskunft</title>
@include('partials.styles')
@include('partials.sd-styles')
<style>.vp{max-width:720px;margin:0 auto;padding:1.2rem 1rem 3rem}.vp h1{font-size:1.4rem}.small{font-size:.85rem;color:#4b5563}</style>
</head>
<body>
<main class="vp">
  <p class="sd-muted" style="margin:0 0 .3rem">{{ $a->owner?->name }}</p>
  <h1>Selbstauskunft für Mietinteressenten</h1>
  <p>Guten Tag {{ $a->name() }}, für die Wohnung <strong>{{ $a->unitLabel() }}</strong> bitten wir Sie um ein paar Angaben. Alle Fragen sind freiwillig. Bitte geben Sie nur an, was Sie mitteilen möchten. Nachweise verlangen wir erst, wenn wir Ihnen die Wohnung zusagen.</p>
  @if (! empty($errors2))<div class="sd-err"><ul>@foreach ($errors2 as $m)<li>{{ $m }}</li>@endforeach</ul></div>@endif
  <form method="POST" action="{{ route('disclosure.submit', $token) }}" class="sd-card">
    @csrf
    @include('applicants._fields', ['values' => $values])
    @include('applicants._privacy', ['owner' => $a->owner])
    <label style="display:block;margin:.6rem 0"><input type="checkbox" name="consent" value="1" required @checked(! empty($values['consent']))> Ich habe die Hinweise zum Datenschutz gelesen und bin mit der Verarbeitung meiner Angaben einverstanden.</label>
    <button class="sd-btn" type="submit">Absenden</button>
  </form>
  <p class="small">Der Link gilt bis {{ $d->token_expires_at?->timezone('Europe/Berlin')->format('d.m.Y') }} und nur für eine Abgabe.</p>
</main>
</body>
</html>
