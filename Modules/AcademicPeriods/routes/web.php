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

Route::middleware(['auth', 'role:staff/admin', 'module:academicperiods'])->prefix('academic-periods')->name('academic-periods.')->group(function () {
    Route::get('/', [AcademicPeriodController::class, 'index'])->name('index');
    Route::post('/', [AcademicPeriodController::class, 'store'])->name('store');
    Route::put('/{academic_period}', [AcademicPeriodController::class, 'update'])->name('update');
    Route::delete('/{academic_period}', [AcademicPeriodController::class, 'destroy'])->name('destroy');
    Route::post('/{academic_period}/activate', [AcademicPeriodController::class, 'activate'])->name('activate');
});
