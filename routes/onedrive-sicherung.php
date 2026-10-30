<?php

use App\Http\Controllers\OneDriveController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// OneDrive für die Sicherung (Paket 40), nur Administrator
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class, EnsureAdmin::class])->group(function () {
    Route::put('/sicherungen/onedrive', [OneDriveController::class, 'save'])->name('backups.onedrive.save');
    Route::post('/sicherungen/onedrive/test', [OneDriveController::class, 'test'])->middleware('throttle:10,1')->name('backups.onedrive.test');
    Route::post('/sicherungen/onedrive/hochladen', [OneDriveController::class, 'upload'])->middleware('throttle:10,1')->name('backups.onedrive.upload');
    Route::post('/sicherungen/onedrive/aus', [OneDriveController::class, 'disable'])->name('backups.onedrive.off');
});
