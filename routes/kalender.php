<?php

use App\Http\Controllers\CalendarController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Kalender-Abo (Paket 33)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/kalender-abo', [CalendarController::class, 'index'])->name('calendar.index');
    Route::post('/kalender-abo', [CalendarController::class, 'create'])->name('calendar.create');
    Route::put('/kalender-abo', [CalendarController::class, 'update'])->name('calendar.update');
    Route::delete('/kalender-abo', [CalendarController::class, 'destroy'])->name('calendar.destroy');
});

// Der Feed selbst: ohne Anmeldung, nur mit geheimem Link (Kalender-Apps können sich nicht anmelden)
Route::middleware(['throttle:60,1'])->get('/kalender/{token}.ics', [CalendarController::class, 'feed'])->where('token', '[A-Za-z0-9]{40}')->name('calendar.feed');
