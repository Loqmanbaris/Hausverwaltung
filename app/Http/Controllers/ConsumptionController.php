<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\ConsumptionNotice;
use App\Models\Meter;
use App\Models\Property;
use App\Support\Audit;
use App\Support\Nk\Verbrauchsinfo;
use DateTimeImmutable;
use Illuminate\Http\Request;

/** Monatliche Verbrauchsinformation für Mieter (§ 6a Heizkostenverordnung, Paket 26). */
class ConsumptionController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $props = Verbrauchsinfo::properties($this->me());
        $month = Verbrauchsinfo::previousMonth();
        $notices = ConsumptionNotice::query()->whereIn('property_id', $props->pluck('id'))->orderByDesc('month')->get()->groupBy('property_id');

        // Übersicht Zähler: fernablesbar und Eichfrist über alle sichtbaren Objekte
        $meters = Meter::query()->whereIn('property_id', Property::visibleTo($this->me())->select('properties.id'))->where('active', true)
            ->with(['unit', 'property'])->get();
        $heat = $meters->filter(fn ($m) => $m->isHeat());

        return view('nk.verbrauch-index', [
            'props' => $props,
            'month' => $month,
            'notices' => $notices,
            'heatTotal' => $heat->count(),
            'notRemote' => $heat->where('remote', false)->sortBy(fn ($m) => [$m->property?->name, $m->unit?->label])->values(),
            'expired' => $meters->filter(fn ($m) => $m->calibrationExpired() || ($m->calibrated_until !== null && (int) $m->calibrated_until === (int) date('Y')))->sortBy('calibrated_until')->values(),
        ]);
    }

    public function show(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $month = $this->month($request);
        $info = Verbrauchsinfo::build($property, $month);
        $done = ConsumptionNotice::query()->where('property_id', $property->id)->where('month', $month.'-01')->orderByDesc('id')->first();

        return view('nk.verbrauch', compact('property', 'info', 'month', 'done'));
    }

    public function print(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $month = $this->month($request);
        $info = Verbrauchsinfo::build($property, $month);
        $only = $request->integer('vertrag') ?: null;
        $rows = array_filter($info['rows'], fn ($r) => $r['lease'] !== null && ($only === null || (int) $r['lease']->id === $only));
        abort_if($only !== null && $rows === [], 404);
        $property->load('owner');

        return view('nk.verbrauch-print', ['property' => $property, 'info' => $info, 'rows' => $rows]);
    }

    /** Als erledigt markieren (z. B. ausgedruckt und verteilt oder im Portal des Messdienstes bereitgestellt). */
    public function done(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $month = $this->month($request);
        ConsumptionNotice::create(['property_id' => $property->id, 'month' => $month.'-01', 'method' => 'erledigt', 'count' => 0, 'created_by' => $this->me()->id]);
        Audit::log('consumption.done', $property, 'Verbrauchsinformation '.Verbrauchsinfo::monthLabel($month).' als erledigt markiert: '.$property->name);

        return redirect()->route('consumption.show', ['property' => $property, 'monat' => $month])->with('status', 'Als erledigt markiert.');
    }

    private function month(Request $request): string
    {
        $m = (string) $request->input('monat', Verbrauchsinfo::previousMonth());
        $d = DateTimeImmutable::createFromFormat('!Y-m', $m);
        if ($d === false || $d->format('Y-m') !== $m || $m > date('Y-m')) {
            return Verbrauchsinfo::previousMonth();
        }

        return $m;
    }
}
