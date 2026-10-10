@extends('layouts.app')
@section('title', 'Status')
@section('content')
@include('partials.sd-styles')
@include('partials.sd-flash')
<div class="sd-head">
  <h1>Status</h1>
</div>

<div class="sd-card">
  <h2>Software</h2>
  <dl class="sd-dl">
    @foreach ($software as $label => $value)
      <dt>{{ $label }}</dt><dd>{{ $value }}</dd>
    @endforeach
  </dl>
</div>

<div class="sd-card">
  <h2>Prüfungen</h2>
  <div class="sd-table-wrap">
    <table class="sd">
      <thead><tr><th>Prüfung</th><th>Ergebnis</th><th>Hinweis</th></tr></thead>
      <tbody>
        @foreach ($checks as $check)
          <tr>
            <td>{{ $check['label'] }}</td>
            <td>
              @if ($check['state'] === 'ok')
                <span class="status-ok">In Ordnung</span>
              @elseif ($check['state'] === 'warn')
                <span class="status-wait">Bitte ansehen</span>
              @else
                <span class="status-off">Handlungsbedarf</span>
              @endif
              <div class="sd-muted" style="font-size:.9rem">{{ $check['value'] }}</div>
            </td>
            <td>{{ $check['hint'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="sd-card" id="cron">
  <h2>Cron-Adresse</h2>
  <p class="sd-muted" style="margin-top:0">Diese Adresse ruft der Cronjob bei IONOS einmal täglich auf (HTTP GET). Sie wirkt wie ein Schlüssel: bitte nicht weitergeben.</p>
  <p style="display:flex;flex-wrap:wrap;gap:.4rem;align-items:center">
    <input id="cron-url" type="text" readonly value="{{ $cronUrl }}" style="flex:1 1 22rem;min-width:0;padding:.5rem;border:1px solid #c5ccd3;border-radius:.4rem;font:inherit;background:#fbfcfd">
    <button class="sd-btn sec small" type="button" onclick="var f=document.getElementById('cron-url');f.select();(navigator.clipboard&&window.isSecureContext?navigator.clipboard.writeText(f.value):document.execCommand('copy'));this.textContent='Kopiert ✓'">Kopieren</button>
  </p>
  <form method="POST" action="{{ route('status.cron') }}" onsubmit="return confirm('Neue Cron-Adresse erzeugen? Die alte funktioniert dann sofort nicht mehr und muss bei IONOS ausgetauscht werden.')">
    @csrf
    <button class="sd-btn sec small" type="submit">Neue Cron-Adresse erzeugen</button>
  </form>
</div>
@endsection
