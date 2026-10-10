<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Property;
use App\Support\Audit;
use App\Support\Stammdaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PropertyController extends Controller
{
    use ScopesOwners;

    public function index()
    {
        $properties = Property::visibleTo($this->me())
            ->with('owner')
            ->withCount('units')
            ->orderBy('name')
            ->get()
            ->groupBy(fn (Property $p) => $p->owner->name);

        return view('stammdaten.properties.index', compact('properties'));
    }

    public function show(Property $property)
    {
        $this->assertOwnerAccess($property->owner_id);

        $property->load(['owner', 'units' => fn ($q) => $q->orderBy('label')]);

        return view('stammdaten.properties.show', compact('property'));
    }

    public function create(Request $request)
    {
        $owners = $this->visibleOwners()->get();
        $property = new Property(['owner_id' => (int) $request->query('eigentuemer')]);

        return view('stammdaten.properties.form', compact('property', 'owners'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(true), Stammdaten::messages(), Stammdaten::attributes());

        $property = DB::transaction(function () use ($data) {
            $property = Property::create($data);
            Audit::log('property.created', $property, 'Objekt angelegt: '.$property->name, null, $data);

            return $property;
        });

        return redirect()->route('properties.show', $property)->with('status', 'Objekt wurde angelegt.');
    }

    public function edit(Property $property)
    {
        $this->assertOwnerAccess($property->owner_id);
        $property->load('owner');
        $owners = collect();

        return view('stammdaten.properties.form', compact('property', 'owners'));
    }

    public function update(Request $request, Property $property)
    {
        $this->assertOwnerAccess($property->owner_id);
        $data = $request->validate($this->rules(false), Stammdaten::messages(), Stammdaten::attributes());

        DB::transaction(function () use ($property, $data) {
            $keys = array_keys($data);
            $before = $property->only($keys);
            $property->update($data);
            [$old, $new] = Audit::diff($before, $property->only($keys));

            if ($new !== null) {
                Audit::log('property.updated', $property, 'Objekt geändert: '.$property->name, $old, $new);
            }
        });

        return redirect()->route('properties.show', $property)->with('status', 'Änderungen wurden gespeichert.');
    }

    public function destroy(Property $property)
    {
        if ($property->units()->exists()) {
            return back()->withErrors(['delete' => 'Dieses Objekt hat noch Einheiten und kann nicht gelöscht werden.']);
        }

        DB::transaction(function () use ($property) {
            Audit::log('property.deleted', $property, 'Objekt gelöscht: '.$property->name);
            $property->delete();
        });

        return redirect()->route('properties.index')->with('status', 'Objekt wurde gelöscht.');
    }

    private function rules(bool $withOwner): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:190'],
            'street' => ['nullable', 'string', 'max:190'],
            'zip' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:120'],
            'heating_type' => ['nullable', Rule::in(array_keys(Stammdaten::HEATING))],
            'hot_water_type' => ['nullable', Rule::in(array_keys(Stammdaten::HOT_WATER))],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];

        if ($withOwner) {
            $rules['owner_id'] = ['required', 'integer', Rule::in($this->visibleOwnerIds())];
        }

        return $rules;
    }
}
