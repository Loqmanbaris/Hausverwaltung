<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Models\Vendor;
use App\Support\Audit;
use App\Support\Kostenarten;
use App\Support\Stammdaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Lieferanten und Rechnungsteller. Die Liste gilt für alle Eigentümer, Belege und Summen
 * sehen Mitarbeiter aber nur für die Eigentümer, die für sie freigegeben sind.
 */
class VendorController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $all = $request->boolean('alle');

        $query = Vendor::query()
            ->withCount(['receipts as receipts_count' => fn ($r) => $r->visibleTo($user)])
            ->orderBy('name');

        if (! $all) {
            $query->where('active', true);
        }
        if ($q !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('city', 'like', $like)->orWhere('email', 'like', $like);
            });
        }

        return view('vendors.index', [
            'vendors' => $query->simplePaginate(30)->withQueryString(),
            'q' => $q,
            'all' => $all,
        ]);
    }

    public function create()
    {
        return view('vendors.form', ['vendor' => new Vendor(['active' => true])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);

        $vendor = DB::transaction(function () use ($data) {
            $vendor = Vendor::create($data);
            Audit::log('vendor.created', $vendor, 'Lieferant angelegt: '.$vendor->name, null, $data);

            return $vendor;
        });

        return redirect()->route('vendors.show', $vendor)->with('status', 'Lieferant wurde angelegt.');
    }

    public function show(Request $request, Vendor $vendor)
    {
        $user = $request->user();
        $base = Receipt::query()->visibleTo($user)->where('vendor_id', $vendor->id);

        return view('vendors.show', [
            'vendor' => $vendor,
            'count' => (clone $base)->count(),
            'sum' => (clone $base)->sum('amount_gross'),
            'receipts' => (clone $base)->with(['owner', 'property'])->orderByDesc('id')->limit(20)->get(),
        ]);
    }

    public function edit(Vendor $vendor)
    {
        return view('vendors.form', compact('vendor'));
    }

    public function update(Request $request, Vendor $vendor)
    {
        $data = $this->validated($request, $vendor);

        DB::transaction(function () use ($vendor, $data) {
            $keys = array_keys($data);
            $before = $vendor->only($keys);
            $vendor->update($data);
            [$old, $new] = Audit::diff($before, $vendor->only($keys));

            if ($new !== null) {
                Audit::log('vendor.updated', $vendor, 'Lieferant geändert: '.$vendor->name, $old, $new);
            }
        });

        return redirect()->route('vendors.show', $vendor)->with('status', 'Änderungen wurden gespeichert.');
    }

    /** Lieferant stilllegen oder wieder aktivieren (nur Administrator). Gelöscht wird nichts. */
    public function toggle(Vendor $vendor)
    {
        $vendor->update(['active' => ! $vendor->active]);
        Audit::log('vendor.updated', $vendor, 'Lieferant '.($vendor->active ? 'aktiviert' : 'stillgelegt').': '.$vendor->name);

        return redirect()->route('vendors.show', $vendor)->with('status', $vendor->active ? 'Lieferant ist wieder aktiv.' : 'Lieferant wurde stillgelegt. Er erscheint nicht mehr in der Auswahl, alte Belege bleiben unverändert.');
    }

    private function validated(Request $request, ?Vendor $vendor): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'street' => ['nullable', 'string', 'max:190'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'vat_id' => ['nullable', 'string', 'max:20'],
            'default_cost_category' => ['nullable', Rule::in(Kostenarten::allowed($vendor?->default_cost_category))],
            'note' => ['nullable', 'string', 'max:5000'],
        ], Stammdaten::messages(), Stammdaten::attributes() + [
            'default_cost_category' => 'Übliche Kostenart',
            'vat_id' => 'USt-ID',
        ]);

        $data['name'] = trim((string) preg_replace('/\s+/u', ' ', $data['name']));
        $data['name_key'] = Vendor::normalizeName($data['name']);

        $taken = Vendor::query()
            ->where('name_key', $data['name_key'])
            ->when($vendor, fn ($q) => $q->where('id', '!=', $vendor->id))
            ->first();
        if ($taken !== null) {
            throw ValidationException::withMessages(['name' => 'Einen Lieferanten mit diesem Namen gibt es schon.']);
        }

        foreach (['street', 'zip', 'city', 'email', 'phone', 'vat_id', 'default_cost_category', 'note'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $data[$field] = $value === '' ? null : $value;
        }

        return $data;
    }
}
