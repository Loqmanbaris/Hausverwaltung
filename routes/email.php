<?php

use App\Http\Controllers\InboxController;
use App\Http\Controllers\MailController;
use App\Http\Controllers\MailSettingsController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// E-Mail-Versand (Paket 21)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/email', [InboxController::class, 'sentFolder'])->name('mail.index'); // Ordner „Gesendet“ der Outlook-Ansicht
    Route::get('/email/neu', [MailController::class, 'compose'])->name('mail.compose');
    Route::get('/email/vorschau.pdf', [MailController::class, 'preview'])->name('mail.preview');
    Route::get('/dokument.pdf', [MailController::class, 'document'])->name('documents.pdf');
    Route::post('/email/senden', [MailController::class, 'send'])->middleware('throttle:30,1')->name('mail.send');

    // Posteingang (E-Mail-Empfang über IMAP) und freie E-Mails
    Route::get('/posteingang', [InboxController::class, 'index'])->name('inbox.index');
    Route::post('/posteingang/abrufen', [InboxController::class, 'fetch'])->middleware('throttle:6,1')->name('inbox.fetch');
    Route::get('/posteingang/{id}', [InboxController::class, 'show'])->whereNumber('id')->name('inbox.show');
    Route::get('/posteingang/{id}/inhalt', [InboxController::class, 'html'])->whereNumber('id')->name('inbox.html');
    Route::get('/posteingang/{id}/anhang/{n}', [InboxController::class, 'attachment'])->whereNumber(['id', 'n'])->name('inbox.attachment');
    Route::post('/posteingang/{id}/archiv', [InboxController::class, 'archive'])->whereNumber('id')->name('inbox.archive');
    Route::post('/posteingang/{id}/ungelesen', [InboxController::class, 'unread'])->whereNumber('id')->name('inbox.unread');
    Route::post('/posteingang/{id}/antworten', [InboxController::class, 'reply'])->whereNumber('id')->middleware('throttle:30,1')->name('inbox.reply');
    Route::get('/email/schreiben', [InboxController::class, 'write'])->name('mail.write');
    Route::post('/email/schreiben', [InboxController::class, 'sendNew'])->middleware('throttle:30,1')->name('mail.write.send');
});

Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class, EnsureAdmin::class])->group(function () {
    Route::get('/email/einstellungen', [MailSettingsController::class, 'edit'])->name('mail.settings');
    Route::put('/email/einstellungen', [MailSettingsController::class, 'update'])->name('mail.settings.update');
    Route::post('/email/test', [MailSettingsController::class, 'test'])->middleware('throttle:10,1')->name('mail.test');
    Route::post('/email/empfang-test', [MailSettingsController::class, 'testReceive'])->middleware('throttle:10,1')->name('mail.test.receive');
});
