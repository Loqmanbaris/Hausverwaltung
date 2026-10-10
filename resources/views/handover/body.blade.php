{{-- Inhalt des Protokolls. Erwartet $p, $photos, $prev, $sig, optional $thumbs (eingebettete Vorschaubilder) --}}
@php
  use App\Support\Handover\HandoverService;
  $states = HandoverService::STATES;
  $lease = $p->lease;
  $h = $lease?->tenant;
  $num = fn ($v) => $v === null ? '' : str_replace('.', ',', rtrim(rtrim((string) $v, '0'), '.'));
@endphp
<div class="hb">
  <h1 class="hb-title">{{ $p->title() }}</h1>
  <table class="hb-meta">
    <tr><th>Objekt</th><td>{{ $lease?->unit?->label }}, {{ $lease?->unit?->property?->addressLine() }}</td></tr>
    <tr><th>Vermieter</th><td>{{ $lease?->owner?->name }}</td></tr>
    <tr><th>Mieter</th><td>{{ $h?->display_name }}</td></tr>
    <tr><th>Datum der Übergabe</th><td>{{ $p->handover_on->format('d.m.Y') }}</td></tr>
    @if ($p->attendees)<tr><th>Anwesend</th><td>{{ $p->attendees }}</td></tr>@endif
    @if ($p->new_address)<tr><th>Neue Anschrift</th><td>{{ $p->new_address }}</td></tr>@endif
    @if ($p->compare)<tr><th>Vergleich</th><td>Einzugsprotokoll vom {{ $p->compare->handover_on->format('d.m.Y') }}</td></tr>@endif
  </table>

  @if ($p->readings->isNotEmpty())
    <h2>Zählerstände</h2>
    <table class="hb-t">
      <thead><tr><th>Zähler</th><th>Nummer</th><th class="n">Stand</th></tr></thead>
      <tbody>
        @foreach ($p->readings as $r)
          <tr><td>{{ $r->meter?->kindLabel() }}{{ $r->meter?->label ? ' '.$r->meter->label : '' }}</td><td>{{ $r->meter?->number }}</td><td class="n">{{ $r->value !== null ? $num($r->value).' '.(\App\Models\Meter::UNITS[$r->meter?->kind] ?? '') : '________' }}</td></tr>
        @endforeach
      </tbody>
    </table>
  @endif

  <h2>Schlüssel</h2>
  <table class="hb-t">
    <thead><tr><th>Art</th><th class="n">Anzahl</th></tr></thead>
    <tbody>
      @foreach ($p->keys ?? [] as $k)
        <tr><td>{{ $k['label'] }}</td><td class="n">{{ $k['count'] !== '' ? $k['count'] : '____' }}</td></tr>
      @endforeach
    </tbody>
  </table>

  @foreach ($p->rooms ?? [] as $ri => $room)
    <section class="hb-room">
      <h2>{{ $room['name'] }}</h2>
      <table class="hb-t">
        <thead><tr><th>Prüfpunkt</th><th>Zustand</th><th>Bemerkung</th>@if ($p->compare)<th>Beim Einzug</th>@endif</tr></thead>
        <tbody>
          @foreach ($room['items'] ?? [] as $item)
            @php($new = HandoverService::isNewDefect($prev, $room['name'], $item['name'], $item['state'] ?? ''))
            @php($pv = $prev[mb_strtolower($room['name'])][mb_strtolower($item['name'])] ?? null)
            <tr @class(['hb-new' => $new])>
              <td>{{ $item['name'] }}</td>
              <td class="st st-{{ $item['state'] ?: 'leer' }}">{{ $states[$item['state'] ?? ''] ?? '–' }}{{ $new ? ' (neu)' : '' }}</td>
              <td>{{ $item['note'] ?? '' }}</td>
              @if ($p->compare)<td>{{ $pv ? ($states[$pv['state']] ?? '–').($pv['note'] ? ': '.$pv['note'] : '') : '–' }}</td>@endif
            </tr>
          @endforeach
        </tbody>
      </table>
      @if (! empty($room['note']))<p class="hb-note">{{ $room['note'] }}</p>@endif
      @if ($photos->has($ri))
        <div class="hb-photos">
          @foreach ($photos->get($ri) as $ph)
            <img src="{{ $thumbs[$ph->id] ?? route('handover.photos.show', [$p, $ph, 's']) }}" alt="Foto {{ $room['name'] }}">
          @endforeach
        </div>
      @endif
    </section>
  @endforeach

  @if ($p->note)
    <h2>Bemerkungen</h2>
    <p class="hb-note">{{ $p->note }}</p>
  @endif

  <div class="hb-signs">
    <p>Die Angaben in diesem Protokoll werden von beiden Seiten bestätigt.</p>
    <div class="hb-sign-row">
      <div class="hb-sign">@if (! empty($sig['landlord']))<img src="{{ $sig['landlord'] }}" alt="Unterschrift Vermieter">@endif<div class="hb-line">Vermieter{{ $p->landlord_signer ? ': '.$p->landlord_signer : '' }}</div></div>
      <div class="hb-sign">@if (! empty($sig['tenant']))<img src="{{ $sig['tenant'] }}" alt="Unterschrift Mieter">@endif<div class="hb-line">Mieter{{ $p->tenant_signer ? ': '.$p->tenant_signer : '' }}</div></div>
    </div>
  </div>
  <p class="hb-foot">Protokoll Nr. {{ $p->id }}@if ($p->content_hash) · Prüfsumme {{ $p->shortHash() }}@endif @if ($p->sign_method === 'tablet') · elektronisch unterschrieben am {{ $p->signed_at?->format('d.m.Y H:i') }} Uhr @endif</p>
</div>
