<?php

use Illuminate\Support\Facades\Route;
use Modules\TeachingAssignments\Infrastructure\Http\Controllers\TeachingAssignmentsController;

/*
| `module:teachingassignments` runs before the permission check, so a school
| that cannot use the module always gets 404, never a 403 that leaks it
| exists. Only staff hold `teachingassignments.manage`.
*/

Route::middleware(['auth', 'module:teachingassignments', 'permission:teachingassignments.manage'])
    ->prefix('teaching-assignments')
    ->name('teachingassignments.')
    ->controller(TeachingAssignmentsController::class)
    ->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::delete('/{assignment}', 'destroy')->whereNumber('assignment')->name('destroy');
        Route::put('/offers/{offer}/coordinator', 'coordinator')->whereNumber('offer')->name('coordinator');
    });
