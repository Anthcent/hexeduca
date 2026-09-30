<?php

use Illuminate\Support\Facades\Route;
use Modules\Grades\Infrastructure\Http\Controllers\GradesController;

/*
| `module:grades` runs before the permission check, so a school that cannot
| use the module always gets 404, never a 403 that leaks it exists. Staff
| and teachers hold `grades.manage`; which subjects a teacher may touch is
| decided per request by GradeAccess (their teaching assignments).
*/

Route::middleware(['auth', 'module:grades', 'permission:grades.manage'])
    ->prefix('grades')
    ->name('grades.')
    ->controller(GradesController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/plan', 'plan')->name('plan');
        Route::put('/plan', 'savePlan')->name('plan.save');
        Route::get('/sheets/{plan}', 'sheet')->whereNumber('plan')->name('sheet');
        Route::put('/sheets/{plan}/cells', 'record')->whereNumber('plan')->name('record');
        Route::get('/sheets/{plan}/history', 'history')->whereNumber('plan')->name('history');
        Route::post('/sheets/{plan}/correction', 'openCorrection')->whereNumber('plan')->name('correction.open');
        Route::delete('/sheets/{plan}/correction', 'closeCorrection')->whereNumber('plan')->name('correction.close');
        Route::get('/monitor', 'monitor')->name('monitor');
    });
