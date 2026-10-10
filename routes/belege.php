<?php

use App\Http\Controllers\ReceiptController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('receipt', '[0-9]+');
Route::pattern('receiptFile', '[0-9]+');

// Voll angemeldet (Admin und Mitarbeiter, Mitarbeiter nur für freigegebene Eigentümer)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/belege', [ReceiptController::class, 'index'])->name('receipts.index');
    Route::get('/belege/neu', [ReceiptController::class, 'create'])->name('receipts.create');
    Route::post('/belege', [ReceiptController::class, 'store'])->name('receipts.store');
    Route::get('/belege/{receipt}', [ReceiptController::class, 'show'])->name('receipts.show');
    Route::get('/belege/{receipt}/bearbeiten', [ReceiptController::class, 'edit'])->name('receipts.edit');
    Route::put('/belege/{receipt}', [ReceiptController::class, 'update'])->name('receipts.update');
    Route::get('/belege/{receipt}/dateien/{receiptFile}', [ReceiptController::class, 'file'])->name('receipts.file');

    // Nur Administratoren
    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::delete('/belege/{receipt}', [ReceiptController::class, 'destroy'])->name('receipts.destroy');
    });
});
