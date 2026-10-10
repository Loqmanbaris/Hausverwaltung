<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\SiteAsset;
use App\Models\SiteObject;
use App\Support\Audit;
use App\Support\Settings;
use App\Support\Site\SiteFiles;
use App\Support\Site\SiteSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/** Webseite gestalten: Texte, Farbe, Logo, Startfoto, Leistungen, Objekt-Galerie, Downloads (nur Administratoren). */
class SiteAdminController extends Controller
{
    public function edit()
    {
        $props = Property::query()->with('owner')->orderBy('name')->get();
        foreach ($props as $p) {
            SiteObject::query()->firstOrCreate(['property_id' => $p->id], ['visible' => false, 'sort' => 0]);
        }
        $values = [];
        foreach (array_keys(SiteSettings::FIELDS) as $k) {
            $values[$k] = SiteSettings::get($k);
        }

        return view('site.edit', [
            'values' => $values,
            'services' => SiteSettings::services(),
            'objects' => SiteObject::query()->with(['property', 'photos'])->whereIn('property_id', $props->pluck('id'))->orderByDesc('visible')->orderBy('sort')->orderBy('id')->get(),
            'logo' => SiteFiles::first('logo'),
            'hero' => SiteFiles::first('hero'),
            'downloads' => SiteAsset::query()->where('kind', 'download')->orderBy('sort')->orderBy('id')->get(),
            'gd' => function_exists('imagecreatefromstring'),
        ]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (SiteSettings::FIELDS as $k => [$label, $default, $max]) {
            if ($k !== 'site_services') {
                $rules[$k] = ['nullable', 'string', 'max:'.$max];
            }
        }
        $rules['site_accent'] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        $rules['services'] = ['nullable', 'array', 'max:8'];
        $rules['services.*.title'] = ['nullable', 'string', 'max:80'];
        $rules['services.*.text'] = ['nullable', 'string', 'max:600'];
        $data = $request->validate($rules, ['site_accent.*' => 'Bitte eine Farbe wählen.']);

        $values = [];
        foreach (SiteSettings::FIELDS as $k => $f) {
            if ($k !== 'site_services') {
                $values[$k] = trim(str_replace("\r", '', (string) ($data[$k] ?? '')));
            }
        }
        $services = [];
        foreach ($data['services'] ?? [] as $s) {
            if (trim((string) ($s['title'] ?? '')) !== '') {
                $services[] = ['title' => trim($s['title']), 'text' => trim(str_replace("\r", '', (string) ($s['text'] ?? '')))];
            }
        }
        $values['site_services'] = json_encode($services, JSON_UNESCAPED_UNICODE);
        Settings::putMany($values);
        Audit::log('site.updated', 'Webseite: Texte und Farbe geändert');

        return redirect()->route('site.edit')->with('status', 'Gespeichert. Die Webseite zeigt die Änderungen sofort.');
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'kind' => ['required', Rule::in(['logo', 'hero', 'object', 'download'])],
            'ref' => ['nullable', 'integer'],
            'title' => ['nullable', 'string', 'max:190'],
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:20480'],
        ], ['files.required' => 'Bitte eine Datei auswählen.', 'files.*.max' => 'Eine Datei darf höchstens 20 MB groß sein.']);
        $ref = null;
        if ($data['kind'] === 'object') {
            $ref = SiteObject::query()->findOrFail((int) $data['ref'])->id;
        }
        $errors = [];
        $n = 0;
        foreach ($request->file('files') as $f) {
            try {
                SiteFiles::store($f, $data['kind'], $ref, $data['title'] ?? null);
                $n++;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
            if (in_array($data['kind'], ['logo', 'hero'], true)) {
                break;
            }
        }
        $anchor = ['logo' => 'aussehen', 'hero' => 'aussehen', 'object' => 'objekt'.$ref, 'download' => 'mieter'][$data['kind']];
        $r = redirect()->to(route('site.edit').'#'.$anchor)->with('status', $n === 1 ? 'Datei gespeichert.' : $n.' Dateien gespeichert.');

        return $errors ? $r->withErrors(['files' => implode(' ', $errors)]) : $r;
    }

    public function destroyAsset(SiteAsset $asset)
    {
        $anchor = ['logo' => 'aussehen', 'hero' => 'aussehen', 'object' => 'objekt'.$asset->ref_id, 'download' => 'mieter'][$asset->kind] ?? '';
        SiteFiles::delete($asset);

        return redirect()->to(route('site.edit').'#'.$anchor)->with('status', 'Datei entfernt.');
    }

    public function objects(Request $request)
    {
        $data = $request->validate([
            'o' => ['nullable', 'array'],
            'o.*.visible' => ['nullable', 'boolean'],
            'o.*.title' => ['nullable', 'string', 'max:120'],
            'o.*.text' => ['nullable', 'string', 'max:2000'],
            'o.*.sort' => ['nullable', 'integer', 'between:0,999'],
        ]);
        foreach ($data['o'] ?? [] as $id => $o) {
            SiteObject::query()->whereKey((int) $id)->update([
                'visible' => (bool) ($o['visible'] ?? false),
                'title' => trim((string) ($o['title'] ?? '')) ?: null,
                'text' => trim(str_replace("\r", '', (string) ($o['text'] ?? ''))) ?: null,
                'sort' => (int) ($o['sort'] ?? 0),
            ]);
        }
        Audit::log('site.updated', 'Webseite: Objekt-Galerie geändert');

        return redirect()->to(route('site.edit').'#objekte')->with('status', 'Objekte gespeichert.');
    }
}
