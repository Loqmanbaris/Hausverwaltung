<?php

use App\Http\Controllers\MailController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// E-Mail-Versand (Paket 21)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/email', [MailController::class, 'index'])->name('mail.index');
    Route::get('/email/neu', [MailController::class, 'compose'])->name('mail.compose');
    Route::get('/email/vorschau.pdf', [MailController::class, 'preview'])->name('mail.preview');
    Route::get('/dokument.pdf', [MailController::class, 'document'])->name('documents.pdf');
    Route::post('/email/senden', [MailController::class, 'send'])->middleware('throttle:30,1')->name('mail.send');
});

Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class, EnsureAdmin::class])->group(function () {
    Route::get('/email/einstellungen', [MailSettingsController::class, 'edit'])->name('mail.settings');
    Route::put('/email/einstellungen', [MailSettingsController::class, 'update'])->name('mail.settings.update');
    Route::post('/email/test', [MailSettingsController::class, 'test'])->middleware('throttle:10,1')->name('mail.test');
});
