<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// `tenantUrl()` is defined globally in tests/Feature/Auth/LoginLogoutTest.php
// (Pest loads all Feature test files into one process, so helper functions
// with the same signature must not be redeclared here).

test('guest requests share a null auth.user prop', function () {
    $school = School::factory()->create();

    $this->get(tenantUrl($school, '/login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
});

test('authenticated requests share the user identity and single role name', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $target = User::factory()->create(['school_id' => $school->id]);
    $target->assignRole('student');

    $this->actingAs($admin)
        ->get(tenantUrl($school, "/users/{$target->id}/edit"))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.id', $admin->id)
            ->where('auth.user.name', $admin->name)
            ->where('auth.user.email', $admin->email)
            ->where('auth.user.role', 'staff/admin'));
});

test('auth.user role reflects a role change on the next request', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);
    $user->assignRole('student');

    $this->actingAs($user)
        ->get(tenantUrl($school, '/'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.role', 'student'));

    $user->syncRoles(['teacher']);

    $this->actingAs($user->fresh())
        ->get(tenantUrl($school, '/'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.role', 'teacher'));
});
