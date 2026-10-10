<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Tenant;
use App\Models\TenantContact;
use App\Support\Audit;
use App\Support\Stammdaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TenantController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q'));
        $ownerId = (int) $request->query('eigentuemer');

        $tenants = Tenant::visibleTo($this->me())
            ->with('owner')
            ->when($search !== '', fn ($q) => $q->where('display_name', 'like', '%'.$search.'%'))
            ->when($ownerId > 0, fn ($q) => $q->where('tenants.owner_id', $ownerId))
            ->orderBy('display_name')
            ->get();

        $owners = $this->visibleOwners()->get();

        return view('stammdaten.tenants.index', compact('tenants', 'owners', 'search', 'ownerId'));
    }

    public function show(Tenant $tenant)
    {
        $this->assertOwnerAccess($tenant->owner_id);
        $tenant->load(['owner', 'contacts' => fn ($q) => $q->orderBy('name')]);

        return view('stammdaten.tenants.show', compact('tenant'));
    }

    public function create(Request $request)
    {
        $owners = $this->visibleOwners()->get();
        $tenant = new Tenant(['type' => 'person', 'owner_id' => (int) $request->query('eigentuemer')]);

        return view('stammdaten.tenants.form', compact('tenant', 'owners'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $data['display_name'] = Tenant::composeName($data);

        $tenant = DB::transaction(function () use ($data) {
            $tenant = Tenant::create($data);
            Audit::log('tenant.created', $tenant, 'Mieter angelegt: '.$tenant->display_name, null, $data);

            return $tenant;
        });

        return redirect()->route('tenants.show', $tenant)->with('status', 'Mieter wurde angelegt.');
    }

    public function edit(Tenant $tenant)
    {
        $this->assertOwnerAccess($tenant->owner_id);
        $tenant->load('owner');
        $owners = collect();

        return view('stammdaten.tenants.form', compact('tenant', 'owners'));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $this->assertOwnerAccess($tenant->owner_id);
        $data = $this->validated($request, false);
        $data['display_name'] = Tenant::composeName($data);

        DB::transaction(function () use ($tenant, $data) {
            $keys = array_keys($data);
            $before = $tenant->only($keys);
            $tenant->update($data);
            [$old, $new] = Audit::diff($before, $tenant->only($keys));

            if ($new !== null) {
                Audit::log('tenant.updated', $tenant, 'Mieter geändert: '.$tenant->display_name, $old, $new);
            }
        });

        return redirect()->route('tenants.show', $tenant)->with('status', 'Änderungen wurden gespeichert.');
    }

    public function destroy(Tenant $tenant)
    {
        DB::transaction(function () use ($tenant) {
            Audit::log('tenant.deleted', $tenant, 'Mieter gelöscht: '.$tenant->display_name);
            $tenant->contacts()->delete();
            $tenant->delete();
        });

        return redirect()->route('tenants.index')->with('status', 'Mieter wurde gelöscht.');
    }

    public function storeContact(Request $request, Tenant $tenant)
    {
        $this->assertOwnerAccess($tenant->owner_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'role' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
        ], Stammdaten::messages(), Stammdaten::attributes());
        $data['receives_invoices'] = $request->boolean('receives_invoices');

        DB::transaction(function () use ($tenant, $data) {
            $contact = $tenant->contacts()->create($data);
            Audit::log('tenant_contact.created', $contact, 'Ansprechpartner hinzugefügt bei '.$tenant->display_name.': '.$contact->name, null, $data);
        });

        return redirect()->route('tenants.show', $tenant)->with('status', 'Ansprechpartner wurde gespeichert.');
    }

    public function destroyContact(Tenant $tenant, TenantContact $contact)
    {
        abort_unless((int) $contact->tenant_id === (int) $tenant->id, 404);

        DB::transaction(function () use ($tenant, $contact) {
            Audit::log('tenant_contact.deleted', $contact, 'Ansprechpartner entfernt bei '.$tenant->display_name.': '.$contact->name);
            $contact->delete();
        });

        return redirect()->route('tenants.show', $tenant)->with('status', 'Ansprechpartner wurde entfernt.');
    }

    private function validated(Request $request, bool $withOwner): array
    {
        $request->merge(['vat_id' => Stammdaten::normalizeCode($request->input('vat_id'))]);

        $rules = [
            'type' => ['required', Rule::in(array_keys(Stammdaten::TENANT_TYPE))],
            'company_name' => ['required_if:type,firma', 'nullable', 'string', 'max:190'],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required_if:type,person', 'nullable', 'string', 'max:100'],
            'vat_id' => ['nullable', 'string', 'regex:/^[A-Z]{2}[A-Z0-9]{2,12}$/'],
            'street' => ['nullable', 'string', 'max:190'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($withOwner) {
            $rules['owner_id'] = ['required', 'integer', Rule::in($this->visibleOwnerIds())];
        }

        return $request->validate($rules, Stammdaten::messages(), Stammdaten::attributes());
    }
}
