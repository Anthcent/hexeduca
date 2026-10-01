<?php

use Illuminate\Support\Facades\Route;
use Modules\GradeLevels\Infrastructure\Http\Controllers\GradeLevelController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| New, authoritative routes for the GradeLevels domain. The legacy
| `academic/grados/*` routes (Modules\Academic\...\GradoController) are
| kept running in parallel for backward compatibility — see plan §3/§12,
| retired only in Fase 10.
|
*/

Route::middleware(['auth', 'module:gradelevels'])->prefix('grade-levels')->name('grade-levels.')->group(function () {
    Route::get('/', [GradeLevelController::class, 'index'])->name('index')->middleware('permission:gradelevels.view');
    Route::post('/', [GradeLevelController::class, 'store'])->name('store')->middleware('permission:gradelevels.manage');
    Route::put('/{grade_level}', [GradeLevelController::class, 'update'])->name('update')->middleware('permission:gradelevels.manage');
    Route::delete('/{grade_level}', [GradeLevelController::class, 'destroy'])->name('destroy')->middleware('permission:gradelevels.manage');
});
