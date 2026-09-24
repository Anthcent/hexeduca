<?php

use App\Tenancy\Http\Middleware\RequireLandlordHost;
use Illuminate\Support\Facades\Route;
use Modules\Admin\Infrastructure\Http\Controllers\ModuleController;
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

/*
|--------------------------------------------------------------------------
| Module platform management — R3
|--------------------------------------------------------------------------
|
| Landlord-only: global module activation and per-school entitlements. See
| sdd/module-developer-platform R3.2.
|
*/

Route::middleware(['auth', 'role:super-admin', RequireLandlordHost::class])
    ->prefix('modulos')
    ->name('admin.modules.')
    ->group(function () {
        Route::get('/', [ModuleController::class, 'index'])->name('index');
        Route::post('/sync', [ModuleController::class, 'sync'])->name('sync');
        Route::post('/{module}/toggle-active', [ModuleController::class, 'toggleActive'])->name('toggle-active');
        Route::post('/{module}/schools/{school}/toggle-entitlement', [ModuleController::class, 'toggleEntitlement'])->name('toggle-entitlement');
    });
