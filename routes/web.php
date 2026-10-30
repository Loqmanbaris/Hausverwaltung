<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::middleware(SecurityHeaders::class)->group(function () {

    Route::get('/', [\App\Http\Controllers\PublicController::class, 'home'])->name('home');
    Route::get('/impressum', [\App\Http\Controllers\PublicController::class, 'imprint'])->name('imprint');
    Route::get('/datenschutz', [\App\Http\Controllers\PublicController::class, 'privacy'])->name('privacy');

    // Nicht angemeldet
    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])->name('login.store');
    });

    // Angemeldet (Passwort geprüft), zweiter Faktor noch offen oder in Einrichtung
    Route::middleware('auth')->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/zwei-faktor', [TwoFactorController::class, 'challenge'])->name('two-factor.challenge');
        Route::post('/zwei-faktor', [TwoFactorController::class, 'verify'])->name('two-factor.verify');
        Route::get('/zwei-faktor/einrichten', [TwoFactorController::class, 'setup'])->name('two-factor.setup');
        Route::post('/zwei-faktor/einrichten', [TwoFactorController::class, 'confirm'])->name('two-factor.confirm');
        Route::get('/zwei-faktor/codes', [TwoFactorController::class, 'recovery'])->name('two-factor.recovery');
    });

    // Voll angemeldet
    Route::middleware(['auth', EnsureTwoFactor::class])->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
        Route::get('/suche', [\App\Http\Controllers\SearchController::class, 'index'])->name('search');

        Route::get('/passwort', [PasswordController::class, 'edit'])->name('password.change');
        Route::put('/passwort', [PasswordController::class, 'update'])->name('password.update');

        // Nur Administratoren
        Route::middleware(EnsureAdmin::class)->group(function () {
            Route::get('/benutzer', [UserController::class, 'index'])->name('users.index');
            Route::get('/benutzer/neu', [UserController::class, 'create'])->name('users.create');
            Route::post('/benutzer', [UserController::class, 'store'])->name('users.store');
            Route::post('/benutzer/{user}/sperren', [UserController::class, 'toggle'])->name('users.toggle');
            Route::post('/benutzer/{user}/zwei-faktor-zuruecksetzen', [UserController::class, 'resetTwoFactor'])->name('users.reset-two-factor');
            Route::post('/benutzer/{user}/passwort-zuruecksetzen', [UserController::class, 'resetPassword'])->name('users.reset-password');

            Route::get('/protokoll', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('/status', [\App\Http\Controllers\StatusController::class, 'index'])->name('status');
            Route::post('/status/cron-adresse', [\App\Http\Controllers\StatusController::class, 'newCronToken'])->name('status.cron');
            Route::get('/einstellungen', [\App\Http\Controllers\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/einstellungen', [\App\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');
            Route::get('/sicherungen', [\App\Http\Controllers\BackupController::class, 'index'])->name('backups.index');
            Route::post('/sicherungen/erstellen', [\App\Http\Controllers\BackupController::class, 'run'])->name('backups.run');
            Route::put('/sicherungen/schluessel', [\App\Http\Controllers\BackupController::class, 'saveKey'])->name('backups.key');
            Route::get('/sicherungen/werkzeug', [\App\Http\Controllers\BackupController::class, 'tool'])->name('backups.tool');
            Route::get('/sicherungen/{file}/herunterladen', [\App\Http\Controllers\BackupController::class, 'download'])->where('file', 'sicherung-[0-9-]+\.bkp')->name('backups.download');
        });
    });
});

require __DIR__ . '/stammdaten.php';
require __DIR__ . '/belege.php';
require __DIR__ . '/cron.php';
require __DIR__ . '/lieferanten.php';
require __DIR__ . '/wiederkehrend.php';
require __DIR__ . '/erechnung.php';
require __DIR__ . '/datev.php';
require __DIR__ . '/mieten.php';
require __DIR__ . '/updates.php';
require __DIR__ . '/webseite.php';
require __DIR__ . '/vertraege.php';
require __DIR__ . '/uebergabe.php';
require __DIR__ . '/zinsen.php';
require __DIR__ . '/import.php';
require __DIR__ . '/seite.php';
require __DIR__ . '/uebersicht.php';
require __DIR__ . '/email.php';
require __DIR__ . '/dokumente.php';
require __DIR__ . '/finanzierung.php';
require __DIR__ . '/technik.php';
require __DIR__ . '/einrichtung.php';
require __DIR__ . '/heizung.php';
require __DIR__ . '/erhoehung.php';
require __DIR__ . '/jahresbericht.php';
require __DIR__ . '/afa.php';
require __DIR__ . '/interessenten.php';
require __DIR__ . '/vertragsverwaltung.php';
require __DIR__ . '/kalender.php';
require __DIR__ . '/kaution.php';
require __DIR__ . '/loeschfristen.php';
require __DIR__ . '/mieterportal.php';
require __DIR__ . '/ausgangsrechnung.php';
require __DIR__ . '/onedrive-sicherung.php';
require __DIR__ . '/meintag.php';
require __DIR__ . '/pwa.php';
require __DIR__ . '/rechnungen.php';
