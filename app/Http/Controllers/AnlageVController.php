<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Owner;
use App\Support\Audit;
use App\Support\Tax\AnlageV;
use Illuminate\Http\Request;

/** Zusammenstellung für die Anlage V je Eigentümer und Jahr (Ansicht, Druck, CSV). */
class AnlageVController extends Controller
{
    use ScopesOwners;

    public function index(Request $request)
    {
        [$owners, $owner, $year] = $this->params($request);
        $data = $owner ? AnlageV::build((int) $owner->id, $year) : null;

        return view('anlage-v.index', compact('owners', 'owner', 'year', 'data'));
    }

    public function print(Request $request)
    {
        [, $owner, $year] = $this->params($request);
        abort_if($owner === null, 404);

        return view('anlage-v.print', ['owner' => $owner, 'year' => $year, 'data' => AnlageV::build((int) $owner->id, $year)]);
    }

    public function csv(Request $request)
    {
        [, $owner, $year] = $this->params($request);
        abort_if($owner === null, 404);
        $csv = AnlageV::csv(AnlageV::build((int) $owner->id, $year), $owner->name, $year);
        Audit::log('anlage_v.exported', $owner, 'Anlage V '.$year.' als CSV heruntergeladen: '.$owner->name);
        $name = 'Anlage-V_'.$year.'_'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $owner->name).'.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: ?Owner, 2: int} */
    private function params(Request $request): array
    {
        $owners = $this->visibleOwners()->get();
        $ownerId = (int) $request->query('eigentuemer');
        $owner = $ownerId ? $owners->firstWhere('id', $ownerId) : ($owners->count() === 1 ? $owners->first() : null);
        $year = (int) $request->query('jahr', (int) date('Y') - 1);
        if ($year < 2000 || $year > (int) date('Y')) {
            $year = (int) date('Y') - 1;
        }

        return [$owners, $owner, $year];
    }
}
