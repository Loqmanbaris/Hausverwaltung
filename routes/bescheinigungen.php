<?php

use App\Http\Controllers\BescheinigungController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Schreiben an Mieter: Wohnungsgeberbestätigung, Mietschuldenfreiheit (Paket 44), Abmahnung, Kündigung (Paket 45)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/bescheinigungen', [BescheinigungController::class, 'choose'])->name('bescheinigung.choose');
    Route::get('/mietvertraege/{lease}/bescheinigungen', [BescheinigungController::class, 'index'])->whereNumber('lease')->name('bescheinigung.index');
    Route::get('/mietvertraege/{lease}/wohnungsgeberbestaetigung', [BescheinigungController::class, 'wohnungsgeber'])->whereNumber('lease')->name('bescheinigung.wohnungsgeber');
    Route::get('/mietvertraege/{lease}/abmahnung', [BescheinigungController::class, 'abmahnung'])->whereNumber('lease')->name('bescheinigung.abmahnung');
    Route::get('/mietvertraege/{lease}/kuendigung', [BescheinigungController::class, 'kuendigung'])->whereNumber('lease')->name('bescheinigung.kuendigung');
    Route::get('/mietvertraege/{lease}/mietschuldenfreiheit', [BescheinigungController::class, 'mietschuldenfrei'])->whereNumber('lease')->name('bescheinigung.mietschuldenfrei');
});
