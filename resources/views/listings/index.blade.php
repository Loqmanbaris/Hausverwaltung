@extends('layouts.app')
@section('title', 'Inserate')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php use App\Support\Listings\Listings; @endphp
<style>
.li-row{display:flex;gap:.8rem;align-items:flex-start}
.li-row img{width:96px;height:72px;object-fit:cover;border-radius:.35rem;background:var(--surface-2);flex:none}
.li-ph{width:96px;height:72px;border-radius:.35rem;background:var(--surface-2);flex:none;display:flex;align-items:center;justify-content:center;color:#98a2b3;font-size:.8rem}
.li-st{display:inline-block;border-radius:1rem;padding:.05rem .55rem;font-size:.85rem;font-weight:600}
.li-online{background:var(--ok-bg);color:var(--ok)}.li-entwurf{background:var(--surface-2);color:var(--muted)}.li-offline{background:var(--brass-bg);color:var(--brass)}
.li-new{display:flex;flex-wrap:wrap;gap:.5rem;align-items:center}
.li-new select{flex:1 1 18rem;min-width:0;padding:.45rem;border:1px solid var(--line-strong);border-radius:.4rem;font:inherit;background:var(--surface)}
</style>
<div class="sd-head">
  <h1>Inserate</h1>
  <div class="sd-actions">
    <a class="sd-btn sec" href="{{ route('inquiries.index') }}">Anfragen @if ($newInquiries > 0)<span class="sd-badge">{{ $newInquiries }} neu</span>@endif</a>
    <a class="sd-btn sec" href="{{ route('public.wohnen') }}" target="_blank" rel="noopener">Webseite ansehen</a>
    @if (auth()->user()->isAdmin())<a class="sd-btn sec" href="{{ route('site.edit') }}">Webseite gestalten</a>@endif
  </div>
</div>
<p class="sd-muted">Freie Wohnungen, Montage-Wohnungen und Gewerbeflächen für die Webseite. Zu jedem Inserat gibt es fertige Texte und Fotos für ImmoScout24 und Kleinanzeigen.</p>

<div class="sd-card">
  <h2>Neues Inserat</h2>
  <form class="li-new" method="GET" action="{{ route('listings.create') }}">
    <select name="einheit" required aria-label="Einheit">
      <option value="">– Einheit wählen –</option>
      @foreach ($units as $u)
        <option value="{{ $u['unit']->id }}">{{ $u['unit']->property?->name }} – {{ $u['unit']->label }} ({{ $u['unit']->usageLabel() }}){{ $u['rented'] ? ($u['ends'] ? ', vermietet bis '.$u['ends'] : ', vermietet') : ', frei' }}</option>
      @endforeach
    </select>
    <button class="sd-btn" type="submit">Weiter</button>
  </form>
  <p class="sd-muted" style="margin:.4rem 0 0">Fläche, Zimmer, Ausstattung und die letzte Miete werden aus der Einheit übernommen.</p>
</div>

<div class="sd-card">
  <h2>Vorhandene Inserate</h2>
  @if ($listings->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine Inserate.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Inserat</th><th>Art</th><th>Status</th><th class="rt-num">Preis</th><th></th></tr></thead>
        <tbody>
          @foreach ($listings as $l)
            @php($photo = $l->photos->first())
            <tr>
              <td>
                <div class="li-row">
                  @if ($photo)<img src="{{ route('listings.photos.show', [$l, $photo, 's']) }}" alt="" loading="lazy">@else<div class="li-ph">kein Foto</div>@endif
                  <div><a href="{{ route('listings.edit', $l) }}">{{ $l->title }}</a><br><span class="sd-muted">{{ $l->unit?->label }}, {{ $l->unit?->property?->name }} · {{ $l->owner?->name }}</span></div>
                </div>
              </td>
              <td>{{ $l->kindLabel() }}</td>
              <td><span class="li-st li-{{ $l->status }}">{{ $l->statusLabel() }}</span></td>
              <td class="rt-num">
                @if ($l->kind === 'montage')
                  @if ($l->price_night !== null){{ Listings::eur($l->price_night) }} / Nacht<br>@endif
                  @if ($l->price_month !== null){{ Listings::eur($l->price_month) }} / Monat @endif
                @elseif ($l->rent_cold !== null)
                  {{ Listings::eur($l->rent_cold) }} kalt
                @endif
              </td>
              <td style="white-space:nowrap">
                <a class="sd-btn sec small" href="{{ route('listings.portal', $l) }}">Portaltexte</a>
                @if ($l->kind === 'montage')<a class="sd-btn sec small" href="{{ route('bookings.index', $l->unit_id) }}">Belegung</a>@endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
