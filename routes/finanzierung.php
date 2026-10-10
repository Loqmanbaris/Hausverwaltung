<?php

use App\Http\Controllers\LoanController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Finanzierung (Paket 23)
Route::pattern('loan', '[0-9]+');
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/finanzierung', [LoanController::class, 'index'])->name('finance.index');
    Route::post('/finanzierung/abgleich', [LoanController::class, 'match'])->name('finance.match');
    Route::get('/finanzierung/darlehen/neu', [LoanController::class, 'create'])->name('loans.create');
    Route::post('/finanzierung/darlehen', [LoanController::class, 'store'])->name('loans.store');
    Route::get('/finanzierung/darlehen/{loan}', [LoanController::class, 'show'])->name('loans.show');
    Route::get('/finanzierung/darlehen/{loan}/bearbeiten', [LoanController::class, 'edit'])->name('loans.edit');
    Route::put('/finanzierung/darlehen/{loan}', [LoanController::class, 'update'])->name('loans.update');
    Route::delete('/finanzierung/darlehen/{loan}', [LoanController::class, 'destroy'])->name('loans.destroy');
    Route::post('/finanzierung/darlehen/{loan}/ereignis', [LoanController::class, 'eventStore'])->name('loans.events.store');
    Route::delete('/finanzierung/darlehen/{loan}/ereignis/{event}', [LoanController::class, 'eventDestroy'])->name('loans.events.destroy');
    Route::get('/objekte/{property}/finanzierung', [LoanController::class, 'valuesEdit'])->name('properties.values');
    Route::put('/objekte/{property}/finanzierung', [LoanController::class, 'valuesUpdate'])->name('properties.values.update');
    Route::post('/kontoauszuege/ausgaben/{tx}/darlehen', [LoanController::class, 'assignTx'])->name('bank.expenses.loan');
    Route::post('/kontoauszuege/ausgaben/{tx}/darlehen-zuruecksetzen', [LoanController::class, 'unassignTx'])->name('bank.expenses.loan.reset');
});
