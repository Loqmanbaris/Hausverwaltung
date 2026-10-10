<?php

use App\Http\Controllers\RecurringExpenseController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('recurring', '[0-9]+');

// Administrator und Mitarbeiter (Mitarbeiter nur für freigegebene Eigentümer)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/wiederkehrend', [RecurringExpenseController::class, 'index'])->name('recurring.index');
    Route::get('/wiederkehrend/neu', [RecurringExpenseController::class, 'create'])->name('recurring.create');
    Route::post('/wiederkehrend', [RecurringExpenseController::class, 'store'])->name('recurring.store');
    Route::get('/wiederkehrend/{recurring}/bearbeiten', [RecurringExpenseController::class, 'edit'])->name('recurring.edit');
    Route::put('/wiederkehrend/{recurring}', [RecurringExpenseController::class, 'update'])->name('recurring.update');
});
