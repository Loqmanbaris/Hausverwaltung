<?php

use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\VendorPortalController;
use App\Http\Controllers\WorkOrderController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

// Wartung, Schadensfälle und Handwerkeraufträge (Paket 24)
Route::pattern('order', '[0-9]+');
Route::pattern('task', '[0-9]+');
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/auftraege', [WorkOrderController::class, 'index'])->name('workorders.index');
    Route::get('/auftraege/neu', [WorkOrderController::class, 'create'])->name('workorders.create');
    Route::post('/auftraege', [WorkOrderController::class, 'store'])->name('workorders.store');
    Route::get('/auftraege/{order}', [WorkOrderController::class, 'show'])->name('workorders.show');
    Route::put('/auftraege/{order}', [WorkOrderController::class, 'update'])->name('workorders.update');
    Route::post('/auftraege/{order}/vergeben', [WorkOrderController::class, 'assign'])->name('workorders.assign');
    Route::post('/auftraege/{order}/termin', [WorkOrderController::class, 'appointment'])->name('workorders.appointment');
    Route::post('/auftraege/{order}/erledigt', [WorkOrderController::class, 'done'])->name('workorders.done');
    Route::post('/auftraege/{order}/rechnung', [WorkOrderController::class, 'bill'])->name('workorders.bill');
    Route::post('/auftraege/{order}/stornieren', [WorkOrderController::class, 'cancel'])->name('workorders.cancel');
    Route::post('/auftraege/{order}/oeffnen', [WorkOrderController::class, 'reopen'])->name('workorders.reopen');
    Route::post('/auftraege/{order}/neuer-link', [WorkOrderController::class, 'newLink'])->name('workorders.link');
    Route::post('/auftraege/{order}/notiz', [WorkOrderController::class, 'note'])->name('workorders.note');
    Route::post('/auftraege/{order}/fotos', [WorkOrderController::class, 'photoStore'])->name('workorders.photos.store');
    Route::get('/auftraege/{order}/fotos/{photo}/{size}', [WorkOrderController::class, 'photoShow'])->where('size', 's|l')->name('workorders.photos.show');
    Route::delete('/auftraege/{order}/fotos/{photo}', [WorkOrderController::class, 'photoDestroy'])->name('workorders.photos.destroy');
    Route::get('/auftraege/{order}/auftrag.pdf', [WorkOrderController::class, 'pdf'])->name('workorders.pdf');
    Route::post('/uebergabe/{protocol}/schadensfaelle', [WorkOrderController::class, 'fromHandover'])->name('workorders.from-handover');

    Route::get('/wartung', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/wartung/neu', [MaintenanceController::class, 'create'])->name('maintenance.create');
    Route::post('/wartung', [MaintenanceController::class, 'store'])->name('maintenance.store');
    Route::post('/wartung/standard', [MaintenanceController::class, 'defaults'])->name('maintenance.defaults');
    Route::get('/wartung/{task}/bearbeiten', [MaintenanceController::class, 'edit'])->name('maintenance.edit');
    Route::put('/wartung/{task}', [MaintenanceController::class, 'update'])->name('maintenance.update');
    Route::delete('/wartung/{task}', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy');
    Route::post('/wartung/{task}/erledigt', [MaintenanceController::class, 'done'])->name('maintenance.done');
});

// Auftragsseite für Handwerker (ohne Anmeldung, nur mit geheimem Link)
Route::middleware([SecurityHeaders::class, 'throttle:60,1'])->group(function () {
    Route::get('/auftrag/{token}', [VendorPortalController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->name('vendor.order');
    Route::post('/auftrag/{token}/termin', [VendorPortalController::class, 'appointment'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:10,1')->name('vendor.appointment');
    Route::post('/auftrag/{token}/erledigt', [VendorPortalController::class, 'done'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:10,1')->name('vendor.done');
    Route::get('/auftrag/{token}/foto/{photo}/{size}', [VendorPortalController::class, 'photo'])->where(['token' => '[A-Za-z0-9]{48}', 'size' => 's|l'])->name('vendor.photo');
});
