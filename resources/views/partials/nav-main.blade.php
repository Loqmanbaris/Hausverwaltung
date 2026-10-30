@php
  $u = auth()->user();
  $admin = $u?->isAdmin();
  $cnt = function (callable $f) { try { return (int) $f(); } catch (\Throwable) { return 0; } };
  $badge = [
      'bank' => $cnt(fn () => \App\Support\Bank\BankService::openCountFor($u)),
      'expenses' => $cnt(fn () => \App\Support\Bank\ExpenseMatcher::openCountFor($u)),
      'inquiries' => $cnt(fn () => \App\Models\Inquiry::newCountFor($u)),
      'orders' => $cnt(fn () => \App\Models\WorkOrder::visibleTo($u)->where('status', 'gemeldet')->count()),
      'maint' => $cnt(fn () => \App\Models\MaintenanceTask::visibleTo($u)->where('active', true)->where('next_due', '<', date('Y-m-d'))->count()),
      'setup' => $u?->isAdmin() ? $cnt(fn () => \App\Support\Setup\Einrichtung::summary()['open']) : 0,
      'heat' => $cnt(fn () => \App\Support\Nk\Verbrauchsinfo::open($u)->count() + \App\Models\Co2Claim::visibleTo($u)->where('status', 'offen')->count()),
      'day' => $cnt(fn () => \App\Support\Tag\MeinTag::openCount($u)),
      'docs' => $cnt(fn () => \App\Models\Document::visibleTo($u)->where('archived', false)->whereNotNull('expires_on')->where('expires_on', '<', date('Y-m-d'))->count()),
  ];
  // [Bezeichnung, Route, aktiv bei, Badge, nur Admin]
  $groups = [
      '' => [
          ['Mein Tag', 'tag.index', ['tag.*'], $badge['day'] ?? 0, false],
          ['Suche', 'search', ['search'], 0, false],
          ['E-Mails', 'mail.index', ['mail.index', 'mail.compose'], 0, false],
      ],
      'Stammdaten' => [
          ['Eigentümer', 'owners.index', ['owners.*'], 0, false],
          ['Objekte und Einheiten', 'properties.index', ['properties.*', 'units.*', 'nk.settings', 'nk.meters'], 0, false],
          ['Mieter', 'tenants.index', ['tenants.*'], 0, false],
          ['Dokumente', 'documents.index', ['documents.*'], $badge['docs'] ?? 0, false],
          ['Import aus Excel', 'import.index', ['import.*'], 0, false],
      ],
      'Vermietung' => [
          ['Mietverträge', 'leases.index', ['leases.*', 'contracts.*', 'handover.*', 'interest.*', 'installments.*'], 0, false],
          ['Interessenten', 'applicants.index', ['applicants.*'], 0, false],
          ['Mieterhöhungen', 'rent_increase.index', ['rent_increase.*'], 0, false],
          ['Kautionen', 'deposit.index', ['deposit.*'], 0, false],
      ],
      'Mieteingang und Mahnung' => [
          ['Mieteingang', 'rent.month', ['rent.month'], 0, false],
          ['Offene Posten', 'rent.open', ['rent.open'], 0, false],
          ['Mahnwesen', 'dunning.index', ['dunning.*'], 0, false],
          ['Kontoauszüge', 'bank.transactions', ['bank.index', 'bank.transactions'], $badge['bank'], false],
          ['Rechnungen schreiben', 'invoices.index', ['invoices.*'], 0, true],
          ['E-Rechnung (Mieten)', 'erechnung.index', ['erechnung.*'], 0, false],
      ],
      'Kosten' => [
          ['Belege', 'receipts.index', ['receipts.*', 'einvoice.*'], 0, false],
          ['Ausgaben abgleichen', 'bank.expenses', ['bank.expenses*'], $badge['expenses'], false],
          ['Wiederkehrende Ausgaben', 'recurring.index', ['recurring.*'], 0, false],
          ['Laufende Verträge', 'svc.index', ['svc.*'], 0, false],
          ['Lieferanten', 'vendors.index', ['vendors.*'], 0, false],
      ],
      'Abrechnung und Steuer' => [
          ['Nebenkosten', 'nk.index', ['nk.index', 'nk.show', 'nk.print'], 0, false],
          ['Zähler und CO2', 'consumption.index', ['consumption.*', 'co2.*'], $badge['heat'] ?? 0, false],
          ['Anlage V', 'anlage-v.index', ['anlage-v.*'], 0, false],
          ['Jahresbericht', 'report.index', ['report.*'], 0, false],
          ['Finanzierung', 'finance.index', ['finance.*', 'loans.*', 'properties.values'], 0, false],
          ['DATEV-Export', 'datev.index', ['datev.*'], 0, true],
      ],
      'Instandhaltung' => [
          ['Schäden und Aufträge', 'workorders.index', ['workorders.*'], $badge['orders'] ?? 0, false],
          ['Wartungsplan', 'maintenance.index', ['maintenance.*'], $badge['maint'] ?? 0, false],
      ],
      'Webseite' => [
          ['Inserate', 'listings.index', ['listings.*', 'bookings.*'], 0, false],
          ['Anfragen', 'inquiries.index', ['inquiries.*'], $badge['inquiries'], false],
          ['Webseite gestalten', 'site.edit', ['site.*'], 0, true],
      ],
      'Verwaltung' => [
          ['Kalender-Abo', 'calendar.index', ['calendar.*'], 0, false],
          ['Benutzer', 'users.index', ['users.*'], 0, true],
          ['Einstellungen', 'settings.edit', ['settings.*', 'cost-types.*'], 0, true],
          ['E-Mail einrichten', 'mail.settings', ['mail.settings'], 0, true],
          ['Vertragsvorlagen', 'contract-templates.index', ['contract-templates.*'], 0, true],
          ['Löschfristen', 'retention.index', ['retention.*'], 0, true],
          ['Sicherungen', 'backups.index', ['backups.*'], 0, true],
          ['Updates', 'updates.index', ['updates.*'], 0, true],
          ['Einrichtung prüfen', 'setup.index', ['setup.*'], $badge['setup'] ?? 0, true],
          ['Status', 'status', ['status'], 0, true],
          ['Protokoll', 'audit.index', ['audit.*'], 0, true],
      ],
  ];
  // Ansicht: „Alltag“ zeigt nur, was täglich gebraucht wird; „Verwaltung“ zeigt alles. Die Wahl wird pro Gerät gemerkt.
  $mode = request()->cookie('nav_mode') === 'verwaltung' ? 'verwaltung' : 'alltag';
  $daily = ['tag.index', 'search', 'mail.index', 'tenants.index', 'leases.index', 'applicants.index', 'rent.month', 'rent.open', 'dunning.index',
      'bank.transactions', 'invoices.index', 'receipts.index', 'bank.expenses', 'workorders.index', 'inquiries.index', 'documents.index'];
  $menu = [];
  foreach ($groups as $title => $items) {
      $visible = array_values(array_filter($items, fn ($i) => (! $i[4] || $admin) && \Illuminate\Support\Facades\Route::has($i[1])
          && ($mode === 'verwaltung' || in_array($i[1], $daily, true) || request()->routeIs(...$i[2]))));
      if ($visible === []) {
          continue;
      }
      $menu[] = [
          'title' => $title,
          'slug' => \Illuminate\Support\Str::slug($title),
          'items' => $visible,
          'on' => count(array_filter($visible, fn ($i) => request()->routeIs(...$i[2]))) > 0,
          'sum' => array_sum(array_map(fn ($i) => (int) $i[3], $visible)),
      ];
  }
@endphp
<style>
  .sb-mode{display:flex;gap:.25rem;margin:.2rem 0 .6rem;padding:.2rem;background:rgba(127,127,127,.15);border-radius:.5rem}
  .sb-mode button{flex:1;border:0;background:transparent;color:inherit;font:inherit;font-size:.85rem;padding:.35rem .4rem;border-radius:.4rem;cursor:pointer}
  .sb-mode button.on{background:#fff;color:#111827;font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,.15)}
</style>
<form method="POST" action="{{ route('nav.mode') }}" class="sb-mode">
  @csrf
  <button type="submit" name="mode" value="alltag" @class(['on' => $mode === 'alltag'])>Alltag</button>
  <button type="submit" name="mode" value="verwaltung" @class(['on' => $mode === 'verwaltung'])>Verwaltung</button>
</form>
@foreach ($menu as $g)
  @if ($g['title'] === '')
    <div class="sb-group sb-top">
      @foreach ($g['items'] as [$label, $route, $active, $count])
        <a href="{{ route($route) }}" @class(['sb-link', 'on' => request()->routeIs(...$active)])>
          <span>{{ $label }}</span>@if ($count > 0)<span class="sb-badge">{{ $count }}</span>@endif
        </a>
      @endforeach
    </div>
  @else
    <details class="sb-group sb-fold" data-g="{{ $g['slug'] }}" @if ($g['on'] || $g['sum'] > 0) open @endif @if ($g['on']) data-on="1" @endif>
      <summary class="sb-title"><span>{{ $g['title'] }}</span>@if ($g['sum'] > 0)<span class="sb-badge sb-sum">{{ $g['sum'] }}</span>@endif</summary>
      @foreach ($g['items'] as [$label, $route, $active, $count])
        <a href="{{ route($route) }}" @class(['sb-link', 'on' => request()->routeIs(...$active)])>
          <span>{{ $label }}</span>@if ($count > 0)<span class="sb-badge">{{ $count }}</span>@endif
        </a>
      @endforeach
    </details>
  @endif
@endforeach
