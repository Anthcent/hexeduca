<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Enrollments\Infrastructure\Listeners\ProjectStudentListener;
use Modules\Enrollments\Infrastructure\Persistence\LocalProjectionStudentReader;
use Modules\Users\Public\Events\UserCreated;
use Modules\Users\Public\Events\UserUpdated;

uses(RefreshDatabase::class);

// Events are constructed directly because these tests isolate projection
// listener behavior from the registration workflow.

test('UserCreated projects a new row into enrollments_student_projection', function () {
    (new ProjectStudentListener)->handle(new UserCreated(
        userId: 11,
        name: 'Grace Hopper',
        email: 'grace@example.test',
        schoolId: 3,
        roles: ['student'],
    ));

    $row = DB::table('enrollments_student_projection')->where('source_student_id', 11)->sole();

    expect($row->name)->toBe('Grace Hopper')
        ->and($row->school_id)->toBe(3)
        ->and($row->last_event_version)->toBe(1);
});

test('idempotency: an older or equal version never overwrites a newer projected row', function () {
    (new ProjectStudentListener)->handle(new UserCreated(
        userId: 11, name: 'Grace Hopper', email: 'grace@example.test', schoolId: 3, roles: ['student'], version: 4,
    ));

    (new ProjectStudentListener)->handle(new UserUpdated(
        userId: 11, name: 'Stale', email: 'stale@example.test', schoolId: 3, roles: ['student'], version: 4,
    ));

    $row = DB::table('enrollments_student_projection')->where('source_student_id', 11)->sole();

    expect($row->name)->toBe('Grace Hopper');
});

test('LocalProjectionStudentReader reads from the projection, scoped by school', function () {
    (new ProjectStudentListener)->handle(new UserCreated(userId: 1, name: 'A', email: 'a@x.test', schoolId: 3, roles: ['student']));
    (new ProjectStudentListener)->handle(new UserCreated(userId: 2, name: 'B', email: 'b@x.test', schoolId: 4, roles: ['student']));

    $reader = new LocalProjectionStudentReader;

    expect($reader->find(1)?->name)->toBe('A')
        ->and(array_map(fn ($dto) => $dto->id, $reader->allForSchool(3)))->toBe([1]);
});

test('teacher and legacy role-less events never enter the student projection', function () {
    $listener = new ProjectStudentListener;

    $listener->handle(new UserCreated(
        userId: 10, name: 'Teacher', email: 'teacher@example.test', schoolId: 3, roles: ['teacher'],
    ));
    $listener->handle(UserCreated::fromPayload([
        'userId' => 11,
        'name' => 'Legacy',
        'email' => 'legacy@example.test',
        'schoolId' => 3,
    ]));

    expect(DB::table('enrollments_student_projection')->where('is_active', true)->count())->toBe(0);
});

test('role removal leaves a versioned tombstone that rejects duplicate and reordered student events', function () {
    $listener = new ProjectStudentListener;
    $listener->handle(new UserCreated(
        userId: 11, name: 'Grace', email: 'grace@example.test', schoolId: 3, roles: ['student'], version: 1,
    ));
    $listener->handle(new UserUpdated(
        userId: 11, name: 'Grace', email: 'grace@example.test', schoolId: 3, roles: ['teacher'], version: 4,
    ));
    $listener->handle(new UserUpdated(
        userId: 11, name: 'Stale Student', email: 'stale@example.test', schoolId: 3, roles: ['student'], version: 3,
    ));
    $listener->handle(new UserUpdated(
        userId: 11, name: 'Duplicate', email: 'duplicate@example.test', schoolId: 3, roles: ['student'], version: 4,
    ));

    $row = DB::table('enrollments_student_projection')->where('source_student_id', 11)->sole();

    expect($row->is_active)->toBe(0)
        ->and($row->last_event_version)->toBe(4)
        ->and($row->name)->toBe('Grace')
        ->and((new LocalProjectionStudentReader)->find(11))->toBeNull();
});
