<?php

use App\Tenancy\Http\Middleware\RequireLandlordHost;
use Illuminate\Support\Facades\Route;
use Modules\Users\Infrastructure\Http\Controllers\AuthController;
use Modules\Users\Infrastructure\Http\Controllers\UsersController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

// Directory + role reassignment: per-tenant routes (no landlord-host
// constraint — an admin authenticated on their own school's subdomain
// already holds a tenant-scoped session by the time they reach this
// screen). See design.md "Decision: super-admin escalation guarded by a
// UserPolicy". Gating the whole resource, not just edit/update: listing
// every user's name/email is itself sensitive directory data.
Route::middleware(['auth', 'role:staff/admin|super-admin'])->group(function () {
    Route::resource('users', UsersController::class)->names('users')->except(['edit', 'update']);
    Route::get('/users/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UsersController::class, 'update'])->name('users.update');
});

// Login/logout: host-unconstrained (reachable both on a tenant subdomain and
// on the landlord host). See design.md "Login on the landlord host is
// restricted to the super-admin" for the in-controller role check.
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Registration: landlord-host-only (see design.md "Only registration is
// route-constrained to the landlord host, enforced by middleware").
Route::middleware(RequireLandlordHost::class)->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});
