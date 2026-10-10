<?php

use App\Http\Controllers\ImportController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Import aus Excel (Paket 15)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/import', [ImportController::class, 'index'])->name('import.index');
    Route::get('/import/vorlage.xlsx', [ImportController::class, 'template'])->name('import.template');
    Route::post('/import/pruefen', [ImportController::class, 'check'])->name('import.check');
    Route::post('/import/eintragen', [ImportController::class, 'run'])->name('import.run');
});
