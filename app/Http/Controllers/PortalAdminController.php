<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Tenant;
use App\Models\TenantPortalUser;
use App\Support\Portal\Portal;
use Illuminate\Http\Request;
use RuntimeException;

/** Mieterportal freigeben, einladen und sperren (Paket 36). */
class PortalAdminController extends Controller
{
    use ScopesOwners;

    public function enable(Request $request, Tenant $tenant)
    {
        $this->assertOwnerAccess((int) $tenant->owner_id);
        $d = $request->validate(['email' => ['required', 'email', 'max:190']], ['email.required' => 'Bitte die E-Mail-Adresse des Mieters eintragen.', 'email.email' => 'Die E-Mail-Adresse ist ungültig.']);
        try {
            $u = Portal::enable($tenant, $d['email'], $this->me());
            if ($request->boolean('invite')) {
                Portal::sendLink($u->load('tenant'), true);

                return back()->with('status', 'Freigegeben. Die Einladung ist per E-Mail unterwegs.');
            }
        } catch (RuntimeException $e) {
            return back()->withErrors(['portal' => $e->getMessage()]);
        }

        return back()->with('status', 'Das Mieterportal ist freigegeben.');
    }

    public function invite(Tenant $tenant)
    {
        $this->assertOwnerAccess((int) $tenant->owner_id);
        $u = TenantPortalUser::query()->where('tenant_id', $tenant->id)->where('enabled', true)->with('tenant')->first();
        if ($u === null) {
            return back()->withErrors(['portal' => 'Das Portal ist für diesen Mieter nicht freigegeben.']);
        }
        try {
            Portal::sendLink($u, true);
        } catch (RuntimeException $e) {
            return back()->withErrors(['portal' => $e->getMessage()]);
        }

        return back()->with('status', 'Die Einladung ist per E-Mail unterwegs.');
    }

    public function disable(Tenant $tenant)
    {
        $this->assertOwnerAccess((int) $tenant->owner_id);
        Portal::disable($tenant);

        return back()->with('status', 'Das Mieterportal ist für diesen Mieter gesperrt.');
    }
}
