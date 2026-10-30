<?php

use App\Http\Controllers\IssuedInvoiceController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// E-Rechnung (Paket 37)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/e-rechnung', [IssuedInvoiceController::class, 'index'])->name('erechnung.index');
    Route::post('/e-rechnung/alle', [IssuedInvoiceController::class, 'createAll'])->name('erechnung.create_all');
    Route::post('/e-rechnung/miete/{charge}', [IssuedInvoiceController::class, 'create'])->whereNumber('charge')->name('erechnung.create');
    Route::get('/e-rechnung/{invoice}/xml', [IssuedInvoiceController::class, 'xml'])->whereNumber('invoice')->name('erechnung.xml');
    Route::get('/e-rechnung/{invoice}/ansicht', [IssuedInvoiceController::class, 'view'])->whereNumber('invoice')->name('erechnung.view');
    Route::get('/e-rechnung/{invoice}/pdf', [IssuedInvoiceController::class, 'pdf'])->whereNumber('invoice')->name('erechnung.pdf');
    Route::post('/e-rechnung/{invoice}/gutschrift', [IssuedInvoiceController::class, 'credit'])->whereNumber('invoice')->name('erechnung.credit');
});
