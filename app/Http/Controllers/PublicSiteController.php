<?php

namespace App\Http\Controllers;

use App\Models\SiteAsset;
use App\Models\SiteObject;
use App\Support\Site\SiteFiles;
use App\Support\Site\SiteSettings;

/** Öffentliche Seiten: Leistungen, Unsere Objekte, Für Mieter, Dateien (Paket 16). */
class PublicSiteController extends Controller
{
    public function services()
    {
        return view('site.services', ['services' => SiteSettings::services(), 'intro' => SiteSettings::get('site_services_intro')]);
    }

    public function objects()
    {
        return view('site.objects', [
            'objects' => SiteObject::query()->where('visible', true)->with(['property', 'photos'])->orderBy('sort')->orderBy('id')->get()->filter(fn ($o) => $o->property !== null),
            'intro' => SiteSettings::get('site_objects_intro'),
        ]);
    }

    public function tenants()
    {
        return view('site.tenants', [
            'intro' => SiteSettings::get('site_tenants_intro'),
            'damage' => SiteSettings::get('site_damage_text'),
            'emergency' => SiteSettings::emergency(),
            'downloads' => SiteAsset::query()->where('kind', 'download')->orderBy('sort')->orderBy('id')->get(),
        ]);
    }

    public function asset(SiteAsset $asset, string $size, string $ext)
    {
        if ($asset->kind === 'object') {
            abort_unless(SiteObject::query()->whereKey($asset->ref_id)->where('visible', true)->exists() || auth()->check(), 404);
        }
        $path = SiteFiles::path($asset, $size);
        abort_if($path === null, 404);
        $headers = ['Content-Type' => $asset->mime, 'Cache-Control' => 'public, max-age=604800'];
        if ($asset->kind === 'download') {
            $headers['Content-Disposition'] = 'inline; filename="'.preg_replace('/[^A-Za-z0-9äöüÄÖÜß _.-]/u', '', (string) $asset->title).'.pdf"';
        }

        return response()->file($path, $headers);
    }
}
