<?php

use App\Http\Controllers\Storefront\ServicesController;
use App\Http\Middleware\EnsureServicesTemplate;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Service-business routes
|--------------------------------------------------------------------------
| Used by templates whose class returns supportsServices() === true (Plumbing
| Services). In catalogue templates the controller answers 404 so these URLs
| never shadow anything there. Included from web.php before the /{slug} page
| catch-all.
*/

Route::middleware(EnsureServicesTemplate::class)->group(function () {
    Route::get('/services', [ServicesController::class, 'index'])->name('services.index');
    Route::get('/services/{slug}', [ServicesController::class, 'show'])->name('services.show');
    Route::get('/emergency', [ServicesController::class, 'emergency'])->name('services.emergency');
    Route::get('/problems/{slug}', [ServicesController::class, 'problem'])->name('services.problem');

    Route::get('/service-areas', [ServicesController::class, 'areas'])->name('areas.index');
    Route::get('/service-areas/{slug}', [ServicesController::class, 'area'])->name('areas.show');

    Route::get('/projects', [ServicesController::class, 'projects'])->name('projects.index');

    Route::get('/book', [ServicesController::class, 'book'])->name('booking.create');
    Route::post('/book', [ServicesController::class, 'store'])->middleware('throttle:10,1')->name('booking.store');
    Route::get('/book/{reference}/confirmation', [ServicesController::class, 'confirmation'])->name('booking.confirmation');

    Route::get('/quote', [ServicesController::class, 'quote'])->name('quote.create');
    Route::post('/quote', [ServicesController::class, 'storeQuote'])->middleware('throttle:10,1')->name('quote.store');

    Route::match(['get', 'post'], '/bookings', [ServicesController::class, 'bookings'])->name('bookings');
    Route::get('/account/bookings', [ServicesController::class, 'accountBookings'])->middleware('auth')->name('account.bookings');

    Route::get('/api/services', [ServicesController::class, 'searchJson'])->name('api.services');
    Route::post('/services/contact', [ServicesController::class, 'contact'])->middleware('throttle:5,1')->name('services.contact');
});
