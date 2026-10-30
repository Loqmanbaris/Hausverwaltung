<?php

use App\Http\Controllers\ReportController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Jahresbericht je Eigentümer (Paket 28)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/jahresbericht', [ReportController::class, 'index'])->name('report.index');
    Route::get('/jahresbericht/drucken', [ReportController::class, 'print'])->name('report.print');
    Route::get('/jahresbericht/pdf', [ReportController::class, 'pdf'])->name('report.pdf');
});
