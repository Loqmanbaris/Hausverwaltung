<?php

use App\Http\Controllers\SetupCheckController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Einrichtung prüfen (Paket 25)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class, EnsureAdmin::class])->group(function () {
    Route::get('/einrichtung', [SetupCheckController::class, 'index'])->name('setup.index');
    Route::post('/einrichtung/datev', [SetupCheckController::class, 'confirmDatev'])->name('setup.datev');
});
