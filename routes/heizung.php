<?php

use App\Http\Controllers\Co2ClaimController;
use App\Http\Controllers\ConsumptionController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// CO2-Kosten und Heizkosten-Zähler (Paket 26)
Route::pattern('claim', '[0-9]+');
Route::pattern('property', '[0-9]+');
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/co2-erstattung', [Co2ClaimController::class, 'index'])->name('co2.index');
    Route::post('/co2-erstattung', [Co2ClaimController::class, 'store'])->name('co2.store');
    Route::post('/co2-erstattung/{claim}/gutschrift', [Co2ClaimController::class, 'credit'])->name('co2.credit');
    Route::post('/co2-erstattung/{claim}/ausgezahlt', [Co2ClaimController::class, 'paid'])->name('co2.paid');
    Route::post('/co2-erstattung/{claim}/zuruecksetzen', [Co2ClaimController::class, 'reset'])->name('co2.reset');
    Route::get('/co2-erstattung/{claim}/drucken', [Co2ClaimController::class, 'print'])->name('co2.print');
    Route::delete('/co2-erstattung/{claim}', [Co2ClaimController::class, 'destroy'])->name('co2.destroy');

    Route::get('/verbrauchsinfo', [ConsumptionController::class, 'index'])->name('consumption.index');
    Route::get('/objekte/{property}/verbrauchsinfo', [ConsumptionController::class, 'show'])->name('consumption.show');
    Route::get('/objekte/{property}/verbrauchsinfo/drucken', [ConsumptionController::class, 'print'])->name('consumption.print');
    Route::post('/objekte/{property}/verbrauchsinfo/erledigt', [ConsumptionController::class, 'done'])->name('consumption.done');
});
