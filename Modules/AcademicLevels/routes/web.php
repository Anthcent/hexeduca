<?php

use Illuminate\Support\Facades\Route;
use Modules\AcademicLevels\Infrastructure\Http\Controllers\AcademicLevelController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| New, authoritative routes for the AcademicLevels domain. The legacy
| `academic/niveles/*` routes (Modules\Academic\...\NivelAcademicoController)
| are kept running in parallel for backward compatibility — see plan §3/§12,
| retired only in Fase 10.
|
*/

Route::middleware(['auth', 'role:staff/admin', 'module:academiclevels'])->prefix('academic-levels')->name('academic-levels.')->group(function () {
    Route::get('/', [AcademicLevelController::class, 'index'])->name('index');
    Route::post('/', [AcademicLevelController::class, 'store'])->name('store');
    Route::put('/{academic_level}', [AcademicLevelController::class, 'update'])->name('update');
    Route::delete('/{academic_level}', [AcademicLevelController::class, 'destroy'])->name('destroy');
});
