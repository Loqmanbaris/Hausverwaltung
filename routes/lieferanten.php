<?php

use App\Http\Controllers\CostTypeController;
use App\Http\Controllers\VendorController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('vendor', '[0-9]+');
Route::pattern('costType', '[0-9]+');

Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    // Lieferanten: Administrator und Mitarbeiter
    Route::get('/lieferanten', [VendorController::class, 'index'])->name('vendors.index');
    Route::get('/lieferanten/neu', [VendorController::class, 'create'])->name('vendors.create');
    Route::post('/lieferanten', [VendorController::class, 'store'])->name('vendors.store');
    Route::get('/lieferanten/{vendor}', [VendorController::class, 'show'])->name('vendors.show');
    Route::get('/lieferanten/{vendor}/bearbeiten', [VendorController::class, 'edit'])->name('vendors.edit');
    Route::put('/lieferanten/{vendor}', [VendorController::class, 'update'])->name('vendors.update');

    // Nur Administratoren
    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::post('/lieferanten/{vendor}/aktiv', [VendorController::class, 'toggle'])->name('vendors.toggle');

        Route::get('/kostenarten', [CostTypeController::class, 'index'])->name('cost-types.index');
        Route::get('/kostenarten/neu', [CostTypeController::class, 'create'])->name('cost-types.create');
        Route::post('/kostenarten', [CostTypeController::class, 'store'])->name('cost-types.store');
        Route::get('/kostenarten/{costType}/bearbeiten', [CostTypeController::class, 'edit'])->name('cost-types.edit');
        Route::put('/kostenarten/{costType}', [CostTypeController::class, 'update'])->name('cost-types.update');
    });
});
