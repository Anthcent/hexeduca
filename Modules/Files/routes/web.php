<?php

use Illuminate\Support\Facades\Route;
use Modules\Files\Infrastructure\Http\Controllers\FilesController;

/*
| `module:files` runs before the permission check, so a school that cannot
| use the module always gets 404, never a 403 that leaks it exists. Whether
| a user may delete a given file (own vs. any) is a domain rule checked by
| the DeleteFile use case.
*/

Route::middleware(['auth', 'module:files'])->prefix('files')->name('files.')->group(function () {
    Route::middleware('permission:files.view')->group(function () {
        Route::get('/', [FilesController::class, 'index'])->name('index');
        Route::get('/{file}/download', [FilesController::class, 'download'])->whereNumber('file')->name('download');
        Route::delete('/{file}', [FilesController::class, 'destroy'])->whereNumber('file')->name('destroy');
        Route::post('/', [FilesController::class, 'store'])->middleware('permission:files.upload')->name('store');
    });
});
