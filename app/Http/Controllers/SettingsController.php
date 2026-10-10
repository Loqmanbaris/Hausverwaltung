<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Settings;
use App\Support\Stammdaten;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Einstellungen für Impressum und Startseite. Nur für Administratoren (über die Route geschützt).
 */
class SettingsController extends Controller
{
    public function edit()
    {
        return view('settings.edit', ['values' => Settings::all()]);
    }

    public function update(Request $request)
    {
        $attributes = [];
        foreach (Settings::FIELDS as $key => [$label]) {
            $attributes[$key] = $label;
        }

        $data = $request->validate([
            'imprint_name' => ['required', 'string', 'max:190'],
            'imprint_street' => ['required', 'string', 'max:190'],
            'imprint_zip' => ['required', 'string', 'max:10'],
            'imprint_city' => ['required', 'string', 'max:120'],
            'imprint_email' => ['required', 'email', 'max:190'],
            'imprint_phone' => ['nullable', 'string', 'max:60'],
            'imprint_vat_id' => ['nullable', 'string', 'max:30'],
            'home_tagline' => ['nullable', 'string', 'max:300'],
        ], Stammdaten::messages(), $attributes);

        $before = [];
        foreach (array_keys($data) as $key) {
            $before[$key] = Settings::get($key, '');
        }

        DB::transaction(function () use ($data, $before) {
            Settings::putMany($data);

            [$old, $new] = Audit::diff($before, array_map(fn ($v) => (string) $v, $data));
            if ($new !== null) {
                Audit::log('settings.updated', 'Einstellungen geändert (Impressum und Startseite)', null, $old, $new);
            }
        });

        return redirect()->route('settings.edit')->with('status', 'Einstellungen wurden gespeichert.');
    }
}
