<?php

use App\Http\Controllers\PublicSiteController;
use App\Http\Controllers\SiteAdminController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('asset', '[0-9]+');

// Öffentlich (Paket 16)
Route::middleware(SecurityHeaders::class)->group(function () {
    Route::get('/leistungen', [PublicSiteController::class, 'services'])->name('site.services');
    Route::get('/unsere-objekte', [PublicSiteController::class, 'objects'])->name('site.objects');
    Route::get('/fuer-mieter', [PublicSiteController::class, 'tenants'])->name('site.tenants');
    Route::get('/seite/{asset}-{size}.{ext}', [PublicSiteController::class, 'asset'])->where(['size' => '[lso]', 'ext' => 'jpg|png|pdf'])->name('site.asset');
});

// Verwaltung (nur Administratoren)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class, EnsureAdmin::class])->group(function () {
    Route::get('/webseite', [SiteAdminController::class, 'edit'])->name('site.edit');
    Route::put('/webseite', [SiteAdminController::class, 'update'])->name('site.update');
    Route::post('/webseite/dateien', [SiteAdminController::class, 'upload'])->name('site.upload');
    Route::delete('/webseite/dateien/{asset}', [SiteAdminController::class, 'destroyAsset'])->name('site.asset.destroy');
    Route::put('/webseite/objekte', [SiteAdminController::class, 'objects'])->name('site.objects.update');
});
