<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Property;
use App\Models\Unit;
use App\Support\Audit;
use App\Support\Stammdaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UnitController extends Controller
{
    use ScopesOwners;

    public function create(Property $property)
    {
        $this->assertOwnerAccess($property->owner_id);
        $unit = new Unit(['usage_type' => 'wohnen']);

        return view('stammdaten.units.form', compact('property', 'unit'));
    }

    public function store(Request $request, Property $property)
    {
        $this->assertOwnerAccess($property->owner_id);
        $data = $this->validated($request);

        DB::transaction(function () use ($property, $data) {
            $unit = $property->units()->create($data);
            Audit::log('unit.created', $unit, 'Einheit angelegt: '.$unit->label.' in '.$property->name, null, $data);
        });

        return redirect()->route('properties.show', $property)->with('status', 'Einheit wurde angelegt.');
    }

    public function edit(Unit $unit)
    {
        $unit->load('property');
        $this->assertOwnerAccess($unit->property->owner_id);
        $property = $unit->property;

        return view('stammdaten.units.form', compact('property', 'unit'));
    }

    public function update(Request $request, Unit $unit)
    {
        $unit->load('property');
        $this->assertOwnerAccess($unit->property->owner_id);
        $data = $this->validated($request);

        DB::transaction(function () use ($unit, $data) {
            $keys = array_keys($data);
            $before = $unit->only($keys);
            $unit->update($data);
            [$old, $new] = Audit::diff($before, $unit->only($keys));

            if ($new !== null) {
                Audit::log('unit.updated', $unit, 'Einheit geändert: '.$unit->label.' in '.$unit->property->name, $old, $new);
            }
        });

        return redirect()->route('properties.show', $unit->property)->with('status', 'Änderungen wurden gespeichert.');
    }

    public function destroy(Unit $unit)
    {
        $unit->load('property');
        $property = $unit->property;

        DB::transaction(function () use ($unit, $property) {
            Audit::log('unit.deleted', $unit, 'Einheit gelöscht: '.$unit->label.' in '.$property->name);
            $unit->delete();
            if (\Illuminate\Support\Facades\Schema::hasTable('listings')) {
                \App\Models\Listing::query()->where('unit_id', $unit->id)->update(['status' => 'offline']);
            }
        });

        return redirect()->route('properties.show', $property)->with('status', 'Einheit wurde gelöscht.');
    }

    private function validated(Request $request): array
    {
        Stammdaten::decimalFields($request, ['area_sqm', 'rooms']);

        return $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'floor' => ['nullable', 'string', 'max:30'],
            'usage_type' => ['required', Rule::in(array_keys(Stammdaten::USAGE))],
            'area_sqm' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'rooms' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'equipment' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], Stammdaten::messages(), Stammdaten::attributes());
    }
}
