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

Route::middleware(['auth', 'role:staff/admin'])->prefix('grade-levels')->name('grade-levels.')->group(function () {
    Route::get('/', [GradeLevelController::class, 'index'])->name('index');
    Route::post('/', [GradeLevelController::class, 'store'])->name('store');
    Route::put('/{grade_level}', [GradeLevelController::class, 'update'])->name('update');
    Route::delete('/{grade_level}', [GradeLevelController::class, 'destroy'])->name('destroy');
});
