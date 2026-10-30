<?php

use App\Http\Controllers\RentIncreaseController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('increase', '[0-9]+');

// Mieterhöhungen (Paket 27)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/mieterhoehungen', [RentIncreaseController::class, 'index'])->name('rent_increase.index');
    Route::get('/mietvertraege/{lease}/mieterhoehung/neu', [RentIncreaseController::class, 'create'])->name('rent_increase.create');
    Route::post('/mietvertraege/{lease}/mieterhoehung/pruefen', [RentIncreaseController::class, 'preview'])->name('rent_increase.preview');
    Route::post('/mietvertraege/{lease}/mieterhoehung', [RentIncreaseController::class, 'store'])->name('rent_increase.store');
    Route::get('/mieterhoehungen/{increase}', [RentIncreaseController::class, 'show'])->name('rent_increase.show');
    Route::get('/mieterhoehungen/{increase}/drucken', [RentIncreaseController::class, 'print'])->name('rent_increase.print');
    Route::put('/mieterhoehungen/{increase}/text', [RentIncreaseController::class, 'updateNote'])->name('rent_increase.note');
    Route::post('/mieterhoehungen/{increase}/datum', [RentIncreaseController::class, 'redate'])->name('rent_increase.redate');
    Route::post('/mieterhoehungen/{increase}/erklaert', [RentIncreaseController::class, 'declare'])->name('rent_increase.declare');
    Route::post('/mieterhoehungen/{increase}/zustimmung', [RentIncreaseController::class, 'consent'])->name('rent_increase.consent');
    Route::post('/mieterhoehungen/{increase}/uebernehmen', [RentIncreaseController::class, 'adopt'])->name('rent_increase.adopt');
    Route::post('/mieterhoehungen/{increase}/ablehnung', [RentIncreaseController::class, 'reject'])->name('rent_increase.reject');
    Route::post('/mieterhoehungen/{increase}/zuruecknehmen', [RentIncreaseController::class, 'withdraw'])->name('rent_increase.withdraw');
    Route::delete('/mieterhoehungen/{increase}', [RentIncreaseController::class, 'destroy'])->name('rent_increase.destroy');

    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::post('/mieterhoehungen/{increase}/rueckgaengig', [RentIncreaseController::class, 'undo'])->name('rent_increase.undo');
        Route::post('/mieterhoehungen/einstellungen', [RentIncreaseController::class, 'settings'])->name('rent_increase.settings');
    });
});
