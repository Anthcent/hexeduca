<?php

use App\IntegrationEvents\Outbox\OutboxWorker;
use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AcademicOffers\Infrastructure\Listeners\ProjectTeacherListener;
use Modules\Enrollments\Infrastructure\Listeners\ProjectStudentListener;
use Modules\Users\Infrastructure\Models\User;
use Modules\Users\Public\Events\UserCreated;
use Modules\Users\Public\Events\UserUpdated;

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

test('student to teacher HTTP reassignment updates both projections through the outbox', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $target = User::factory()->create(['school_id' => $school->id]);
    $target->assignRole('student');
    (new ProjectStudentListener)->handle(new UserCreated(
        userId: $target->id,
        name: $target->name,
        email: $target->email,
        schoolId: $school->id,
        roles: ['student'],
    ));

    $this->actingAs($admin)
        ->put(tenantUrl($school, "/users/{$target->id}"), ['role' => 'teacher'])
        ->assertRedirect();

    $row = DB::table('integration_outbox_events')->where('event_name', 'user.updated')->sole();
    expect($row->event_class)->toBe(UserUpdated::class)
        ->and(json_decode($row->payload, true)['roles'])->toBe(['teacher']);
    expect(app(OutboxWorker::class)->run())->toMatchArray(['published' => 1, 'failed' => 0]);
    expect(DB::table('enrollments_student_projection')->where('source_student_id', $target->id)->value('is_active'))->toBe(0)
        ->and(DB::table('academic_offers_teacher_projection')->where('source_teacher_id', $target->id)->count())->toBe(1);
});

test('teacher to student HTTP reassignment updates both projections through the outbox', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $target = User::factory()->create(['school_id' => $school->id]);
    $target->assignRole('teacher');
    (new ProjectTeacherListener)->handle(new UserCreated(
        userId: $target->id,
        name: $target->name,
        email: $target->email,
        schoolId: $school->id,
        roles: ['teacher'],
    ));

    $this->actingAs($admin)
        ->put(tenantUrl($school, "/users/{$target->id}"), ['role' => 'student'])
        ->assertRedirect();

    $row = DB::table('integration_outbox_events')->where('event_name', 'user.updated')->sole();
    expect(json_decode($row->payload, true)['roles'])->toBe(['student']);
    expect(app(OutboxWorker::class)->run())->toMatchArray(['published' => 1, 'failed' => 0]);
    expect(DB::table('academic_offers_teacher_projection')->where('source_teacher_id', $target->id)->value('is_active'))->toBe(0)
        ->and(DB::table('enrollments_student_projection')->where('source_student_id', $target->id)->count())->toBe(1);
});

test('role reassignment rolls back when the UserUpdated outbox write fails', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $target = User::factory()->create(['school_id' => $school->id]);
    $target->assignRole('student');
    DB::statement(<<<'SQL'
        CREATE TRIGGER fail_role_update_outbox
        BEFORE INSERT ON integration_outbox_events
        WHEN NEW.event_name = 'user.updated'
        BEGIN
            SELECT RAISE(ABORT, 'forced role update outbox failure');
        END
    SQL);

    expect(fn () => $this->withoutExceptionHandling()
        ->actingAs($admin)
        ->put(tenantUrl($school, "/users/{$target->id}"), ['role' => 'teacher']))
        ->toThrow(QueryException::class);

    $target->refresh();
    expect($target->hasRole('student'))->toBeTrue()
        ->and($target->hasRole('teacher'))->toBeFalse();
    expect(DB::table('integration_outbox_events')->where('event_name', 'user.updated')->count())->toBe(0);
});
