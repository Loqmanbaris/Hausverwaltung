<?php

use App\Http\Controllers\DocumentController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Dokumentenablage (Paket 22)
Route::pattern('document', '[0-9]+');
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/dokumente', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('/dokumente', [DocumentController::class, 'store'])->middleware('throttle:60,1')->name('documents.store');
    Route::get('/dokumente/{document}/datei', [DocumentController::class, 'file'])->name('documents.file');
    Route::get('/dokumente/{document}/bearbeiten', [DocumentController::class, 'edit'])->name('documents.edit');
    Route::put('/dokumente/{document}', [DocumentController::class, 'update'])->name('documents.update');
    Route::post('/dokumente/{document}/archivieren', [DocumentController::class, 'archive'])->name('documents.archive');
    Route::delete('/dokumente/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
});
