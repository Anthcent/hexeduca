<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;
use Modules\Users\Infrastructure\Policies\UserPolicy;
use Tests\TestCase;

// Unit tests use plain PHPUnit\Framework\TestCase by default (see
// tests/Pest.php — `uses(TestCase::class)->in('Feature')` only binds
// Feature tests). This test needs the full Laravel app + database to
// exercise real `hasRole()`/`assignRole()` via Spatie, so it opts into
// the Laravel TestCase explicitly.
uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->policy = new UserPolicy;
});

test('super-admin can assign any role to a user in any tenant', function () {
    $superAdmin = User::factory()->create(['school_id' => null]);
    $superAdmin->assignRole('super-admin');

    foreach (['student', 'teacher', 'staff/admin', 'super-admin'] as $role) {
        $school = School::factory()->create();
        $target = User::factory()->create(['school_id' => $school->id]);

        expect($this->policy->assignRole($superAdmin, $target, $role))->toBeTrue();
    }
});

test('staff/admin can assign a non-super-admin role within their own school', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $sameSchoolTarget = User::factory()->create(['school_id' => $school->id]);

    foreach (['student', 'teacher', 'staff/admin'] as $role) {
        expect($this->policy->assignRole($admin, $sameSchoolTarget, $role))->toBeTrue();
    }
});

test('staff/admin cannot assign super-admin even within their own school', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $sameSchoolTarget = User::factory()->create(['school_id' => $school->id]);

    expect($this->policy->assignRole($admin, $sameSchoolTarget, 'super-admin'))->toBeFalse();
});

test('staff/admin cannot assign a role to a user in a different school', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $schoolA->id]);
    $admin->assignRole('staff/admin');
    $otherSchoolTarget = User::factory()->create(['school_id' => $schoolB->id]);

    expect($this->policy->assignRole($admin, $otherSchoolTarget, 'teacher'))->toBeFalse();
});

test('an actor with no school_id and no super-admin role cannot assign any role', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => null]);
    $admin->assignRole('staff/admin');
    $target = User::factory()->create(['school_id' => $school->id]);

    expect($this->policy->assignRole($admin, $target, 'teacher'))->toBeFalse();
});
