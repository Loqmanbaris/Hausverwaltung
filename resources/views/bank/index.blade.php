@extends('layouts.app')
@section('title', 'Kontoauszüge')
@section('content')
@include('partials.sd-styles')
@include('partials.rent-styles')
@include('partials.sd-flash')
@php($isAdmin = auth()->user()->isAdmin())
<div class="sd-head">
  <h1>Kontoauszüge</h1>
  <div class="sd-actions">
    <a class="sd-btn" href="{{ route('bank.transactions') }}">Umsätze zuordnen</a>
    <a class="sd-btn" href="{{ route('bank.expenses') }}">Ausgaben abgleichen</a>
    <a class="sd-btn sec" href="{{ route('rent.month') }}">Mieteingang</a>
  </div>
</div>

<div class="sd-card">
  <h2>Kontoauszug einlesen</h2>
  <p class="sd-muted" style="margin-top:0">Im Online-Banking der Sparkasse unter „Umsätze“ einen Zeitraum wählen, auf „Export“ klicken und das Format <strong>CSV-CAMT</strong> wählen. Die Datei hier hochladen. Zeiträume dürfen sich überschneiden: Umsätze, die schon da sind, werden nicht doppelt angelegt.</p>
  @if ($accounts->isEmpty())
    <div class="rt-warn">Es ist noch keine Bankverbindung hinterlegt. Trage das Konto zuerst beim Eigentümer unter „Bankverbindungen“ ein.</div>
  @endif
  <form method="POST" action="{{ route('bank.store') }}" enctype="multipart/form-data">
    @csrf
    <div class="sd-grid">
      <div class="sd-field">
        <label for="f-datei">Datei (CSV) *</label>
        <input id="f-datei" type="file" name="datei" accept=".csv,.CSV,.txt,text/csv" required>
      </div>
      <div class="sd-field">
        <label for="f-account_id">Konto</label>
        <select id="f-account_id" name="account_id">
          <option value="">automatisch aus der Datei erkennen</option>
          @foreach ($accounts as $a)
            <option value="{{ $a->id }}">{{ $a->owner?->name }} – {{ $a->label ?: $a->bank_name }} ({{ substr($a->formattedIban(), -9) }})</option>
          @endforeach
        </select>
      </div>
    </div>
    <button class="sd-btn" type="submit">Einlesen</button>
  </form>
</div>

<div class="sd-card">
  <h2>Konten</h2>
  @if ($accounts->isEmpty())
    <p class="sd-muted" style="margin:0">Keine Bankverbindung hinterlegt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Eigentümer und Konto</th><th>Letzter Umsatz</th><th>Zuzuordnen</th><th></th></tr></thead>
        <tbody>
          @foreach ($accounts as $a)
            <tr>
              <td>{{ $a->owner?->name }}<br><span class="sd-muted">{{ $a->label ?: $a->bank_name }} · {{ $a->formattedIban() }}</span></td>
              <td data-l="Letzter Umsatz">{{ isset($last[$a->id]) ? \Carbon\Carbon::parse($last[$a->id])->format('d.m.Y') : 'noch nichts eingelesen' }}</td>
              <td data-l="Zuzuordnen">@if (($open[$a->id] ?? 0) > 0)<span class="rt-part">{{ $open[$a->id] }}</span>@else<span class="sd-muted">0</span>@endif</td>
              <td><a class="sd-btn sec small" href="{{ route('bank.transactions', ['konto' => $a->id]) }}">Umsätze</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="sd-card">
  <h2>Eingelesene Dateien</h2>
  @if ($imports->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd rt-stack">
        <thead><tr><th>Eingelesen</th><th>Datei</th><th>Zeitraum</th><th>Umsätze</th><th></th></tr></thead>
        <tbody>
          @foreach ($imports as $i)
            <tr>
              <td style="white-space:nowrap">{{ $i->created_at->timezone('Europe/Berlin')->format('d.m.Y H:i') }}<br><span class="sd-muted">{{ $i->creator?->name }}</span></td>
              <td>{{ $i->filename }}<br><span class="sd-muted">{{ $i->owner?->name }} · {{ substr($i->account?->formattedIban() ?? '', -9) }}</span></td>
              <td data-l="Zeitraum">{{ $i->period_from?->format('d.m.Y') }} – {{ $i->period_to?->format('d.m.Y') }}</td>
              <td data-l="Umsätze">{{ $i->rows_new }} neu @if ($i->rows_duplicate > 0), {{ $i->rows_duplicate }} schon vorhanden @endif</td>
              <td>
                @if ($isAdmin)
                  <form class="sd-inline" method="POST" action="{{ route('bank.destroy', $i) }}" onsubmit="return confirm('Diesen Kontoauszug und seine Umsätze löschen? Das geht nur, wenn noch nichts zugeordnet ist.')">
                    @csrf @method('DELETE')
                    <button class="sd-btn danger small" type="submit">Löschen</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
