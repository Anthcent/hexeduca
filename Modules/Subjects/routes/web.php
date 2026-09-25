<?php

use Illuminate\Support\Facades\Route;
use Modules\Subjects\Infrastructure\Http\Controllers\PlanAssignmentsController;
use Modules\Subjects\Infrastructure\Http\Controllers\StudyPlansController;
use Modules\Subjects\Infrastructure\Http\Controllers\SubjectsController;

/*
| `module:subjects` runs before the permission check, so a school that
| cannot use the module always gets 404, never a 403 that leaks it exists.
| Only staff hold `subjects.manage`.
*/

Route::middleware(['auth', 'module:subjects', 'permission:subjects.manage'])
    ->prefix('study-plans')
    ->name('subjects.')
    ->group(function () {
        Route::get('/', [StudyPlansController::class, 'index'])->name('index');
        Route::post('/', [StudyPlansController::class, 'store'])->name('plans.store');

        Route::prefix('assignments')->name('assignments.')->controller(PlanAssignmentsController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::post('/', 'store')->name('store');
            Route::post('/offer-override', 'overrideOffer')->name('offer-override');
            Route::delete('/{assignment}', 'destroy')->whereNumber('assignment')->name('destroy');
            Route::put('/{assignment}/exclusions', 'setExclusion')->whereNumber('assignment')->name('exclusions');
        });

        Route::prefix('{plan}')->whereNumber('plan')->group(function () {
            Route::get('/', [StudyPlansController::class, 'show'])->name('plans.show');
            Route::put('/', [StudyPlansController::class, 'update'])->name('plans.update');
            Route::delete('/', [StudyPlansController::class, 'destroy'])->name('plans.destroy');
            Route::post('/archive', [StudyPlansController::class, 'archive'])->name('plans.archive');
            Route::get('/reactivation', [StudyPlansController::class, 'reviewReactivation'])->name('plans.reactivation');
            Route::post('/reactivate', [StudyPlansController::class, 'reactivate'])->name('plans.reactivate');

            Route::prefix('subjects')->name('subjects.')->controller(SubjectsController::class)->group(function () {
                Route::post('/', 'store')->name('store');
                Route::put('/{subject}', 'update')->whereNumber('subject')->name('update');
                Route::delete('/{subject}', 'destroy')->whereNumber('subject')->name('destroy');
                Route::post('/{subject}/archive', 'archive')->whereNumber('subject')->name('archive');
                Route::get('/{subject}/reactivation', 'reviewReactivation')->whereNumber('subject')->name('reactivation');
                Route::post('/{subject}/reactivate', 'reactivate')->whereNumber('subject')->name('reactivate');
            });
        });
    });
