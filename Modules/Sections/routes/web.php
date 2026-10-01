<?php

use Illuminate\Support\Facades\Route;
use Modules\Sections\Infrastructure\Http\Controllers\SectionController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| New, authoritative routes for the Sections domain. The legacy
| `academic/secciones/*` routes (Modules\Academic\...\SeccionController)
| are kept running in parallel for backward compatibility — see plan
| §3/§12, retired only in Fase 10.
|
*/

Route::middleware(['auth', 'module:sections'])->prefix('sections')->name('sections.')->group(function () {
    Route::get('/', [SectionController::class, 'index'])->name('index')->middleware('permission:sections.view');
    Route::post('/', [SectionController::class, 'store'])->name('store')->middleware('permission:sections.manage');
    Route::put('/{section}', [SectionController::class, 'update'])->name('update')->middleware('permission:sections.manage');
    Route::delete('/{section}', [SectionController::class, 'destroy'])->name('destroy')->middleware('permission:sections.manage');
});
