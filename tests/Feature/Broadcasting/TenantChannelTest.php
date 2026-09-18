<?php

use App\Tenancy\Broadcasting\TenantChannel;
use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

test('name builds the exact school-scoped channel shape', function () {
    expect(TenantChannel::name(7, 'grades', 42))->toBe('school.7.grades.42');
});

test('name accepts a School model instance', function () {
    $school = School::factory()->create();

    expect(TenantChannel::name($school, 'grades', 42))->toBe("school.{$school->id}.grades.42");
});

test('pattern builds the exact registration pattern shape', function () {
    expect(TenantChannel::pattern('grades'))->toBe('school.{schoolId}.grades.{id}');
});

test('authorize accepts a same-school user', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    expect(TenantChannel::authorize($user, $school->id))->toBeTrue();
});

test('authorize rejects a different-school user', function () {
    $ownSchool = School::factory()->create();
    $otherSchool = School::factory()->create();
    $user = User::factory()->create(['school_id' => $ownSchool->id]);

    expect(TenantChannel::authorize($user, $otherSchool->id))->toBeFalse();
});

test('authorize rejects a landlord user for any school id, well-formed or malformed', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => null]);

    expect(TenantChannel::authorize($user, $school->id))->toBeFalse()
        ->and(TenantChannel::authorize($user, 'abc'))->toBeFalse();
});

test('authorize coerces string channel ids', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    expect(TenantChannel::authorize($user, (string) $school->id))->toBeTrue();
});

test('authorize rejects a malformed non numeric school id even against a tenant user', function () {
    $school = School::factory()->create();
    $user = User::factory()->create(['school_id' => $school->id]);

    expect(TenantChannel::authorize($user, 'abc'))->toBeFalse();
});
