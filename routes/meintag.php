<?php

use App\Http\Controllers\TagController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('item', '[0-9]+');

// Mein Tag (Paket 42)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/mein-tag', [TagController::class, 'index'])->name('tag.index');
    Route::post('/mein-tag/eintraege', [TagController::class, 'store'])->name('tag.store');
    Route::get('/mein-tag/eintraege/{item}/bearbeiten', [TagController::class, 'edit'])->name('tag.edit');
    Route::put('/mein-tag/eintraege/{item}', [TagController::class, 'update'])->name('tag.update');
    Route::post('/mein-tag/eintraege/{item}/erledigt', [TagController::class, 'done'])->name('tag.done');
    Route::post('/mein-tag/eintraege/{item}/auslassen', [TagController::class, 'skip'])->name('tag.skip');
    Route::post('/mein-tag/eintraege/{item}/verschieben', [TagController::class, 'move'])->name('tag.move');
    Route::delete('/mein-tag/eintraege/{item}', [TagController::class, 'destroy'])->name('tag.destroy');
    Route::post('/mein-tag/vorlagen/{key}', [TagController::class, 'template'])->where('key', '[a-z]+')->name('tag.template');
    Route::post('/mein-tag/mail', [TagController::class, 'mail'])->name('tag.mail');
});
