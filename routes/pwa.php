<?php

use App\Http\Controllers\PwaController;
use Illuminate\Support\Facades\Route;

// App zum Installieren (Paket 43): ohne Anmeldung, enthält keine Daten
Route::middleware(['throttle:120,1'])->group(function () {
    Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
    Route::get('/sw.js', [PwaController::class, 'worker'])->name('pwa.worker');
    Route::get('/app-icon-{size}.png', [PwaController::class, 'icon'])->where('size', '192|512')->name('pwa.icon');
    Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');
});
