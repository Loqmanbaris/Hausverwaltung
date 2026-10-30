<?php

use App\Http\Controllers\RetentionController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Löschfristen für ehemalige Mieter (Paket 35), nur Administrator
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class, EnsureAdmin::class])->group(function () {
    Route::get('/datenschutz/loeschfristen', [RetentionController::class, 'index'])->name('retention.index');
    Route::post('/datenschutz/loeschfristen', [RetentionController::class, 'settings'])->name('retention.settings');
    Route::post('/datenschutz/loeschfristen/{tenant}/bereinigen', [RetentionController::class, 'clean'])->whereNumber('tenant')->name('retention.clean');
    Route::post('/datenschutz/loeschfristen/{tenant}/aufbewahren', [RetentionController::class, 'hold'])->whereNumber('tenant')->name('retention.hold');
});
