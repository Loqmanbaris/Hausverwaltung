<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Owner;
use App\Models\OwnerBankAccount;
use App\Models\User;
use App\Support\Audit;
use App\Support\Stammdaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OwnerController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $owners = $this->visibleOwners()->withCount('properties')->get();

        return view('stammdaten.owners.index', compact('owners'));
    }

    public function show(Owner $owner)
    {
        $this->assertOwnerAccess($owner->id);

        Audit::log('owner.viewed', $owner, 'Eigentümer geöffnet: '.$owner->name);

        $owner->load([
            'bankAccounts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('id'),
            'properties' => fn ($q) => $q->orderBy('name')->withCount('units'),
        ]);
        $tenantCount = $owner->tenants()->count();

        return view('stammdaten.owners.show', compact('owner', 'tenantCount'));
    }

    public function create()
    {
        return view('stammdaten.owners.form', ['owner' => new Owner(['tax_status' => 'regel'])]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $owner = DB::transaction(function () use ($data) {
            $owner = Owner::create($data);
            Audit::log('owner.created', $owner, 'Eigentümer angelegt: '.$owner->name, null, $data);

            return $owner;
        });

        return redirect()->route('owners.show', $owner)->with('status', 'Eigentümer wurde angelegt.');
    }

    public function edit(Owner $owner)
    {
        return view('stammdaten.owners.form', compact('owner'));
    }

    public function update(Request $request, Owner $owner)
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($owner, $data) {
            $keys = array_keys($data);
            $before = $owner->only($keys);
            $owner->update($data);
            [$old, $new] = Audit::diff($before, $owner->only($keys));

            if ($new !== null) {
                Audit::log('owner.updated', $owner, 'Eigentümer geändert: '.$owner->name, $old, $new);
            }
        });

        return redirect()->route('owners.show', $owner)->with('status', 'Änderungen wurden gespeichert.');
    }

    public function destroy(Owner $owner)
    {
        if ($owner->properties()->exists() || $owner->tenants()->exists()) {
            return back()->withErrors(['delete' => 'Dieser Eigentümer hat noch Objekte oder Mieter und kann nicht gelöscht werden.']);
        }

        DB::transaction(function () use ($owner) {
            Audit::log('owner.deleted', $owner, 'Eigentümer gelöscht: '.$owner->name);
            $owner->delete();
        });

        return redirect()->route('owners.index')->with('status', 'Eigentümer wurde gelöscht.');
    }

    public function access(Owner $owner)
    {
        $employees = $this->employees();
        $granted = array_map('intval', $owner->users()->pluck('users.id')->all());

        return view('stammdaten.owners.access', compact('owner', 'employees', 'granted'));
    }

    public function accessUpdate(Request $request, Owner $owner)
    {
        $employees = $this->employees();

        $validated = $request->validate([
            'users' => ['nullable', 'array'],
            'users.*' => ['integer', Rule::in($employees->pluck('id')->all())],
        ], Stammdaten::messages(), Stammdaten::attributes());

        $newIds = array_values(array_unique(array_map('intval', $validated['users'] ?? [])));
        $oldIds = array_map('intval', $owner->users()->pluck('users.id')->all());

        $names = fn (array $ids) => $employees->whereIn('id', $ids)->pluck('name')->values()->all();

        DB::transaction(function () use ($owner, $newIds, $oldIds, $names) {
            $owner->users()->sync($newIds);

            if (array_diff($newIds, $oldIds) || array_diff($oldIds, $newIds)) {
                Audit::log(
                    'owner.access_updated',
                    $owner,
                    'Freigaben für Mitarbeiter geändert bei '.$owner->name,
                    ['mitarbeiter' => $names($oldIds)],
                    ['mitarbeiter' => $names($newIds)]
                );
            }
        });

        return redirect()->route('owners.show', $owner)->with('status', 'Freigaben wurden gespeichert.');
    }

    public function storeAccount(Request $request, Owner $owner)
    {
        $request->merge(['iban' => Stammdaten::normalizeIban($request->input('iban'))]);

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'account_holder' => ['required', 'string', 'max:190'],
            'iban' => ['required', 'string', 'max:34', Stammdaten::ibanRule()],
            'bic' => ['nullable', 'string', 'max:11'],
            'bank_name' => ['nullable', 'string', 'max:190'],
        ], Stammdaten::messages(), Stammdaten::attributes());

        $data['is_primary'] = $request->boolean('is_primary') || ! $owner->bankAccounts()->exists();

        DB::transaction(function () use ($owner, $data) {
            if ($data['is_primary']) {
                $owner->bankAccounts()->update(['is_primary' => false]);
            }
            $account = $owner->bankAccounts()->create($data);
            Audit::log(
                'bank_account.created',
                $account,
                'Bankverbindung hinzugefügt bei '.$owner->name.' (IBAN endet auf '.substr($data['iban'], -4).')',
                null,
                $data
            );
        });

        return redirect()->route('owners.show', $owner)->with('status', 'Bankverbindung wurde gespeichert.');
    }

    public function destroyAccount(Owner $owner, OwnerBankAccount $account)
    {
        abort_unless((int) $account->owner_id === (int) $owner->id, 404);

        DB::transaction(function () use ($owner, $account) {
            Audit::log(
                'bank_account.deleted',
                $account,
                'Bankverbindung entfernt bei '.$owner->name.' (IBAN endet auf '.substr((string) $account->iban, -4).')'
            );
            $wasPrimary = $account->is_primary;
            $account->delete();

            if ($wasPrimary) {
                $next = $owner->bankAccounts()->orderBy('id')->first();
                $next?->update(['is_primary' => true]);
            }
        });

        return redirect()->route('owners.show', $owner)->with('status', 'Bankverbindung wurde entfernt.');
    }

    private function employees()
    {
        return User::orderBy('name')->get()->reject(fn (User $u) => $u->isAdmin())->values();
    }

    private function validated(Request $request): array
    {
        $request->merge([
            'vat_id' => Stammdaten::normalizeCode($request->input('vat_id')),
            'creditor_id' => Stammdaten::normalizeCode($request->input('creditor_id')),
        ]);

        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'street' => ['nullable', 'string', 'max:190'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:120'],
            'tax_number' => ['nullable', 'string', 'max:40'],
            'vat_id' => ['nullable', 'string', 'regex:/^[A-Z]{2}[A-Z0-9]{2,12}$/'],
            'tax_status' => ['required', Rule::in(array_keys(Stammdaten::TAX_STATUS))],
            'creditor_id' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], Stammdaten::messages(), Stammdaten::attributes());
    }
}
