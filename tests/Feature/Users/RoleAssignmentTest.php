<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

// `tenantUrl()` is defined globally in tests/Feature/Auth/LoginLogoutTest.php
// (Pest loads all Feature test files into one process, so helper functions
// with the same signature must not be redeclared here).

test('staff/admin reassigns a student to teacher in their own school', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $target = User::factory()->create(['school_id' => $school->id]);
    $target->assignRole('student');

    $this->actingAs($admin)
        ->put(tenantUrl($school, "/users/{$target->id}"), ['role' => 'teacher'])
        ->assertRedirect();

    $target->refresh();
    expect($target->hasRole('teacher'))->toBeTrue();
    expect($target->hasRole('student'))->toBeFalse();
    expect($target->getRoleNames())->toHaveCount(1);
});

test('staff/admin cannot edit or update a user in a different school', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $schoolA->id]);
    $admin->assignRole('staff/admin');
    $target = User::factory()->create(['school_id' => $schoolB->id]);
    $target->assignRole('student');

    // TenantScope excludes the school-B user entirely from a lookup made on
    // schoolA's own subdomain — the request never reaches UserPolicy.
    $this->actingAs($admin)
        ->get(tenantUrl($schoolA, "/users/{$target->id}/edit"))
        ->assertNotFound();

    $this->actingAs($admin)
        ->put(tenantUrl($schoolA, "/users/{$target->id}"), ['role' => 'teacher'])
        ->assertNotFound();

    $target->refresh();
    expect($target->hasRole('student'))->toBeTrue();
});

test('staff/admin attempting to grant super-admin is rejected with 403', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $target = User::factory()->create(['school_id' => $school->id]);
    $target->assignRole('student');

    $this->actingAs($admin)
        ->put(tenantUrl($school, "/users/{$target->id}"), ['role' => 'super-admin'])
        ->assertForbidden();

    $target->refresh();
    expect($target->hasRole('student'))->toBeTrue();
    expect($target->hasRole('super-admin'))->toBeFalse();
});

test('super-admin can grant super-admin to any user in any tenant', function () {
    $school = School::factory()->create();
    $superAdmin = User::factory()->create(['school_id' => null]);
    $superAdmin->assignRole('super-admin');
    $target = User::factory()->create(['school_id' => $school->id]);
    $target->assignRole('student');

    $this->actingAs($superAdmin)
        ->put(tenantUrl($school, "/users/{$target->id}"), ['role' => 'super-admin'])
        ->assertRedirect();

    $target->refresh();
    expect($target->hasRole('super-admin'))->toBeTrue();
});

test('a non-admin user hitting the edit route is rejected with 403', function () {
    $school = School::factory()->create();
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');
    $target = User::factory()->create(['school_id' => $school->id]);
    $target->assignRole('teacher');

    $this->actingAs($student)
        ->get(tenantUrl($school, "/users/{$target->id}/edit"))
        ->assertForbidden();
});
