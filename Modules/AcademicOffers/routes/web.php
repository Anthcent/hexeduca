<?php

use Illuminate\Support\Facades\Route;
use Modules\AcademicOffers\Infrastructure\Http\Controllers\AcademicOfferController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| New, authoritative routes for the AcademicOffers domain. The legacy
| `academic/ofertas/*` routes (Modules\Academic\...\OfertaAcademicaController)
| are kept running in parallel for backward compatibility — see plan §3/§12,
| retired only in Fase 10.
|
*/

Route::middleware(['auth', 'role:staff/admin'])->prefix('academic-offers')->name('academic-offers.')->group(function () {
    Route::get('/create', [AcademicOfferController::class, 'create'])->name('create');
    Route::post('/', [AcademicOfferController::class, 'store'])->name('store');
});
