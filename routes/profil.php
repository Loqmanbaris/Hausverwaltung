<?php

use App\Http\Controllers\ProfileSettingsController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Profilmenü → Einstellungen: alle Einstellungen als Kacheln an einer Stelle (wie im Portal und bei Freytag)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/profil/einstellungen', [ProfileSettingsController::class, 'index'])->name('profile.settings');
});
