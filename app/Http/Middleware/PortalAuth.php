<?php

namespace App\Http\Middleware;

use App\Models\TenantPortalUser;
use App\Support\Portal\Portal;
use Closure;
use Illuminate\Http\Request;

/** Schützt die Seiten des Mieterportals: nur mit gültiger Portal-Sitzung (Paket 36). */
class PortalAuth
{
    public function handle(Request $request, Closure $next)
    {
        $id = (int) $request->session()->get('portal_user_id');
        $seen = (int) $request->session()->get('portal_seen');
        if ($id === 0 || $seen < time() - Portal::SESSION_MINUTES * 60) {
            $request->session()->forget(['portal_user_id', 'portal_seen']);

            return redirect()->route('portal.login')->with('portal_info', 'Bitte melden Sie sich an.');
        }
        $u = TenantPortalUser::query()->whereKey($id)->where('enabled', true)->with('tenant')->first();
        if ($u === null || $u->tenant === null) {
            $request->session()->forget(['portal_user_id', 'portal_seen']);

            return redirect()->route('portal.login');
        }
        $request->session()->put('portal_seen', time());
        $request->attributes->set('portal_user', $u);

        return $next($request);
    }
}
