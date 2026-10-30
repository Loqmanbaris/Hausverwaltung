@extends('layouts.app')
@section('title', 'Mieterhöhungen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  use App\Models\RentIncrease;
  use App\Support\Money;
  use App\Support\Rent\Erhoehung\Regeln;
  $eur = fn ($c) => Regeln::eur((int) round($c));
@endphp
<div class="sd-head">
  <h1>Mieterhöhungen</h1>
  <div class="sd-actions">
    @if ($owners->count() > 1)
      <form method="GET" style="display:inline">
        <select name="eigentuemer" onchange="this.form.submit()" aria-label="Eigentümer" style="padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit">
          <option value="">Alle Eigentümer</option>
          @foreach ($owners as $o)<option value="{{ $o->id }}" @selected($ownerId === $o->id)>{{ $o->name }}</option>@endforeach
        </select>
      </form>
    @endif
  </div>
</div>
<p class="sd-muted">Hier siehst du, wo eine Erhöhung möglich ist und wie weit laufende Verfahren sind. Die Prüfung beachtet Sperrfrist, Wartefrist, Kappungsgrenze und Vergleichsmiete. Sie ersetzt keine Rechtsberatung.</p>

@if ($running->isNotEmpty())
  <div class="sd-card">
    <h2>Laufende Verfahren</h2>
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Mietvertrag</th><th>Art</th><th class="rt-num">Kaltmiete</th><th>Gilt ab</th><th>Stand</th><th></th></tr></thead>
        <tbody>
          @foreach ($running as $r)
            @php
              $hint = null;
              if ($r->status === 'erklaert' && $r->needsConsent() && $r->consent_until) {
                $hint = $r->consent_until->format('Y-m-d') < $today ? 'Zustimmungsfrist seit '.$r->consent_until->format('d.m.Y').' abgelaufen' : 'Zustimmung bis '.$r->consent_until->format('d.m.Y');
              } elseif ($r->status === 'erklaert' && ! $r->needsConsent() && $r->kind !== 'staffel') {
                $hint = $r->effective_from->format('Y-m-d') <= $today ? 'gilt jetzt, bitte in den Mietstand übernehmen' : 'wird zum '.$r->effective_from->format('d.m.Y').' wirksam';
              } elseif ($r->status === 'abgelehnt' && $r->suit_until) {
                $hint = 'Klage auf Zustimmung bis '.$r->suit_until->format('d.m.Y');
              } elseif ($r->status === 'entwurf') {
                $hint = 'Schreiben noch nicht versendet';
              }
            @endphp
            <tr>
              <td><a href="{{ route('leases.show', $r->lease_id) }}">{{ $r->lease?->title() }}</a></td>
              <td>{{ $r->kindLabel() }}</td>
              <td class="rt-num">{{ Money::format($r->old_cold) }} → {{ Money::format($r->new_cold) }}</td>
              <td>{{ $r->effective_from->format('d.m.Y') }}</td>
              <td>{{ $r->statusLabel() }}@if ($hint)<br><span class="sd-muted">{{ $hint }}</span>@endif</td>
              <td><a class="sd-btn sec small" href="{{ route('rent_increase.show', $r) }}">Öffnen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

<div class="sd-card">
  <h2>Wo eine Erhöhung möglich ist</h2>
  @if ($possible === [])
    <p class="sd-muted" style="margin:0">Keine laufenden Wohnraum-, Index- oder Staffelverträge gefunden.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Mietvertrag</th><th>Verfahren</th><th class="rt-num">Kaltmiete</th><th class="rt-num">je m²</th><th>Letzte Änderung</th><th>Möglich</th><th></th></tr></thead>
        <tbody>
          @foreach ($possible as $p)
            @php($l = $p['lease'])
            <tr>
              <td><a href="{{ route('leases.show', $l) }}">{{ $l->title() }}</a></td>
              <td>{{ ['vergleich' => 'Vergleichsmiete', 'index' => 'Indexanpassung', 'staffel' => 'Staffel'][$p['kind']] }}</td>
              <td class="rt-num">{{ $eur($p['cold']) }}</td>
              <td class="rt-num">{{ $p['per_sqm'] !== null ? $eur($p['per_sqm']) : '–' }}</td>
              <td>{{ $p['last'] ? Regeln::de($p['last']) : '–' }}</td>
              <td>
                @if ($p['open'])
                  <span class="sd-badge">Verfahren läuft</span>
                @elseif ($p['kind'] === 'staffel')
                  {{ $p['earliest'] ? 'nächste Staffel '.Regeln::de($p['earliest']) : ($p['note'] ?? '–') }}
                @elseif ($p['ready'])
                  <span class="sd-badge" style="background:#ecfdf5;color:#065f46">jetzt möglich</span>
                @else
                  Erklärung ab {{ Regeln::de($p['earliest']) }}
                @endif
              </td>
              <td>
                @unless ($p['open'])
                  @if ($p['kind'] !== 'staffel' || $p['ready'])
                    <a class="sd-btn sec small" href="{{ route('rent_increase.create', ['lease' => $l, 'art' => $p['kind']]) }}">Vorbereiten</a>
                  @endif
                @endunless
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
    <p class="sd-muted" style="margin:.6rem 0 0">Bei Gewerbe und Montage-Wohnungen gibt es keine gesetzliche Vergleichsmiete. Dort ist eine Erhöhung nur per Vereinbarung oder Vertragsklausel möglich (beim Mietvertrag unter „Mieterhöhung“).</p>
  @endif
</div>

@if ($done->isNotEmpty())
  <div class="sd-card">
    <h2>Abgeschlossen</h2>
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Mietvertrag</th><th>Art</th><th class="rt-num">Kaltmiete</th><th>Gilt ab</th><th>Stand</th><th></th></tr></thead>
        <tbody>
          @foreach ($done as $r)
            <tr>
              <td><a href="{{ route('leases.show', $r->lease_id) }}">{{ $r->lease?->title() }}</a></td>
              <td>{{ $r->kindLabel() }}</td>
              <td class="rt-num">{{ Money::format($r->old_cold) }} → {{ Money::format($r->new_cold) }}</td>
              <td>{{ $r->effective_from->format('d.m.Y') }}</td>
              <td>{{ $r->statusLabel() }}</td>
              <td><a class="sd-btn sec small" href="{{ route('rent_increase.show', $r) }}">Öffnen</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endif

@if (auth()->user()->isAdmin())
  <div class="sd-card">
    <h2>Einstellung für Indexmieten</h2>
    <p class="sd-muted" style="margin-top:0">Die geplante Begrenzung von Indexmieten („Mietrecht II“) ist noch nicht in Kraft (Stand Oktober 2026: Bundestag hat den Entwurf beraten, Beschluss steht aus). Standard ist deshalb keine Begrenzung. Wenn das Gesetz gilt, kannst du hier das passende Modell wählen, es gilt für neue Berechnungen.</p>
    <form method="POST" action="{{ route('rent_increase.settings') }}" style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center">
      @csrf
      <select name="rent_index_cap_mode" aria-label="Begrenzung" style="padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit">
        @foreach (Regeln::INDEX_CAP_MODES as $k => $label)<option value="{{ $k }}" @selected($indexMode === $k)>{{ $label }}</option>@endforeach
      </select>
      <button class="sd-btn small" type="submit">Speichern</button>
    </form>
  </div>
@endif
@endsection
