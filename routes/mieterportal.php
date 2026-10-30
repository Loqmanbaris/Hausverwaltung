<?php

use App\Http\Controllers\PortalAdminController;
use App\Http\Controllers\PortalController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\PortalAuth;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Mieterportal für Mieter (Paket 36): eigene Anmeldung per Link, getrennt von der Verwaltung
Route::middleware([SecurityHeaders::class])->prefix('mieter-portal')->group(function () {
    Route::get('/', [PortalController::class, 'login'])->name('portal.login');
    Route::post('/link', [PortalController::class, 'requestLink'])->middleware('throttle:20,1')->name('portal.link');
    Route::get('/anmelden/{token}', [PortalController::class, 'confirm'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:30,1')->name('portal.confirm');
    Route::post('/anmelden/{token}', [PortalController::class, 'enter'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:30,1')->name('portal.enter');

    Route::middleware([PortalAuth::class])->group(function () {
        Route::get('/start', [PortalController::class, 'home'])->name('portal.home');
        Route::get('/konto', [PortalController::class, 'account'])->name('portal.account');
        Route::get('/dokumente', [PortalController::class, 'documents'])->name('portal.documents');
        Route::get('/dokumente/{document}', [PortalController::class, 'document'])->whereNumber('document')->name('portal.document');
        Route::get('/zaehler', [PortalController::class, 'meters'])->name('portal.meters');
        Route::post('/zaehler', [PortalController::class, 'readingStore'])->middleware('throttle:40,1')->name('portal.reading');
        Route::get('/schaden', [PortalController::class, 'damage'])->name('portal.damage');
        Route::post('/schaden', [PortalController::class, 'damageStore'])->middleware('throttle:30,1')->name('portal.damage.store');
        Route::post('/abmelden', [PortalController::class, 'logout'])->name('portal.logout');
    });
});

// Freigabe durch die Verwaltung
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::post('/mieter/{tenant}/portal', [PortalAdminController::class, 'enable'])->whereNumber('tenant')->name('portal.admin.enable');
    Route::post('/mieter/{tenant}/portal/einladen', [PortalAdminController::class, 'invite'])->whereNumber('tenant')->name('portal.admin.invite');
    Route::delete('/mieter/{tenant}/portal', [PortalAdminController::class, 'disable'])->whereNumber('tenant')->name('portal.admin.disable');
});
