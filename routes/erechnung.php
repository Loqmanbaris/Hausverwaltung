<?php

use App\Http\Controllers\EInvoiceController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Voll angemeldet (Admin und Mitarbeiter, Mitarbeiter nur für freigegebene Eigentümer)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/belege/e-rechnung', [EInvoiceController::class, 'create'])->name('einvoice.create');
    Route::post('/belege/e-rechnung', [EInvoiceController::class, 'store'])->name('einvoice.store');
});
