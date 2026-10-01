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
    $superAdmin->forceFill(['is_super_admin' => true])->save();

    foreach (['student', 'teacher', 'director'] as $role) {
        $school = School::factory()->create();
        $target = User::factory()->create(['school_id' => $school->id]);

        expect($this->policy->assignRole($superAdmin, $target, $role))->toBeTrue();
    }
});

test('director can assign a role within their own school', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('director');
    $sameSchoolTarget = User::factory()->create(['school_id' => $school->id]);

    foreach (['student', 'teacher', 'director'] as $role) {
        expect($this->policy->assignRole($admin, $sameSchoolTarget, $role))->toBeTrue();
    }
});

test('director cannot assign a role to a user in a different school', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $schoolA->id]);
    $admin->assignRole('director');
    $otherSchoolTarget = User::factory()->create(['school_id' => $schoolB->id]);

    expect($this->policy->assignRole($admin, $otherSchoolTarget, 'teacher'))->toBeFalse();
});

test('an actor with no school_id who is not a super-admin cannot assign any role', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => null]);
    $target = User::factory()->create(['school_id' => $school->id]);

    expect($this->policy->assignRole($admin, $target, 'teacher'))->toBeFalse();
});
