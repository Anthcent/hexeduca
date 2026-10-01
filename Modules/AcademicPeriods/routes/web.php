<?php

use Illuminate\Support\Facades\Route;
use Modules\AcademicPeriods\Infrastructure\Http\Controllers\AcademicPeriodController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| New, authoritative routes for the AcademicPeriods domain. The legacy
| `academic/periodos/*` routes (Modules\Academic\...\PeriodoAcademicoController)
| are kept running in parallel for backward compatibility — see plan §3/§12,
| retired only in Fase 10.
|
*/

Route::middleware(['auth', 'module:academicperiods'])->prefix('academic-periods')->name('academic-periods.')->group(function () {
    Route::get('/', [AcademicPeriodController::class, 'index'])->name('index')->middleware('permission:academicperiods.view');
    Route::post('/', [AcademicPeriodController::class, 'store'])->name('store')->middleware('permission:academicperiods.manage');
    Route::put('/{academic_period}', [AcademicPeriodController::class, 'update'])->name('update')->middleware('permission:academicperiods.manage');
    Route::delete('/{academic_period}', [AcademicPeriodController::class, 'destroy'])->name('destroy')->middleware('permission:academicperiods.manage');
    Route::post('/{academic_period}/activate', [AcademicPeriodController::class, 'activate'])->name('activate')->middleware('permission:academicperiods.manage');
});
