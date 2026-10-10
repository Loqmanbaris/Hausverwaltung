<?php

use App\Http\Controllers\ContractTemplateController;
use App\Http\Controllers\LeaseContractController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('contract', '[0-9]+');

// Mietverträge als Dokument (Paket 12)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/mietvertraege/{lease}/vertrag/neu', [LeaseContractController::class, 'create'])->name('contracts.create');
    Route::post('/mietvertraege/{lease}/vertrag', [LeaseContractController::class, 'store'])->name('contracts.store');
    Route::get('/vertraege/{contract}', [LeaseContractController::class, 'show'])->name('contracts.show');
    Route::get('/vertraege/{contract}/bearbeiten', [LeaseContractController::class, 'edit'])->name('contracts.edit');
    Route::put('/vertraege/{contract}', [LeaseContractController::class, 'update'])->name('contracts.update');
    Route::get('/vertraege/{contract}/drucken', [LeaseContractController::class, 'print'])->name('contracts.print');
    Route::get('/vertraege/{contract}/unterschreiben', [LeaseContractController::class, 'signForm'])->name('contracts.sign');
    Route::post('/vertraege/{contract}/unterschreiben', [LeaseContractController::class, 'signStore'])->name('contracts.sign.store');
    Route::post('/vertraege/{contract}/scan', [LeaseContractController::class, 'scanStore'])->name('contracts.scan');
    Route::get('/vertraege/{contract}/scan', [LeaseContractController::class, 'scanShow'])->name('contracts.scan.show');
    Route::delete('/vertraege/{contract}', [LeaseContractController::class, 'destroy'])->name('contracts.destroy');

    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::post('/vertraege/{contract}/stornieren', [LeaseContractController::class, 'cancel'])->name('contracts.cancel');
        Route::get('/vertragsvorlagen', [ContractTemplateController::class, 'index'])->name('contract-templates.index');
        Route::get('/vertragsvorlagen/{kind}', [ContractTemplateController::class, 'edit'])->where('kind', 'wohnen|gewerbe|montage')->name('contract-templates.edit');
        Route::put('/vertragsvorlagen/{kind}', [ContractTemplateController::class, 'update'])->where('kind', 'wohnen|gewerbe|montage')->name('contract-templates.update');
        Route::post('/vertragsvorlagen/{kind}/zuruecksetzen', [ContractTemplateController::class, 'reset'])->where('kind', 'wohnen|gewerbe|montage')->name('contract-templates.reset');
    });
});
