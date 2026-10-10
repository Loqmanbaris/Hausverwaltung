<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\PublicListingController;
use App\Http\Middleware\EnsureTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Support\Facades\Route;

Route::pattern('listing', '[0-9]+');
Route::pattern('photo', '[0-9]+');
Route::pattern('booking', '[0-9]+');
Route::pattern('inquiry', '[0-9]+');

// Öffentlich (Paket 11)
Route::middleware(SecurityHeaders::class)->group(function () {
    Route::get('/wohnungen', [PublicListingController::class, 'wohnen'])->name('public.wohnen');
    Route::get('/montage-wohnungen', [PublicListingController::class, 'montage'])->name('public.montage');
    Route::get('/gewerbe', [PublicListingController::class, 'gewerbe'])->name('public.gewerbe');
    Route::get('/angebote/{slug}', [PublicListingController::class, 'show'])->where('slug', '[a-z0-9-]{3,140}')->name('public.listing');
    Route::get('/kontakt', [PublicListingController::class, 'contact'])->name('public.contact');
    Route::post('/kontakt', [PublicListingController::class, 'send'])->name('public.send');
    Route::get('/kontakt/danke', [PublicListingController::class, 'thanks'])->name('public.thanks');
    Route::get('/bilder/{photo}-{size}.jpg', [PublicListingController::class, 'photo'])->where('size', '[ls]')->name('public.photo');
});

// Verwaltung
Route::middleware([SecurityHeaders::class, 'auth', EnsureTwoFactor::class])->group(function () {
    Route::get('/inserate', [ListingController::class, 'index'])->name('listings.index');
    Route::get('/inserate/neu', [ListingController::class, 'create'])->name('listings.create');
    Route::post('/inserate', [ListingController::class, 'store'])->name('listings.store');
    Route::get('/inserate/{listing}', [ListingController::class, 'edit'])->name('listings.edit');
    Route::put('/inserate/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::post('/inserate/{listing}/status', [ListingController::class, 'status'])->name('listings.status');
    Route::delete('/inserate/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');
    Route::post('/inserate/{listing}/fotos', [ListingController::class, 'photoStore'])->name('listings.photos.store');
    Route::put('/inserate/{listing}/fotos/{photo}', [ListingController::class, 'photoUpdate'])->name('listings.photos.update');
    Route::delete('/inserate/{listing}/fotos/{photo}', [ListingController::class, 'photoDestroy'])->name('listings.photos.destroy');
    Route::get('/inserate/{listing}/fotos/{photo}/{size}', [ListingController::class, 'photoShow'])->where('size', '[ls]')->name('listings.photos.show');
    Route::get('/inserate/{listing}/portale', [ListingController::class, 'portal'])->name('listings.portal');
    Route::get('/inserate/{listing}/fotos.zip', [ListingController::class, 'photosZip'])->name('listings.photos.zip');

    Route::get('/einheiten/{unit}/belegung', [BookingController::class, 'index'])->name('bookings.index');
    Route::post('/einheiten/{unit}/belegung', [BookingController::class, 'store'])->name('bookings.store');
    Route::put('/einheiten/{unit}/belegung/{booking}', [BookingController::class, 'update'])->name('bookings.update');
    Route::delete('/einheiten/{unit}/belegung/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');

    Route::get('/anfragen', [InquiryController::class, 'index'])->name('inquiries.index');
    Route::get('/anfragen/{inquiry}', [InquiryController::class, 'show'])->name('inquiries.show');
    Route::put('/anfragen/{inquiry}', [InquiryController::class, 'update'])->name('inquiries.update');
    Route::delete('/anfragen/{inquiry}', [InquiryController::class, 'destroy'])->name('inquiries.destroy');
});
