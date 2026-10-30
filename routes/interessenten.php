<?php

use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\DisclosureController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('applicant', '[0-9]+');
Route::pattern('viewing', '[0-9]+');

// Interessenten (Paket 31)
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/interessenten', [ApplicantController::class, 'index'])->name('applicants.index');
    Route::get('/interessenten/neu', [ApplicantController::class, 'create'])->name('applicants.create');
    Route::post('/interessenten', [ApplicantController::class, 'store'])->name('applicants.store');
    Route::post('/anfragen/{inquiry}/interessent', [ApplicantController::class, 'fromInquiry'])->whereNumber('inquiry')->name('applicants.from_inquiry');
    Route::get('/interessenten/{applicant}', [ApplicantController::class, 'show'])->name('applicants.show');
    Route::get('/interessenten/{applicant}/bearbeiten', [ApplicantController::class, 'edit'])->name('applicants.edit');
    Route::put('/interessenten/{applicant}', [ApplicantController::class, 'update'])->name('applicants.update');
    Route::delete('/interessenten/{applicant}', [ApplicantController::class, 'destroy'])->name('applicants.destroy');
    Route::post('/interessenten/{applicant}/stand', [ApplicantController::class, 'status'])->name('applicants.status');

    Route::post('/interessenten/{applicant}/besichtigung', [ApplicantController::class, 'viewingStore'])->name('applicants.viewing.store');
    Route::put('/besichtigungen/{viewing}', [ApplicantController::class, 'viewingUpdate'])->name('applicants.viewing.update');
    Route::delete('/besichtigungen/{viewing}', [ApplicantController::class, 'viewingDestroy'])->name('applicants.viewing.destroy');

    Route::post('/interessenten/{applicant}/selbstauskunft/anfordern', [ApplicantController::class, 'disclosureRequest'])->name('applicants.disclosure.request');
    Route::post('/interessenten/{applicant}/selbstauskunft/neuer-link', [ApplicantController::class, 'disclosureRenew'])->name('applicants.disclosure.renew');
    Route::get('/interessenten/{applicant}/selbstauskunft/formular', [ApplicantController::class, 'disclosurePrint'])->name('applicants.disclosure.print');
    Route::get('/interessenten/{applicant}/selbstauskunft/eintragen', [ApplicantController::class, 'disclosureManual'])->name('applicants.disclosure.manual');
    Route::post('/interessenten/{applicant}/selbstauskunft/eintragen', [ApplicantController::class, 'disclosureManualStore'])->name('applicants.disclosure.manual.store');

    Route::get('/interessenten/{applicant}/brief/{kind}', [ApplicantController::class, 'letter'])->where('kind', 'zusage|absage')->name('applicants.letter');
    Route::post('/interessenten/{applicant}/mieter', [ApplicantController::class, 'convert'])->name('applicants.convert');
});

// Selbstauskunft für Interessenten (ohne Anmeldung, nur mit geheimem Link)
Route::middleware([SecurityHeaders::class, 'throttle:30,1'])->group(function () {
    Route::get('/selbstauskunft/{token}', [DisclosureController::class, 'form'])->where('token', '[A-Za-z0-9]{48}')->name('disclosure.form');
    Route::post('/selbstauskunft/{token}', [DisclosureController::class, 'submit'])->where('token', '[A-Za-z0-9]{48}')->middleware('throttle:20,1')->name('disclosure.submit');
});
