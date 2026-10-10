<?php

use App\Http\Controllers\DashboardLayoutController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Übersicht selbst gestalten (Paket 19)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/uebersicht/anpassen', [DashboardLayoutController::class, 'edit'])->name('dashboard.layout');
    Route::put('/uebersicht/anpassen', [DashboardLayoutController::class, 'update'])->name('dashboard.layout.update');
    Route::post('/uebersicht/zuruecksetzen', [DashboardLayoutController::class, 'reset'])->name('dashboard.layout.reset');
    Route::put('/uebersicht/merkzettel', [DashboardLayoutController::class, 'note'])->name('dashboard.note');
});
