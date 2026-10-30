<?php

use App\Http\Controllers\InvoiceController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('invoice', '[0-9]+');

// Rechnungen schreiben (Paket 45), nur Administratoren
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class, EnsureAdmin::class])->group(function () {
    Route::get('/rechnungen', [InvoiceController::class, 'index'])->name('invoices.index');
    Route::get('/rechnungen/neu', [InvoiceController::class, 'create'])->name('invoices.create');
    Route::post('/rechnungen', [InvoiceController::class, 'store'])->name('invoices.store');
    Route::get('/rechnungen/daten', [InvoiceController::class, 'settings'])->name('invoices.settings');
    Route::put('/rechnungen/daten', [InvoiceController::class, 'saveSettings'])->name('invoices.settings.save');
    Route::get('/rechnungen/{invoice}', [InvoiceController::class, 'show'])->name('invoices.show');
    Route::get('/rechnungen/{invoice}/bearbeiten', [InvoiceController::class, 'edit'])->name('invoices.edit');
    Route::put('/rechnungen/{invoice}', [InvoiceController::class, 'update'])->name('invoices.update');
    Route::delete('/rechnungen/{invoice}', [InvoiceController::class, 'destroy'])->name('invoices.destroy');
    Route::post('/rechnungen/{invoice}/festschreiben', [InvoiceController::class, 'finalize'])->name('invoices.finalize');
    Route::post('/rechnungen/{invoice}/gutschrift', [InvoiceController::class, 'credit'])->name('invoices.credit');
    Route::post('/rechnungen/{invoice}/bezahlt', [InvoiceController::class, 'paid'])->name('invoices.paid');
    Route::post('/rechnungen/{invoice}/versendet', [InvoiceController::class, 'sent'])->name('invoices.sent');
    Route::post('/rechnungen/{invoice}/mail', [InvoiceController::class, 'mail'])->middleware('throttle:30,1')->name('invoices.mail');
    Route::get('/rechnungen/{invoice}/pdf', [InvoiceController::class, 'pdf'])->name('invoices.pdf');
    Route::get('/rechnungen/{invoice}/xml', [InvoiceController::class, 'xml'])->name('invoices.xml');
    Route::get('/rechnungen/{invoice}/ansicht', [InvoiceController::class, 'view'])->name('invoices.view');
});
