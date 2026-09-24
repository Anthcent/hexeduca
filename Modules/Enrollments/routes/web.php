<?php

use Illuminate\Support\Facades\Route;
use Modules\Enrollments\Infrastructure\Http\Controllers\EnrollmentController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| New, authoritative routes for the Enrollments domain. The legacy
| `academic/matriculas/*` routes (Modules\Academic\...\MatriculaController)
| are kept running in parallel for backward compatibility — see plan §3/§12,
| retired only in Fase 10.
|
*/

Route::middleware(['auth', 'role:staff/admin'])->prefix('enrollments')->name('enrollments.')->group(function () {
    Route::get('/create', [EnrollmentController::class, 'create'])->name('create');
    Route::post('/', [EnrollmentController::class, 'store'])->name('store');
});
