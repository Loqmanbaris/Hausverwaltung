<?php

use App\Http\Controllers\CronController;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Ohne Anmeldung, geschützt durch den geheimen Schlüssel in der Adresse.
Route::middleware([SecurityHeaders::class, 'throttle:10,1'])->group(function () {
    Route::get('/cron/{token}', [CronController::class, 'run'])
        ->where('token', '[A-Za-z0-9]{40,64}')
        ->name('cron.run');
});
