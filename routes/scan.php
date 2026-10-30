<?php

use App\Http\Controllers\ScanAssetController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Beleg-Scanner (Paket 47): Dateien für Zuschneiden, PDF und Texterkennung, nur für angemeldete Benutzer
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/scan/ocr/{file}', [ScanAssetController::class, 'show'])->where('file', '[A-Za-z0-9._-]+')->name('scan.asset');
});
