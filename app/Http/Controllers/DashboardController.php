<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\AuditLog;
use App\Models\Owner;
use App\Models\Property;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Backup\BackupService;
use App\Support\Settings;
use App\Support\Stammdaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Startseite nach dem Login. Zeigt nur Daten der Eigentümer, die der Benutzer sehen darf.
 */
class DashboardController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $user = $this->me();
        $isAdmin = $user->isAdmin();

        $owners = $this->visibleOwners()->get(['owners.id', 'owners.name']);
        $selected = $owners->firstWhere('id', (int) $request->query('eigentuemer'));
        $ids = $selected
            ? [(int) $selected->id]
            : $owners->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rows = [];
        $usage = [];
        $totals = ['properties' => 0, 'units' => 0, 'area' => 0.0, 'tenants' => 0];
        $checks = [];
        $recent = collect();
        $blocks = \App\Support\Dashboard\Layout::visible($user);
        $wants = fn (string $k) => in_array($k, array_column($blocks, 'key'), true);

        if ($ids !== []) {
            [$rows, $usage, $totals] = $this->overview($owners, $ids);
            $checks = $this->checks($ids, $isAdmin);
        }

        if ($isAdmin && $wants('recent')) {
            $recent = AuditLog::query()
                ->where('event', 'not like', 'login.%')
                ->where('event', 'not like', 'two_factor.%')
                ->where('event', 'not like', 'password.%')
                ->whereNotIn('event', ['logout', 'owner.viewed'])
                ->orderByDesc('id')
                ->limit(8)
                ->get();
        }

        return view('dashboard', [
            'owners' => $owners,
            'selected' => $selected,
            'hasOwners' => $ids !== [],
            'rows' => $rows,
            'usage' => $usage,
            'totals' => $totals,
            'checks' => $checks,
            'recent' => $recent,
            'backupAttention' => $isAdmin ? BackupService::attention() : null,
            'imprintIncomplete' => $isAdmin && ! Settings::imprintComplete(),
            'cockpit' => $ids !== [] ? \App\Support\Dashboard\Cockpit::build($user, $selected ? (int) $selected->id : null, null, array_column($blocks, 'key')) : null,
            'blocks' => $blocks,
            'quick' => \App\Support\Dashboard\Layout::quick($user),
            'note' => \App\Support\Dashboard\Layout::note($user),
        ]);
    }

    /** Zahlen pro Eigentümer, pro Nutzungsart und gesamt. */
    private function overview($owners, array $ids): array
    {
        $propertyCounts = DB::table('properties')
            ->whereNull('deleted_at')
            ->whereIn('owner_id', $ids)
            ->selectRaw('owner_id, count(*) as c')
            ->groupBy('owner_id')
            ->pluck('c', 'owner_id')
            ->all();

        $tenantCounts = DB::table('tenants')
            ->whereNull('deleted_at')
            ->whereIn('owner_id', $ids)
            ->selectRaw('owner_id, count(*) as c')
            ->groupBy('owner_id')
            ->pluck('c', 'owner_id')
            ->all();

        $unitRows = DB::table('units')
            ->join('properties', 'properties.id', '=', 'units.property_id')
            ->whereNull('units.deleted_at')
            ->whereNull('properties.deleted_at')
            ->whereIn('properties.owner_id', $ids)
            ->selectRaw('properties.owner_id as owner_id, units.usage_type as usage_type, count(units.id) as c, coalesce(sum(units.area_sqm), 0) as a')
            ->groupBy('properties.owner_id', 'units.usage_type')
            ->get();

        $rows = [];
        foreach ($owners as $owner) {
            if (! in_array((int) $owner->id, $ids, true)) {
                continue;
            }
            $rows[(int) $owner->id] = [
                'owner' => $owner,
                'properties' => (int) ($propertyCounts[$owner->id] ?? 0),
                'units' => 0,
                'area' => 0.0,
                'tenants' => (int) ($tenantCounts[$owner->id] ?? 0),
            ];
        }

        $byUsage = [];
        foreach ($unitRows as $row) {
            $ownerId = (int) $row->owner_id;
            if (isset($rows[$ownerId])) {
                $rows[$ownerId]['units'] += (int) $row->c;
                $rows[$ownerId]['area'] += (float) $row->a;
            }
            $byUsage[$row->usage_type]['units'] = ($byUsage[$row->usage_type]['units'] ?? 0) + (int) $row->c;
            $byUsage[$row->usage_type]['area'] = ($byUsage[$row->usage_type]['area'] ?? 0.0) + (float) $row->a;
        }

        // Nutzungsarten in der gewohnten Reihenfolge, nur vorhandene
        $usage = [];
        foreach (Stammdaten::USAGE as $key => $label) {
            if (isset($byUsage[$key])) {
                $usage[] = ['label' => $label] + $byUsage[$key];
            }
        }

        $totals = [
            'properties' => array_sum(array_column($rows, 'properties')),
            'units' => array_sum(array_column($rows, 'units')),
            'area' => array_sum(array_column($rows, 'area')),
            'tenants' => array_sum(array_column($rows, 'tenants')),
        ];

        return [array_values($rows), $usage, $totals];
    }

    /**
     * Hinweise auf fehlende Angaben. Eigentümer-Angaben nur für den Administrator,
     * weil nur er sie ergänzen kann.
     */
    private function checks(array $ids, bool $isAdmin): array
    {
        $propertyIds = fn () => Property::query()->whereIn('owner_id', $ids)->select('properties.id');
        $list = [];

        if ($isAdmin) {
            $list[] = $this->check(
                'Eigentümer ohne Bankverbindung',
                'Wird später für Rechnungen und Lastschriften gebraucht.',
                Owner::query()->whereIn('owners.id', $ids)->whereDoesntHave('bankAccounts'),
                'owners.name',
                fn ($o) => [$o->name, route('owners.show', $o)]
            );

            $list[] = $this->check(
                'Eigentümer ohne Steuernummer',
                'Wird später für Rechnungen und die Anlage V gebraucht.',
                Owner::query()->whereIn('owners.id', $ids)->whereNull('owners.tax_number'),
                'owners.name',
                fn ($o) => [$o->name, route('owners.edit', $o)]
            );
        }

        $list[] = $this->check(
            'Objekte ohne Heizart',
            'Wichtig für die spätere Nebenkostenabrechnung.',
            Property::query()->whereIn('properties.owner_id', $ids)->where(function ($w) {
                $w->whereNull('properties.heating_type')->orWhere('properties.heating_type', '');
            }),
            'properties.name',
            fn ($p) => [$p->name, route('properties.edit', $p)]
        );

        $list[] = $this->check(
            'Objekte ohne Einheiten',
            'Ein Objekt braucht mindestens eine Wohnung oder Gewerbefläche.',
            Property::query()->whereIn('properties.owner_id', $ids)->doesntHave('units'),
            'properties.name',
            fn ($p) => [$p->name, route('properties.show', $p)]
        );

        $list[] = $this->check(
            'Einheiten ohne Fläche',
            'Die Fläche wird für Umlageschlüssel und Auswertungen gebraucht.',
            Unit::query()->whereIn('units.property_id', $propertyIds())->where(function ($w) {
                $w->whereNull('units.area_sqm')->orWhere('units.area_sqm', 0);
            })->with('property'),
            'units.label',
            fn ($u) => [$u->label.' ('.($u->property?->name ?? '–').')', route('units.edit', $u)]
        );

        $list[] = $this->check(
            'Mieter ohne E-Mail-Adresse',
            'Verträge, Rechnungen und Abrechnungen gehen per E-Mail raus. Eine Adresse bei einem Ansprechpartner genügt.',
            Tenant::query()->whereIn('tenants.owner_id', $ids)->where(function ($w) {
                $w->whereNull('tenants.email')->orWhere('tenants.email', '');
            })->whereDoesntHave('contacts', function ($c) {
                $c->whereNotNull('tenant_contacts.email')->where('tenant_contacts.email', '!=', '');
            }),
            'tenants.display_name',
            fn ($t) => [$t->display_name, route('tenants.edit', $t)]
        );

        $list[] = $this->check(
            'Firmen ohne Ansprechpartner',
            'Bei Firmen gehen Schreiben an die hinterlegten Ansprechpartner.',
            Tenant::query()->whereIn('tenants.owner_id', $ids)->where('tenants.type', 'firma')->doesntHave('contacts'),
            'tenants.display_name',
            fn ($t) => [$t->display_name, route('tenants.show', $t)]
        );

        return array_values(array_filter($list));
    }

    /** Erst zählen, dann die ersten fünf Treffer laden (Sortierung nur für die Liste). */
    private function check(string $title, string $hint, $query, string $orderBy, callable $item): ?array
    {
        $count = (clone $query)->count();

        if ($count === 0) {
            return null;
        }

        $items = $query->orderBy($orderBy)->limit(5)->get()->map(function ($model) use ($item) {
            [$label, $url] = $item($model);

            return ['label' => $label, 'url' => $url];
        })->all();

        return ['title' => $title, 'hint' => $hint, 'count' => $count, 'items' => $items];
    }
}
