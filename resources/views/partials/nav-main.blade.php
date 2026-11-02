@php
  $u = auth()->user();
  $admin = $u?->isAdmin();
  $cnt = function (callable $f) { try { return (int) $f(); } catch (\Throwable) { return 0; } };
  $badge = [
      'bank' => $cnt(fn () => \App\Support\Bank\BankService::openCountFor($u)),
      'expenses' => $cnt(fn () => \App\Support\Bank\ExpenseMatcher::openCountFor($u)),
      'orders' => $cnt(fn () => \App\Models\WorkOrder::visibleTo($u)->where('status', 'gemeldet')->count()),
      'maint' => $cnt(fn () => \App\Models\MaintenanceTask::visibleTo($u)->where('active', true)->where('next_due', '<', date('Y-m-d'))->count()),
      'heat' => $cnt(fn () => \App\Support\Nk\Verbrauchsinfo::open($u)->count() + \App\Models\Co2Claim::visibleTo($u)->where('status', 'offen')->count()),
      'day' => $cnt(fn () => \App\Support\Tag\MeinTag::openCount($u)),
      'mail' => $cnt(fn () => \App\Models\ReceivedEmail::unreadCountFor($u)),
  ];
  // [Bezeichnung, Route, aktiv bei, Badge, nur Admin]
  $groups = [
      '' => [
          ['Mein Tag', 'tag.index', ['tag.*'], $badge['day'] ?? 0, false],
          ['Suche', 'search', ['search'], 0, false],
          ['E-Mails', 'inbox.index', ['inbox.*', 'mail.index', 'mail.compose', 'mail.write'], $badge['mail'] ?? 0, false],
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
      // Gruppen „Stammdaten“, „Webseite“ und „Verwaltung“ stehen seit 02.11.2026 als Kacheln unter Profilmenü → Einstellungen
  ];
  // Ein Menü für alle: Die frühere Trennung „Alltag“/„Verwaltung“ gibt es seit 02.11.2026 nicht mehr, es steht immer alles da.
  $menu = [];
  foreach ($groups as $title => $items) {
      $visible = array_values(array_filter($items, fn ($i) => (! $i[4] || $admin) && \Illuminate\Support\Facades\Route::has($i[1])));
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
  // Symbole vor den Menüpunkten (wie im Portal), Linien-Symbole im Stil von Heroicons
  $icPaths = [
      'tag.index' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
      'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
      'inbox.index' => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
      'owners.index' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
      'properties.index' => '<path d="M3 21h18M5 21V7l7-4 7 4v14"/><path d="M9 21v-6h6v6M9 10h.01M15 10h.01"/>',
      'tenants.index' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
      'documents.index' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
      'leases.index' => '<path d="M9 12h6M9 16h6M9 8h2"/><rect x="4" y="2" width="16" height="20" rx="2"/>',
      'applicants.index' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
      'rent_increase.index' => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
      'deposit.index' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
      'rent.month' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 15h3"/>',
      'rent.open' => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
      'dunning.index' => '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
      'bank.transactions' => '<path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/>',
      'invoices.index' => '<path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 1 .7V2l-1 .7-3-2-3 2-3-2-3 2-3-2z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
      'erechnung.index' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="M7 15h0M2 9.5h20"/>',
      'receipts.index' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M10 13l2 2 4-4"/>',
      'bank.expenses' => '<path d="M16 3h5v5M21 3l-7 7M8 21H3v-5M3 21l7-7"/>',
      'recurring.index' => '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.9-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"/>',
      'svc.index' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
      'vendors.index' => '<rect x="1" y="3" width="15" height="13"/><path d="M16 8h4l3 3v5h-7V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>',
      'nk.index' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h0M12 10h0M16 10h0M8 14h0M12 14h0M16 14h0M8 18h0M12 18h0M16 18h0"/>',
      'consumption.index' => '<path d="M12 2.7l5.7 5.6a8 8 0 1 1-11.4 0z"/>',
      'anlage-v.index' => '<path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
      'report.index' => '<path d="M18 20V10M12 20V4M6 20v-6"/>',
      'finance.index' => '<path d="M12 1v22M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
      'datev.index' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
      'workorders.index' => '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.8-3.8a6 6 0 0 1-7.9 7.9l-6.9 6.9a2.1 2.1 0 0 1-3-3l6.9-6.9a6 6 0 0 1 7.9-7.9z"/>',
      'maintenance.index' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>',
      'listings.index' => '<path d="M20.6 13.4l-7.2 7.2a2 2 0 0 1-2.8 0L2 12V2h10l8.6 8.6a2 2 0 0 1 0 2.8z"/><path d="M7 7h.01"/>',
      'inquiries.index' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
      'site.edit' => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/>',
  ];
  $ico = fn (string $route) => '<span class="sb-ic" aria-hidden="true"><svg viewBox="0 0 24 24">'.($icPaths[$route] ?? '<circle cx="12" cy="12" r="3"/>').'</svg></span>';
@endphp
@foreach ($menu as $g)
  @if ($g['title'] === '')
    <div class="sb-group sb-top">
      @foreach ($g['items'] as [$label, $route, $active, $count])
        <a href="{{ route($route) }}" @class(['sb-link', 'on' => request()->routeIs(...$active)])>
          {!! $ico($route) !!}<span>{{ $label }}</span>@if ($count > 0)<span class="sb-badge">{{ $count }}</span>@endif
        </a>
      @endforeach
    </div>
  @else
    <details class="sb-group sb-fold" data-g="{{ $g['slug'] }}" @if ($g['on'] || $g['sum'] > 0) open @endif @if ($g['on']) data-on="1" @endif>
      <summary class="sb-title"><span>{{ $g['title'] }}</span>@if ($g['sum'] > 0)<span class="sb-badge sb-sum">{{ $g['sum'] }}</span>@endif</summary>
      @foreach ($g['items'] as [$label, $route, $active, $count])
        <a href="{{ route($route) }}" @class(['sb-link', 'on' => request()->routeIs(...$active)])>
          {!! $ico($route) !!}<span>{{ $label }}</span>@if ($count > 0)<span class="sb-badge">{{ $count }}</span>@endif
        </a>
      @endforeach
    </details>
  @endif
@endforeach
