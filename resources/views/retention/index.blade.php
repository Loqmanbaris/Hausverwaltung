@extends('layouts.app')
@section('title', 'Löschfristen')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php
  $box = 'padding:.4rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit';
  $de = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d.m.Y') : '–';
@endphp
<div class="sd-head"><h1>Löschfristen für ehemalige Mieter</h1></div>
<p class="sd-muted">Personenbezogene Daten dürfen nicht ewig bleiben. Gleichzeitig müssen Buchführungsunterlagen je nach Art sechs bis zehn Jahre aufbewahrt werden (§ 147 AO). Deshalb gibt es zwei Stufen. Das CRM löscht nichts von allein: Es schlägt vor, du bestätigst. Die Fristen und Abgrenzungen bitte mit Steuerberater oder Datenschutzbeauftragtem abstimmen.</p>
@if ($errors->has('retention'))<div class="sd-err">{{ $errors->first('retention') }}</div>@endif

<form method="POST" action="{{ route('retention.settings') }}" class="sd-card" style="display:flex;flex-wrap:wrap;gap:.8rem;align-items:flex-end">
  @csrf
  <div class="sd-field" style="margin:0"><label for="f-m">Stufe 1 nach (Monaten)</label><input id="f-m" type="number" name="months" min="1" max="120" value="{{ old('months', $months) }}" style="{{ $box }};width:7rem"></div>
  <div class="sd-field" style="margin:0"><label for="f-y">Stufe 2 nach (Jahren)</label><input id="f-y" type="number" name="years" min="1" max="30" value="{{ old('years', $years) }}" style="{{ $box }};width:7rem"></div>
  <button class="sd-btn" type="submit">Speichern</button>
  <p class="sd-muted" style="flex-basis:100%;margin:0"><strong>Stufe 1</strong> (Standard 36 Monate nach Mietende, Verjährung): Kontaktdaten, E-Mail-Adresse, Telefon, Notizen, Ausweis- und Bonitätsunterlagen, Fotos, Schriftverkehr und die Inhalte versendeter E-Mails. <strong>Stufe 2</strong> (Standard 10 Jahre): zusätzlich Name, Anschrift und alle übrigen Dokumente. Mieterkonto, Beträge und Abrechnungen bleiben als Buchführungsunterlagen. Bei Firmen bleibt der Firmenname.</p>
</form>

<div class="sd-card">
  <h2>Fällig</h2>
  @if ($due === [])
    <p class="sd-muted" style="margin:0">Im Moment ist bei keinem ehemaligen Mieter eine Frist abgelaufen.</p>
  @else
    <div class="sd-table-wrap"><table class="sd">
      <thead><tr><th>Mieter</th><th>Mietende</th><th>Fällig</th><th>Betrifft</th><th></th></tr></thead>
      <tbody>
        @foreach ($due as $p)
          @php($t = $p['tenant'])
          <tr>
            <td>{{ $t->display_name }}@if ($t->retention_hold) <span class="sd-badge">aufbewahren</span>@endif</td>
            <td>{{ $de($p['ref']) }}</td>
            <td>Stufe {{ $p['stage'] }}</td>
            <td>
              Kontakte {{ $p['counts']['contacts'] }}, E-Mails {{ $p['counts']['emails'] }}, Dokumente {{ $p['stage'] === 2 ? $p['counts']['docs1'] + $p['counts']['docs2'] : $p['counts']['docs1'] }}
              @foreach ($p['blocked'] as $b)<br><span class="sd-badge" style="background:#fffbeb;color:#92400e">gesperrt: {{ $b }}</span>@endforeach
            </td>
            <td style="white-space:nowrap">
              @if ($p['blocked'] === [])
                <form method="POST" action="{{ route('retention.clean', $t) }}" style="display:inline" onsubmit="return confirm('Stufe {{ $p['stage'] }} für {{ addslashes($t->display_name) }} jetzt ausführen? Das lässt sich nicht rückgängig machen.')">@csrf<input type="hidden" name="stage" value="{{ $p['stage'] }}"><button class="sd-btn small" type="submit">Bereinigen</button></form>
              @endif
              @unless ($t->retention_hold)
                <form method="POST" action="{{ route('retention.hold', $t) }}" style="display:inline-flex;gap:.3rem">@csrf<input type="hidden" name="hold" value="1"><input name="reason" placeholder="Grund" maxlength="190" aria-label="Grund" style="{{ $box }};width:8rem"><button class="sd-btn sec small" type="submit">Aufbewahren</button></form>
              @endunless
            </td>
          </tr>
        @endforeach
      </tbody>
    </table></div>
  @endif
</div>

@if ($held->isNotEmpty())
  <div class="sd-card">
    <h2>Von der Löschung ausgenommen</h2>
    <div class="sd-table-wrap"><table class="sd"><tbody>
      @foreach ($held as $t)
        <tr><td><a href="{{ route('tenants.show', $t) }}">{{ $t->display_name }}</a></td><td class="sd-muted">{{ $t->hold_reason }}</td>
          <td><form method="POST" action="{{ route('retention.hold', $t) }}">@csrf<input type="hidden" name="hold" value="0"><button class="sd-btn sec small" type="submit">Markierung entfernen</button></form></td></tr>
      @endforeach
    </tbody></table></div>
  </div>
@endif
<p class="sd-muted">Nicht erfasst: Unterschriften in Verträgen und Übergabeprotokollen sowie Belege im Beleg-Bereich. Sie bleiben, solange sie aufzubewahren sind. Interessenten werden schon automatisch gelöscht (Paket 31).</p>
@endsection
