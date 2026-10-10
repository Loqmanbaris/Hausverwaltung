<?php

use App\Http\Controllers\OwnerController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\UnitController;
use Illuminate\Support\Facades\Route;

Route::pattern('owner', '[0-9]+');
Route::pattern('property', '[0-9]+');
Route::pattern('unit', '[0-9]+');
Route::pattern('tenant', '[0-9]+');
Route::pattern('account', '[0-9]+');
Route::pattern('contact', '[0-9]+');

// Voll angemeldet (Admin und Mitarbeiter, Mitarbeiter nur für freigegebene Eigentümer)
Route::middleware(['auth', \App\Http\Middleware\EnsureTwoFactor::class])->group(function () {
    Route::get('/eigentuemer', [OwnerController::class, 'index'])->name('owners.index');
    Route::get('/eigentuemer/{owner}', [OwnerController::class, 'show'])->name('owners.show');

    Route::get('/objekte', [PropertyController::class, 'index'])->name('properties.index');
    Route::get('/objekte/neu', [PropertyController::class, 'create'])->name('properties.create');
    Route::post('/objekte', [PropertyController::class, 'store'])->name('properties.store');
    Route::get('/objekte/{property}', [PropertyController::class, 'show'])->name('properties.show');
    Route::get('/objekte/{property}/bearbeiten', [PropertyController::class, 'edit'])->name('properties.edit');
    Route::put('/objekte/{property}', [PropertyController::class, 'update'])->name('properties.update');

    Route::get('/objekte/{property}/einheiten/neu', [UnitController::class, 'create'])->name('units.create');
    Route::post('/objekte/{property}/einheiten', [UnitController::class, 'store'])->name('units.store');
    Route::get('/einheiten/{unit}/bearbeiten', [UnitController::class, 'edit'])->name('units.edit');
    Route::put('/einheiten/{unit}', [UnitController::class, 'update'])->name('units.update');

    Route::get('/mieter', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/mieter/neu', [TenantController::class, 'create'])->name('tenants.create');
    Route::post('/mieter', [TenantController::class, 'store'])->name('tenants.store');
    Route::get('/mieter/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::get('/mieter/{tenant}/bearbeiten', [TenantController::class, 'edit'])->name('tenants.edit');
    Route::put('/mieter/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
    Route::post('/mieter/{tenant}/ansprechpartner', [TenantController::class, 'storeContact'])->name('tenants.contacts.store');

    // Nur Administratoren: Eigentümer anlegen und ändern, Bankverbindungen, Freigaben, Löschen
    Route::middleware(\App\Http\Middleware\EnsureAdmin::class)->group(function () {
        Route::get('/eigentuemer/neu', [OwnerController::class, 'create'])->name('owners.create');
        Route::post('/eigentuemer', [OwnerController::class, 'store'])->name('owners.store');
        Route::get('/eigentuemer/{owner}/bearbeiten', [OwnerController::class, 'edit'])->name('owners.edit');
        Route::put('/eigentuemer/{owner}', [OwnerController::class, 'update'])->name('owners.update');
        Route::delete('/eigentuemer/{owner}', [OwnerController::class, 'destroy'])->name('owners.destroy');
        Route::get('/eigentuemer/{owner}/zugriff', [OwnerController::class, 'access'])->name('owners.access');
        Route::put('/eigentuemer/{owner}/zugriff', [OwnerController::class, 'accessUpdate'])->name('owners.access.update');
        Route::post('/eigentuemer/{owner}/konten', [OwnerController::class, 'storeAccount'])->name('owners.accounts.store');
        Route::delete('/eigentuemer/{owner}/konten/{account}', [OwnerController::class, 'destroyAccount'])->name('owners.accounts.destroy');

        Route::delete('/objekte/{property}', [PropertyController::class, 'destroy'])->name('properties.destroy');
        Route::delete('/einheiten/{unit}', [UnitController::class, 'destroy'])->name('units.destroy');
        Route::delete('/mieter/{tenant}', [TenantController::class, 'destroy'])->name('tenants.destroy');
        Route::delete('/mieter/{tenant}/ansprechpartner/{contact}', [TenantController::class, 'destroyContact'])->name('tenants.contacts.destroy');
    });
});
