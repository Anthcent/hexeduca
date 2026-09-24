<?php

use Illuminate\Support\Facades\Route;
use Modules\Notifications\Infrastructure\Http\Controllers\NotificationsController;

/*
| `module:notifications` runs before the permission check, so a school that
| cannot use the module always gets 404, never a 403 that leaks it exists.
*/

Route::middleware(['auth', 'module:notifications'])->prefix('notifications')->name('notifications.')->group(function () {
    Route::middleware('permission:notifications.view')->group(function () {
        Route::get('/', [NotificationsController::class, 'index'])->name('index');
        Route::patch('/{notification}/read', [NotificationsController::class, 'markAsRead'])->whereNumber('notification')->name('read');
        Route::post('/read-all', [NotificationsController::class, 'markAllAsRead'])->name('read-all');
    });

    Route::middleware('permission:notifications.send')->group(function () {
        Route::get('/create', [NotificationsController::class, 'create'])->name('create');
        Route::post('/', [NotificationsController::class, 'store'])->name('store');
    });
});
