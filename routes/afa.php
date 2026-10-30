<?php

use App\Http\Controllers\AfaController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Gebäude-AfA je Objekt (Paket 29)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/objekte/{property}/afa', [AfaController::class, 'edit'])->name('afa.edit');
    Route::put('/objekte/{property}/afa', [AfaController::class, 'update'])->name('afa.update');
    Route::post('/objekte/{property}/afa/jahr', [AfaController::class, 'yearStore'])->name('afa.year.store');
    Route::delete('/objekte/{property}/afa/jahr/{year}', [AfaController::class, 'yearDestroy'])->whereNumber('year')->name('afa.year.destroy');
});
