<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Tenant;
use App\Support\Audit;
use App\Support\Datenschutz\Loeschfristen;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Löschfristen für ehemalige Mieter, nur für den Administrator (Paket 35). */
class RetentionController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $user = $this->me();

        return view('retention.index', [
            'months' => Loeschfristen::months(), 'years' => Loeschfristen::years(), 'due' => Loeschfristen::due($user),
            'held' => Tenant::query()->where('retention_hold', true)->orderBy('display_name')->get(),
        ]);
    }

    public function settings(Request $request)
    {
        $d = $request->validate(['months' => ['required', 'integer', 'between:1,120'], 'years' => ['required', 'integer', 'between:1,30']]);
        if ((int) $d['years'] * 12 < (int) $d['months']) {
            return back()->withErrors(['years' => 'Die zweite Frist muss länger sein als die erste.'])->withInput();
        }
        Loeschfristen::saveSettings((int) $d['months'], (int) $d['years']);
        Audit::log('retention.settings', 'Löschfristen geändert: '.$d['months'].' Monate und '.$d['years'].' Jahre');

        return back()->with('status', 'Die Fristen sind gespeichert.');
    }

    public function clean(Request $request, Tenant $tenant)
    {
        $d = $request->validate(['stage' => ['required', Rule::in(['1', '2'])]]);
        try {
            $done = Loeschfristen::clean($tenant, (int) $d['stage'], $this->me());
        } catch (RuntimeException $e) {
            return back()->withErrors(['retention' => $e->getMessage()]);
        }

        return back()->with('status', 'Bereinigt: '.$done['docs'].' Dokument(e), '.$done['contacts'].' Kontakt(e) und '.$done['emails'].' E-Mail-Inhalt(e) gelöscht.');
    }

    public function hold(Request $request, Tenant $tenant)
    {
        $d = $request->validate(['hold' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:190']]);
        Loeschfristen::hold($tenant, (bool) $d['hold'], $d['reason'] ?? null, $this->me());

        return back()->with('status', $d['hold'] ? 'Der Mieter wird von der Löschung ausgenommen.' : 'Die Markierung ist entfernt.');
    }
}
