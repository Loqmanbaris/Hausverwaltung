<?php

use App\Http\Controllers\ServiceContractController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('contract', '[0-9]+');

// Laufende Verträge (Paket 32)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/laufende-vertraege', [ServiceContractController::class, 'index'])->name('svc.index');
    Route::get('/laufende-vertraege/neu', [ServiceContractController::class, 'create'])->name('svc.create');
    Route::post('/laufende-vertraege', [ServiceContractController::class, 'store'])->name('svc.store');
    Route::get('/laufende-vertraege/{contract}', [ServiceContractController::class, 'show'])->name('svc.show');
    Route::get('/laufende-vertraege/{contract}/bearbeiten', [ServiceContractController::class, 'edit'])->name('svc.edit');
    Route::put('/laufende-vertraege/{contract}', [ServiceContractController::class, 'update'])->name('svc.update');
    Route::delete('/laufende-vertraege/{contract}', [ServiceContractController::class, 'destroy'])->name('svc.destroy');
    Route::post('/laufende-vertraege/{contract}/gekuendigt', [ServiceContractController::class, 'cancel'])->name('svc.cancel');
    Route::post('/laufende-vertraege/{contract}/laufzeit', [ServiceContractController::class, 'renew'])->name('svc.renew');
    Route::post('/laufende-vertraege/{contract}/beendet', [ServiceContractController::class, 'end'])->name('svc.end');
    Route::post('/laufende-vertraege/{contract}/wieder-aktiv', [ServiceContractController::class, 'reopen'])->name('svc.reopen');
});
