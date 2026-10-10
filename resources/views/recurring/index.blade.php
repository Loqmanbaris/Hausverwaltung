@extends('layouts.app')
@section('title', 'Wiederkehrende Ausgaben')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<style>
.rx-ok{color:#067647;font-weight:600}
.rx-soon{color:#4b5563;font-weight:600}
.rx-due{color:#7a4b00;font-weight:600}
.rx-miss{color:#b42318;font-weight:600}
.rx-num{white-space:nowrap}
</style>
<div class="sd-head">
  <h1>Wiederkehrende Ausgaben</h1>
  <div class="sd-actions">
    <a class="sd-btn" href="{{ route('recurring.create') }}">Wiederkehrende Ausgabe anlegen</a>
    <a class="sd-btn sec" href="{{ route('receipts.index') }}">Zu den Belegen</a>
  </div>
</div>
<p class="sd-muted">Hier legst du Ausgaben an, die regelmäßig kommen, zum Beispiel Grundsteuer, Versicherung oder Darlehensraten. Das System zeigt, wann sie fällig sind, und ob dazu schon ein Beleg erfasst ist. Ein Beleg passt, wenn Eigentümer, Objekt und Kostenart stimmen und sein Datum nahe am Termin liegt (bis {{ \App\Support\Receipts\RecurrenceSchedule::DAYS_BEFORE }} Tage davor oder {{ \App\Support\Receipts\RecurrenceSchedule::DAYS_AFTER }} Tage danach).</p>

<div class="sd-card">
  <h2>Fälligkeiten</h2>
  <p class="sd-muted" style="margin-top:0">Von den letzten {{ \App\Support\Receipts\RecurringOverview::DAYS_BACK }} Tagen bis zu den nächsten {{ \App\Support\Receipts\RecurringOverview::DAYS_AHEAD }} Tagen.</p>
  @if (count($rows) === 0)
    <p class="sd-muted" style="margin:0">In diesem Zeitraum ist nichts fällig.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Fällig am</th><th>Ausgabe</th><th>Eigentümer und Objekt</th><th>Erwartet</th><th>Stand</th><th></th></tr></thead>
        <tbody>
          @foreach ($rows as $row)
            @php($expense = $row['expense'])
            <tr>
              <td class="rx-num">{{ $row['due']->format('d.m.Y') }}</td>
              <td>{{ $expense->title() }}</td>
              <td>{{ $expense->owner->name }}<br><span class="sd-muted">{{ $expense->placeLabel() }}</span></td>
              <td class="rx-num">{{ $expense->amountLabel() }}</td>
              <td>
                @switch($row['state'])
                  @case('erfasst') <span class="rx-ok">Beleg erfasst</span> @break
                  @case('bald') <span class="rx-soon">bald fällig</span> @break
                  @case('faellig') <span class="rx-due">fällig, Beleg fehlt noch</span> @break
                  @default <span class="rx-miss">Beleg fehlt</span>
                @endswitch
              </td>
              <td>
                @if ($row['receipt_id'])
                  <a class="sd-btn sec small" href="{{ route('receipts.show', $row['receipt_id']) }}">Beleg Nr. {{ $row['receipt_id'] }}</a>
                @else
                  <a class="sd-btn small" href="{{ route('receipts.create', ['wiederkehrend' => $expense->id, 'faellig' => $row['due']->format('Y-m-d')]) }}">Beleg erfassen</a>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>

<div class="sd-card">
  <h2>Angelegte Ausgaben</h2>
  <form method="GET" action="{{ route('recurring.index') }}" style="margin:0 0 .7rem">
    <label class="sd-check" style="margin:0;font-weight:400"><input type="checkbox" name="alle" value="1" @checked($all) onchange="this.form.submit()"> auch stillgelegte anzeigen</label>
  </form>
  @if ($templates->isEmpty())
    <p class="sd-muted" style="margin:0">Noch keine wiederkehrende Ausgabe angelegt.</p>
  @else
    <div class="sd-table-wrap">
      <table class="sd">
        <thead><tr><th>Ausgabe</th><th>Rhythmus</th><th>Eigentümer und Objekt</th><th>Erwartet</th><th>Stand</th><th></th></tr></thead>
        <tbody>
          @foreach ($templates as $expense)
            <tr>
              <td>{{ $expense->title() }}</td>
              <td>{{ $expense->scheduleLabel() }}<br><span class="sd-muted">ab {{ $expense->start_date->format('d.m.Y') }}@if ($expense->end_date), bis {{ $expense->end_date->format('d.m.Y') }}@endif</span></td>
              <td>{{ $expense->owner->name }}<br><span class="sd-muted">{{ $expense->placeLabel() }}</span></td>
              <td class="rx-num">{{ $expense->amountLabel() }}</td>
              <td>@if ($expense->active) aktiv @else <span class="sd-muted">stillgelegt</span> @endif</td>
              <td><a class="sd-btn sec small" href="{{ route('recurring.edit', $expense) }}">Bearbeiten</a></td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif
</div>
@endsection
