<?php

use App\Tenancy\Http\Middleware\RequireLandlordHost;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Infrastructure\Http\Controllers\SchoolController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Landlord-only: institution (School/tenant) management. Reachable only on
| the landlord host by an authenticated super-admin — see design.md "Login
| on the landlord host is restricted to the super-admin".
|
*/

Route::middleware(['auth', 'role:super-admin', RequireLandlordHost::class])
    ->prefix('instituciones')
    ->name('admin.schools.')
    ->group(function () {
        Route::get('/', [SchoolController::class, 'index'])->name('index');
        Route::post('/', [SchoolController::class, 'store'])->name('store');
        Route::put('/{school}', [SchoolController::class, 'update'])->name('update');
        Route::post('/{school}/toggle-active', [SchoolController::class, 'toggleActive'])->name('toggle-active');
    });
