<?php

use App\Http\Controllers\DatevController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('datevExport', '[0-9]+');

// Nur Administratoren
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class, EnsureAdmin::class])->group(function () {
    Route::get('/datev', [DatevController::class, 'index'])->name('datev.index');
    Route::post('/datev/export', [DatevController::class, 'export'])->name('datev.export');
    Route::get('/datev/exporte/{datevExport}/herunterladen', [DatevController::class, 'download'])->name('datev.download');
    Route::get('/datev/exporte/{datevExport}/belege', [DatevController::class, 'receipts'])->name('datev.receipts');
    Route::get('/datev/einstellungen', [DatevController::class, 'settings'])->name('datev.settings');
    Route::put('/datev/einstellungen', [DatevController::class, 'saveSettings'])->name('datev.settings.save');
    Route::get('/datev/konten', [DatevController::class, 'accounts'])->name('datev.accounts');
    Route::put('/datev/konten', [DatevController::class, 'saveAccounts'])->name('datev.accounts.save');
});
