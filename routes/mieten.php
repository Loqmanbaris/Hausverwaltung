<?php

use App\Http\Controllers\BankController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\RentIncomeController;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('lease', '[0-9]+');
Route::pattern('rent', '[0-9]+');
Route::pattern('payment', '[0-9]+');
Route::pattern('tx', '[0-9]+');
Route::pattern('import', '[0-9]+');
Route::pattern('payer', '[0-9]+');
Route::pattern('settlement', '[0-9]+');
Route::pattern('cost', '[0-9]+');
Route::pattern('meter', '[0-9]+');
Route::pattern('reading', '[0-9]+');
Route::pattern('person', '[0-9]+');
Route::pattern('notice', '[0-9]+');

// Administrator und Mitarbeiter (Mitarbeiter nur für freigegebene Eigentümer)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/mietvertraege', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('/mietvertraege/neu', [LeaseController::class, 'create'])->name('leases.create');
    Route::post('/mietvertraege', [LeaseController::class, 'store'])->name('leases.store');
    Route::get('/mietvertraege/{lease}', [LeaseController::class, 'show'])->name('leases.show');
    Route::get('/mietvertraege/{lease}/bearbeiten', [LeaseController::class, 'edit'])->name('leases.edit');
    Route::put('/mietvertraege/{lease}', [LeaseController::class, 'update'])->name('leases.update');

    Route::get('/mietvertraege/{lease}/mietaenderung', [LeaseController::class, 'rentCreate'])->name('leases.rent.create');
    Route::post('/mietvertraege/{lease}/mietaenderung', [LeaseController::class, 'rentStore'])->name('leases.rent.store');
    Route::get('/mietvertraege/{lease}/mietaenderung/{rent}/korrigieren', [LeaseController::class, 'rentEdit'])->name('leases.rent.edit');
    Route::put('/mietvertraege/{lease}/mietaenderung/{rent}', [LeaseController::class, 'rentUpdate'])->name('leases.rent.update');
    Route::delete('/mietvertraege/{lease}/mietaenderung/{rent}', [LeaseController::class, 'rentDestroy'])->name('leases.rent.destroy');

    Route::post('/mietvertraege/{lease}/zahlungen', [LeaseController::class, 'paymentStore'])->name('leases.payments.store');

    Route::get('/mieteingang', [RentIncomeController::class, 'month'])->name('rent.month');
    Route::post('/mieteingang/zahlung', [RentIncomeController::class, 'quickPay'])->name('rent.quick-pay');
    Route::get('/mieteingang/offen', [RentIncomeController::class, 'open'])->name('rent.open');

    // Kontoauszug-Import (Paket 5b)
    Route::get('/kontoauszuege', [BankController::class, 'index'])->name('bank.index');
    Route::post('/kontoauszuege', [BankController::class, 'store'])->name('bank.store');
    Route::get('/kontoauszuege/umsaetze', [BankController::class, 'transactions'])->name('bank.transactions');
    Route::post('/kontoauszuege/vorschlaege', [BankController::class, 'acceptSure'])->name('bank.accept');
    Route::post('/kontoauszuege/umsaetze/{tx}/zuordnen', [BankController::class, 'assign'])->name('bank.assign');
    Route::post('/kontoauszuege/umsaetze/{tx}/aufteilen', [BankController::class, 'split'])->name('bank.split');
    Route::delete('/kontoauszuege/umsaetze/{tx}/teile/{item}', [BankController::class, 'itemRemove'])->name('bank.item.remove');
    Route::post('/kontoauszuege/umsaetze/{tx}/keine-miete', [BankController::class, 'ignore'])->name('bank.ignore');
    Route::post('/kontoauszuege/umsaetze/{tx}/zuruecksetzen', [BankController::class, 'reset'])->name('bank.reset');
    // Umsatz bearbeiten: Angaben korrigieren, Zuordnungen einzeln ändern oder entfernen
    Route::get('/kontoauszuege/umsaetze/{tx}/bearbeiten', [BankController::class, 'edit'])->name('bank.edit');
    Route::put('/kontoauszuege/umsaetze/{tx}', [BankController::class, 'update'])->name('bank.update');
    Route::put('/kontoauszuege/umsaetze/{tx}/zahlungen/{payment}', [BankController::class, 'paymentUpdate'])->name('bank.payment.update');
    Route::delete('/kontoauszuege/umsaetze/{tx}/zahlungen/{payment}', [BankController::class, 'paymentRemove'])->name('bank.payment.remove');
    // Ausgaben mit Belegen abgleichen (Paket 9)
    Route::get('/kontoauszuege/ausgaben', [\App\Http\Controllers\BankExpenseController::class, 'index'])->name('bank.expenses');
    Route::post('/kontoauszuege/ausgaben/vorschlaege', [\App\Http\Controllers\BankExpenseController::class, 'acceptSure'])->name('bank.expenses.accept');
    Route::post('/kontoauszuege/ausgaben/{tx}/beleg', [\App\Http\Controllers\BankExpenseController::class, 'link'])->name('bank.expenses.link');
    Route::post('/kontoauszuege/ausgaben/{tx}/ohne-beleg', [\App\Http\Controllers\BankExpenseController::class, 'noReceipt'])->name('bank.expenses.none');
    Route::post('/kontoauszuege/ausgaben/{tx}/zuruecksetzen', [\App\Http\Controllers\BankExpenseController::class, 'reset'])->name('bank.expenses.reset');

    Route::delete('/mietvertraege/{lease}/zahlerkonten/{payer}', [BankController::class, 'payerDestroy'])->name('leases.payers.destroy');

    // Nebenkostenabrechnung (Paket 7)
    Route::get('/nebenkosten', [\App\Http\Controllers\NkController::class, 'index'])->name('nk.index');
    Route::post('/nebenkosten', [\App\Http\Controllers\NkController::class, 'store'])->name('nk.store');
    Route::get('/nebenkosten/{settlement}', [\App\Http\Controllers\NkController::class, 'show'])->name('nk.show');
    Route::get('/nebenkosten/{settlement}/drucken', [\App\Http\Controllers\NkController::class, 'print'])->name('nk.print');
    Route::post('/nebenkosten/{settlement}/belege', [\App\Http\Controllers\NkController::class, 'refresh'])->name('nk.refresh');
    Route::post('/nebenkosten/{settlement}/kosten', [\App\Http\Controllers\NkController::class, 'storeCost'])->name('nk.costs.store');
    Route::post('/nebenkosten/{settlement}/kosten/{cost}/umschalten', [\App\Http\Controllers\NkController::class, 'toggleCost'])->name('nk.costs.toggle');
    Route::delete('/nebenkosten/{settlement}/kosten/{cost}', [\App\Http\Controllers\NkController::class, 'destroyCost'])->name('nk.costs.destroy');
    Route::put('/nebenkosten/{settlement}/text', [\App\Http\Controllers\NkController::class, 'updateNote'])->name('nk.note');
    Route::put('/nebenkosten/{settlement}/co2', [\App\Http\Controllers\NkController::class, 'saveCo2'])->name('nk.co2');
    Route::post('/nebenkosten/{settlement}/abschliessen', [\App\Http\Controllers\NkController::class, 'finalize'])->name('nk.finalize');
    Route::post('/nebenkosten/{settlement}/vorauszahlung/{lease}', [\App\Http\Controllers\NkController::class, 'applySuggestion'])->name('nk.suggest');
    Route::delete('/nebenkosten/{settlement}', [\App\Http\Controllers\NkController::class, 'destroy'])->name('nk.destroy');

    // Anlage V (Paket 8)
    Route::get('/anlage-v', [\App\Http\Controllers\AnlageVController::class, 'index'])->name('anlage-v.index');
    Route::get('/anlage-v/drucken', [\App\Http\Controllers\AnlageVController::class, 'print'])->name('anlage-v.print');
    Route::get('/anlage-v/csv', [\App\Http\Controllers\AnlageVController::class, 'csv'])->name('anlage-v.csv');

    // Mahnwesen (Paket 10)
    Route::get('/mahnwesen', [\App\Http\Controllers\DunningController::class, 'index'])->name('dunning.index');
    Route::post('/mahnwesen', [\App\Http\Controllers\DunningController::class, 'store'])->name('dunning.store');
    Route::get('/mahnwesen/drucken', [\App\Http\Controllers\DunningController::class, 'print'])->name('dunning.print.many');
    Route::get('/mahnwesen/{notice}/drucken', [\App\Http\Controllers\DunningController::class, 'print'])->name('dunning.print');
    Route::post('/mahnwesen/{notice}/stornieren', [\App\Http\Controllers\DunningController::class, 'cancel'])->name('dunning.cancel');

    Route::get('/objekte/{property}/nebenkosten', [\App\Http\Controllers\NkSetupController::class, 'settings'])->name('nk.settings');
    Route::put('/objekte/{property}/nebenkosten', [\App\Http\Controllers\NkSetupController::class, 'saveSettings'])->name('nk.settings.save');
    Route::get('/objekte/{property}/zaehler', [\App\Http\Controllers\NkSetupController::class, 'meters'])->name('nk.meters');
    Route::post('/objekte/{property}/zaehler', [\App\Http\Controllers\NkSetupController::class, 'storeMeter'])->name('nk.meters.store');
    Route::delete('/objekte/{property}/zaehler/{meter}', [\App\Http\Controllers\NkSetupController::class, 'destroyMeter'])->name('nk.meters.destroy');
    Route::put('/objekte/{property}/zaehler/{meter}', [\App\Http\Controllers\NkSetupController::class, 'updateMeter'])->name('nk.meters.update');
    Route::post('/objekte/{property}/zaehlerstaende/import', [\App\Http\Controllers\NkSetupController::class, 'importReadings'])->name('nk.readings.import');
    Route::post('/objekte/{property}/zaehlerstaende', [\App\Http\Controllers\NkSetupController::class, 'storeReadings'])->name('nk.readings.store');
    Route::delete('/objekte/{property}/zaehlerstaende/{reading}', [\App\Http\Controllers\NkSetupController::class, 'destroyReading'])->name('nk.readings.destroy');
    Route::post('/mietvertraege/{lease}/personen', [\App\Http\Controllers\NkSetupController::class, 'storePersons'])->name('leases.persons.store');
    Route::delete('/mietvertraege/{lease}/personen/{person}', [\App\Http\Controllers\NkSetupController::class, 'destroyPersons'])->name('leases.persons.destroy');

    // Nur Administratoren
    Route::middleware(EnsureAdmin::class)->group(function () {
        Route::delete('/mietvertraege/{lease}', [LeaseController::class, 'destroy'])->name('leases.destroy');
        Route::delete('/zahlungen/{payment}', [LeaseController::class, 'paymentDestroy'])->name('payments.destroy');
        Route::delete('/kontoauszuege/{import}', [BankController::class, 'destroyImport'])->name('bank.destroy');
        Route::put('/mieteingang/erfasst-ab', [RentIncomeController::class, 'saveStart'])->name('rent.start');
        Route::put('/mahnwesen/einstellungen', [\App\Http\Controllers\DunningController::class, 'saveSettings'])->name('dunning.settings');
        Route::post('/nebenkosten/{settlement}/oeffnen', [\App\Http\Controllers\NkController::class, 'reopen'])->name('nk.reopen');

        // DATEV-Export der Mieteinnahmen (Paket 5c)
        Route::get('/datev/mieten', [\App\Http\Controllers\DatevRentController::class, 'index'])->name('datev.rent');
        Route::post('/datev/mieten/export', [\App\Http\Controllers\DatevRentController::class, 'export'])->name('datev.rent.export');
        Route::get('/datev/mieten/konten', [\App\Http\Controllers\DatevRentController::class, 'accounts'])->name('datev.rent.accounts');
        Route::put('/datev/mieten/konten', [\App\Http\Controllers\DatevRentController::class, 'saveAccounts'])->name('datev.rent.accounts.save');
    });
});
