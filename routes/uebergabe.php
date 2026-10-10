<?php

use App\Http\Controllers\HandoverController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('protocol', '[0-9]+');
Route::pattern('photo', '[0-9]+');

// Übergabeprotokoll (Paket 13)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::post('/mietvertraege/{lease}/uebergabe', [HandoverController::class, 'store'])->name('handover.store');
    Route::get('/uebergabe/{protocol}', [HandoverController::class, 'show'])->name('handover.show');
    Route::get('/uebergabe/{protocol}/bearbeiten', [HandoverController::class, 'edit'])->name('handover.edit');
    Route::put('/uebergabe/{protocol}', [HandoverController::class, 'update'])->name('handover.update');
    Route::post('/uebergabe/{protocol}/fotos', [HandoverController::class, 'photoStore'])->name('handover.photos.store');
    Route::get('/uebergabe/{protocol}/fotos/{photo}/{size}', [HandoverController::class, 'photoShow'])->where('size', '[ls]')->name('handover.photos.show');
    Route::delete('/uebergabe/{protocol}/fotos/{photo}', [HandoverController::class, 'photoDestroy'])->name('handover.photos.destroy');
    Route::get('/uebergabe/{protocol}/abschliessen', [HandoverController::class, 'signForm'])->name('handover.sign');
    Route::post('/uebergabe/{protocol}/abschliessen', [HandoverController::class, 'signStore'])->name('handover.sign.store');
    Route::get('/uebergabe/{protocol}/drucken', [HandoverController::class, 'print'])->name('handover.print');
    Route::delete('/uebergabe/{protocol}', [HandoverController::class, 'destroy'])->name('handover.destroy');
});
