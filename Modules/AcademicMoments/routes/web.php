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

Route::middleware(['auth', 'module:academicmoments'])->prefix('academic-moments')->name('academic-moments.')->group(function () {
    Route::get('/', [AcademicMomentController::class, 'index'])->name('index')->middleware('permission:academicmoments.view');
    Route::post('/', [AcademicMomentController::class, 'store'])->name('store')->middleware('permission:academicmoments.manage');
    Route::put('/{academic_moment}', [AcademicMomentController::class, 'update'])->name('update')->middleware('permission:academicmoments.manage');
    Route::delete('/{academic_moment}', [AcademicMomentController::class, 'destroy'])->name('destroy')->middleware('permission:academicmoments.manage');
});
