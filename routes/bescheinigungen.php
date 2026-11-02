<?php

use App\Http\Controllers\BescheinigungController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Bescheinigungen zum Mietvertrag (Paket 44): Wohnungsgeberbestätigung, Mietschuldenfreiheitsbescheinigung
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/mietvertraege/{lease}/bescheinigungen', [BescheinigungController::class, 'index'])->whereNumber('lease')->name('bescheinigung.index');
    Route::get('/mietvertraege/{lease}/wohnungsgeberbestaetigung', [BescheinigungController::class, 'wohnungsgeber'])->whereNumber('lease')->name('bescheinigung.wohnungsgeber');
    Route::get('/mietvertraege/{lease}/mietschuldenfreiheit', [BescheinigungController::class, 'mietschuldenfrei'])->whereNumber('lease')->name('bescheinigung.mietschuldenfrei');
});
