<?php

use Illuminate\Support\Facades\Route;
use Modules\Grades\Infrastructure\Http\Controllers\GradeController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| First real domain routes for Grades (Fase 8) — replaces the generic
| Route::resource scaffold. Teachers record/update their own grades;
 | staff/admin may act on behalf of the same-school teacher assigned to the
 | offer — see GradeController and RecordGrade (plan §16/§11).
|
*/

Route::middleware(['auth', 'role:teacher|staff/admin', 'module:grades'])->prefix('grades')->name('grades.')->group(function () {
    Route::get('/create', [GradeController::class, 'create'])->name('create');
    Route::post('/', [GradeController::class, 'store'])->name('store');
    Route::put('/{grade}', [GradeController::class, 'update'])->name('update');
    Route::delete('/{grade}', [GradeController::class, 'destroy'])->name('destroy');
});
