<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesOwners;
use App\Models\Lease;
use App\Models\LeasePerson;
use App\Models\Meter;
use App\Models\MeterReading;
use App\Models\NkCostKey;
use App\Models\NkSetting;
use App\Models\Property;
use App\Support\Audit;
use App\Support\Kostenarten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Vorbereitung der Nebenkostenabrechnung: Einstellungen je Objekt, Zähler und Zählerstände, Personen je Mietvertrag. */
class NkSetupController extends Controller
{
    use ScopesOwners;

    public function settings(Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $setting = NkSetting::forProperty((int) $property->id);
        $keys = NkCostKey::query()->where('property_id', $property->id)->get()->keyBy('cost_key');
        $types = DB::table('cost_types')->where('allocatable', true)->orderBy('sort')->get(['key', 'label', 'active']);
        $hasCommercial = $property->units()->whereIn('usage_type', ['gewerbe_option', 'gewerbe_ohne_option'])->exists();

        return view('nk.settings', compact('property', 'setting', 'keys', 'types', 'hasCommercial'));
    }

    public function saveSettings(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $data = $request->validate([
            'heating_mode' => ['required', Rule::in(array_keys(NkSetting::HEATING))],
            'consumption_share' => ['required', 'integer', 'between:50,70'],
            'key' => ['array'],
            'key.*' => [Rule::in(array_keys(NkCostKey::TYPES))],
            'percent' => ['array'],
            'percent.*' => ['nullable', 'numeric', 'between:0,100'],
            'fuel' => ['nullable', Rule::in(array_keys(NkSetting::FUELS))],
            'building_use' => ['nullable', Rule::in(['wohn', 'nichtwohn'])],
            'co2_restriction' => ['nullable', Rule::in(array_keys(NkSetting::RESTRICTIONS))],
            'co2_restriction_note' => ['nullable', 'string', 'max:190'],
            'benchmark_kwh' => ['nullable', 'string', 'max:12'],
        ], [
            'consumption_share.between' => 'Der Verbrauchsanteil muss zwischen 50 und 70 Prozent liegen (Heizkostenverordnung).',
            'percent.*.between' => 'Der Vorwegabzug muss zwischen 0 und 100 Prozent liegen.',
            'percent.*.numeric' => 'Der Vorwegabzug muss eine Zahl sein.',
        ]);

        $restriction = $data['co2_restriction'] ?? 'keine';
        if ($restriction !== 'keine' && trim((string) ($data['co2_restriction_note'] ?? '')) === '') {
            return back()->withInput()->withErrors(['co2_restriction_note' => 'Bitte den Grund für die Kürzung angeben (z. B. Bescheid der Denkmalbehörde). Ohne Nachweis gilt die Kürzung nicht.']);
        }
        $bench = trim((string) ($data['benchmark_kwh'] ?? ''));
        $bench = $bench === '' ? null : str_replace(',', '.', $bench);
        if ($bench !== null && (! is_numeric($bench) || (float) $bench <= 0 || (float) $bench > 1000)) {
            return back()->withInput()->withErrors(['benchmark_kwh' => 'Der Vergleichswert muss eine Zahl zwischen 1 und 1000 kWh je m² und Jahr sein.']);
        }

        DB::transaction(function () use ($property, $data, $request, $restriction, $bench) {
            $setting = NkSetting::forProperty((int) $property->id);
            $setting->fill([
                'heating_mode' => $data['heating_mode'],
                'consumption_share' => (int) $data['consumption_share'],
                'fuel' => $data['fuel'] ?? null,
                'building_use' => $data['building_use'] ?? null,
                'co2_restriction' => $restriction,
                'co2_restriction_note' => $restriction === 'keine' ? null : trim((string) $data['co2_restriction_note']),
                'benchmark_kwh' => $bench,
            ])->save();

            $allowed = DB::table('cost_types')->where('allocatable', true)->pluck('key')->all();
            foreach ((array) ($data['key'] ?? []) as $costKey => $type) {
                if (! in_array($costKey, $allowed, true)) {
                    continue;
                }
                $pct = str_replace(',', '.', (string) ($request->input('percent.'.$costKey) ?? ''));
                NkCostKey::query()->updateOrCreate(
                    ['property_id' => $property->id, 'cost_key' => $costKey],
                    ['key_type' => $type, 'commercial_percent' => $pct === '' || (float) $pct == 0.0 ? null : $pct]
                );
            }
            Audit::log('nk.settings', $property, 'Nebenkosten-Einstellungen geändert: '.$property->name);
        });

        return redirect()->route('nk.settings', $property)->with('status', 'Die Einstellungen wurden gespeichert.');
    }

    public function meters(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $units = $property->units()->orderBy('label')->get();
        $meters = Meter::query()->where('property_id', $property->id)->with(['unit', 'readings'])->get()
            ->sortBy(fn ($m) => [$m->unit?->label, $m->kind, $m->label])->values();
        $date = (string) $request->query('stichtag', ((int) date('Y') - 1).'-12-31');
        if (\DateTimeImmutable::createFromFormat('!Y-m-d', $date) === false) {
            $date = ((int) date('Y') - 1).'-12-31';
        }

        return view('nk.meters', compact('property', 'units', 'meters', 'date'));
    }

    public function storeMeter(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $data = $request->validate([
            'unit_id' => ['required', 'integer', Rule::in($property->units()->pluck('id')->all())],
            'kind' => ['required', Rule::in(array_keys(Meter::KINDS))],
            'label' => ['nullable', 'string', 'max:100'],
            'number' => ['nullable', 'string', 'max:60'],
            'factor' => ['nullable', 'string', 'max:12'],
            'calibrated_until' => ['nullable', 'integer', 'between:2000,2100'],
        ], ['unit_id.*' => 'Bitte eine Einheit wählen.', 'kind.*' => 'Bitte die Art des Zählers wählen.', 'calibrated_until.*' => 'Die Eichfrist bitte als Jahr angeben, z. B. 2029.']);

        $factor = str_replace(',', '.', trim((string) ($data['factor'] ?? '')));
        if ($factor !== '' && (! is_numeric($factor) || (float) $factor <= 0)) {
            return back()->withInput()->withErrors(['factor' => 'Der Umrechnungsfaktor muss eine Zahl größer als 0 sein.']);
        }

        $meter = Meter::create([
            'property_id' => $property->id,
            'unit_id' => (int) $data['unit_id'],
            'kind' => $data['kind'],
            'label' => $data['label'] ?? null,
            'number' => $data['number'] ?? null,
            'factor' => $factor === '' ? 1 : $factor,
            'active' => true,
            'remote' => $request->boolean('remote'),
            'calibrated_until' => $data['calibrated_until'] ?? null,
        ]);
        Audit::log('meter.created', $meter, 'Zähler angelegt: '.$meter->title().' in '.$property->name);

        return redirect()->route('nk.meters', $property)->with('status', 'Zähler wurde angelegt.');
    }

    /** Zähler ändern: Bezeichnung, Nummer, Faktor, fernablesbar, Eichfrist (z. B. nach dem Tausch). */
    public function updateMeter(Request $request, Property $property, Meter $meter)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        abort_unless((int) $meter->property_id === (int) $property->id, 404);
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'number' => ['nullable', 'string', 'max:60'],
            'factor' => ['nullable', 'string', 'max:12'],
            'calibrated_until' => ['nullable', 'integer', 'between:2000,2100'],
        ], ['calibrated_until.*' => 'Die Eichfrist bitte als Jahr angeben, z. B. 2029.']);
        $factor = str_replace(',', '.', trim((string) ($data['factor'] ?? '')));
        if ($factor !== '' && (! is_numeric($factor) || (float) $factor <= 0)) {
            return back()->withErrors(['factor' => 'Der Umrechnungsfaktor muss eine Zahl größer als 0 sein.']);
        }
        $meter->update([
            'label' => $data['label'] ?? null,
            'number' => $data['number'] ?? null,
            'factor' => $factor === '' ? 1 : $factor,
            'remote' => $request->boolean('remote'),
            'calibrated_until' => $data['calibrated_until'] ?? null,
        ]);
        Audit::log('meter.updated', $meter, 'Zähler geändert: '.$meter->title().' in '.$property->name.($meter->remote ? ' (fernablesbar)' : ''));

        return redirect()->to(route('nk.meters', $property).'#zaehler-'.$meter->id)->with('status', 'Zähler wurde gespeichert.');
    }

    /**
     * Zählerstände aus einer CSV-Datei (z. B. Export des Funk-Ablesesystems): Zählernummer; Datum; Stand.
     * Eine Kopfzeile wird erkannt und übersprungen. Vorhandene Werte am selben Tag werden überschrieben.
     */
    public function importReadings(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $request->validate(['file' => ['required', 'file', 'max:2048']], ['file.*' => 'Bitte eine CSV-Datei (höchstens 2 MB) wählen.']);
        $res = \App\Support\Nk\MeterImport::import($property, (string) file_get_contents($request->file('file')->getRealPath()), $this->me()->id);
        Audit::log('meter.import', $property, $res['saved'].' Zählerstände importiert: '.$property->name);
        $msg = $res['saved'].' Zählerstände übernommen.';
        if ($res['unknown'] !== []) {
            $msg .= ' Unbekannte Zählernummern: '.implode(', ', array_slice($res['unknown'], 0, 10)).(count($res['unknown']) > 10 ? ' …' : '').'.';
        }
        if ($res['invalid'] > 0) {
            $msg .= ' '.$res['invalid'].' Zeile(n) nicht lesbar.';
        }

        return redirect()->route('nk.meters', $property)->with('status', $msg);
    }

    public function destroyMeter(Property $property, Meter $meter)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        abort_unless((int) $meter->property_id === (int) $property->id, 404);
        Audit::log('meter.deleted', $meter, 'Zähler entfernt: '.$meter->title().' in '.$property->name);
        $meter->delete();

        return redirect()->route('nk.meters', $property)->with('status', 'Zähler wurde entfernt. Die Zählerstände bleiben gespeichert.');
    }

    /** Sammel-Eingabe: alle Zählerstände zu einem Stichtag. Leere Felder werden übersprungen, vorhandene Werte überschrieben. */
    public function storeReadings(Request $request, Property $property)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        $request->validate(['read_on' => ['required', 'date_format:Y-m-d']], ['read_on.*' => 'Bitte das Ablesedatum angeben.']);
        $date = (string) $request->input('read_on');
        $meterIds = Meter::query()->where('property_id', $property->id)->pluck('id')->map(fn ($i) => (int) $i)->all();

        $saved = 0;
        $errors = [];
        foreach ((array) $request->input('value', []) as $meterId => $raw) {
            $raw = trim((string) $raw);
            if ($raw === '' || ! in_array((int) $meterId, $meterIds, true)) {
                continue;
            }
            $v = str_contains($raw, ',') ? str_replace(',', '.', str_replace('.', '', $raw)) : $raw;
            if (! is_numeric($v) || (float) $v < 0) {
                $errors[] = 'Der Wert „'.$raw.'“ ist keine gültige Zahl.';

                continue;
            }
            MeterReading::query()->updateOrCreate(['meter_id' => (int) $meterId, 'read_on' => $date], ['value' => $v, 'created_by' => $this->me()->id]);
            $saved++;
        }
        if ($errors !== []) {
            return back()->withInput()->withErrors($errors);
        }
        Audit::log('meter.readings', $property, $saved.' Zählerstände zum '.date('d.m.Y', strtotime($date)).' erfasst: '.$property->name);

        return redirect()->route('nk.meters', ['property' => $property, 'stichtag' => $date])->with('status', $saved.' Zählerstände gespeichert.');
    }

    public function destroyReading(Property $property, MeterReading $reading)
    {
        $this->assertOwnerAccess((int) $property->owner_id);
        abort_unless((int) $reading->meter?->property_id === (int) $property->id, 404);
        $reading->delete();

        return redirect()->route('nk.meters', $property)->with('status', 'Zählerstand wurde gelöscht.');
    }

    public function storePersons(Request $request, Lease $lease)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        $data = $request->validate([
            'valid_from' => ['required', 'date_format:Y-m-d'],
            'persons' => ['required', 'integer', 'between:0,20'],
        ], ['valid_from.*' => 'Bitte ein gültiges Datum angeben.', 'persons.*' => 'Bitte eine Personenzahl zwischen 0 und 20 angeben.']);

        LeasePerson::query()->updateOrCreate(['lease_id' => $lease->id, 'valid_from' => $data['valid_from']], ['persons' => (int) $data['persons']]);
        Audit::log('lease.persons', $lease, 'Personenzahl '.$data['persons'].' ab '.date('d.m.Y', strtotime($data['valid_from'])).' erfasst');

        return redirect()->route('leases.show', $lease)->with('status', 'Personenzahl wurde gespeichert.');
    }

    public function destroyPersons(Lease $lease, LeasePerson $person)
    {
        $this->assertOwnerAccess((int) $lease->owner_id);
        abort_unless((int) $person->lease_id === (int) $lease->id, 404);
        $person->delete();

        return redirect()->route('leases.show', $lease)->with('status', 'Eintrag wurde entfernt.');
    }

    /** Für die Einstellungsseite: Bezeichnung der Kostenart. */
    public static function costLabel(string $key): string
    {
        return Kostenarten::label($key);
    }
}
