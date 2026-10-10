<?php

use App\Http\Controllers\InterestController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('booking', '[0-9]+');
Route::pattern('plan', '[0-9]+');

// Verzugszinsen und Raten (Paket 14)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/mietvertraege/{lease}/zinsen', [InterestController::class, 'index'])->name('interest.index');
    Route::post('/mietvertraege/{lease}/zinsen', [InterestController::class, 'book'])->name('interest.book');
    Route::post('/zinsbuchungen/{booking}/stornieren', [InterestController::class, 'cancel'])->name('interest.cancel');
    Route::get('/mietvertraege/{lease}/raten/neu', [InterestController::class, 'planCreate'])->name('installments.create');
    Route::post('/mietvertraege/{lease}/raten', [InterestController::class, 'planStore'])->name('installments.store');
    Route::get('/ratenvereinbarungen/{plan}', [InterestController::class, 'planShow'])->name('installments.show');
    Route::get('/ratenvereinbarungen/{plan}/drucken', [InterestController::class, 'planPrint'])->name('installments.print');
    Route::post('/ratenvereinbarungen/{plan}/status', [InterestController::class, 'planStatus'])->name('installments.status');

    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::post('/mahnwesen/basiszins', [InterestController::class, 'rateStore'])->name('interest.rates.store');
        Route::delete('/mahnwesen/basiszins/{date}', [InterestController::class, 'rateDestroy'])->name('interest.rates.destroy');
    });
});
