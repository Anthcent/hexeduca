<?php

use Illuminate\Support\Facades\Route;
use Modules\AcademicMoments\Infrastructure\Http\Controllers\AcademicMomentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| New, authoritative routes for the AcademicMoments domain. Legacy
| MomentoAcademico behavior lived only inside Modules\Academic without a
| dedicated route/controller of its own — no legacy route to keep in
| parallel here.
|
*/

Route::middleware(['auth', 'role:staff/admin', 'module:academicmoments'])->prefix('academic-moments')->name('academic-moments.')->group(function () {
    Route::get('/', [AcademicMomentController::class, 'index'])->name('index');
    Route::post('/', [AcademicMomentController::class, 'store'])->name('store');
    Route::put('/{academic_moment}', [AcademicMomentController::class, 'update'])->name('update');
    Route::delete('/{academic_moment}', [AcademicMomentController::class, 'destroy'])->name('destroy');
});
