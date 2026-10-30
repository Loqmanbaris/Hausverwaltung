<?php

use App\Http\Controllers\UpdateController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('update', '[0-9]+');

// Nur Administratoren
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class, EnsureAdmin::class, 'throttle:30,1'])->group(function () {
    Route::get('/updates', [UpdateController::class, 'index'])->name('updates.index');
    Route::post('/updates', [UpdateController::class, 'upload'])->name('updates.upload');
    Route::get('/updates/github', [UpdateController::class, 'github'])->name('updates.github');
    Route::post('/updates/github', [UpdateController::class, 'githubSave'])->name('updates.github.save');
    Route::post('/updates/github/holen', [UpdateController::class, 'githubFetch'])->name('updates.github.fetch');
    Route::get('/updates/programmstand', [UpdateController::class, 'exportSource'])->name('updates.source');
    Route::get('/updates/pruefen', [UpdateController::class, 'show'])->name('updates.show');
    Route::post('/updates/einspielen', [UpdateController::class, 'install'])->name('updates.install');
    Route::get('/updates/{update}', [UpdateController::class, 'result'])->name('updates.result');
    Route::post('/updates/{update}/zuruecknehmen', [UpdateController::class, 'rollback'])->name('updates.rollback');
});
