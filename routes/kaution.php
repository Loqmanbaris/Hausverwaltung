<?php

use App\Http\Controllers\DepositController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Kautionsverwaltung (Paket 34)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/kautionen', [DepositController::class, 'index'])->name('deposit.index');
    Route::get('/mietvertraege/{lease}/kaution', [DepositController::class, 'show'])->name('deposit.show');
    Route::put('/mietvertraege/{lease}/kaution', [DepositController::class, 'update'])->name('deposit.update');
    Route::post('/mietvertraege/{lease}/kaution/buchung', [DepositController::class, 'entryStore'])->name('deposit.entry.store');
    Route::delete('/mietvertraege/{lease}/kaution/buchung/{entry}', [DepositController::class, 'entryDestroy'])->whereNumber('entry')->name('deposit.entry.destroy');
    Route::post('/mietvertraege/{lease}/kaution/uebernehmen', [DepositController::class, 'import'])->name('deposit.import');
    Route::post('/mietvertraege/{lease}/kaution/berechnen', [DepositController::class, 'preview'])->name('deposit.preview');
    Route::post('/mietvertraege/{lease}/kaution/abrechnen', [DepositController::class, 'settle'])->name('deposit.settle');
    Route::get('/mietvertraege/{lease}/kaution/abrechnung', [DepositController::class, 'letter'])->name('deposit.letter');
    Route::get('/mietvertraege/{lease}/kaution/abrechnung.pdf', [DepositController::class, 'pdf'])->name('deposit.pdf');
    Route::middleware(EnsureAdmin::class)->post('/mietvertraege/{lease}/kaution/zuruecknehmen', [DepositController::class, 'reopen'])->name('deposit.reopen');
});
