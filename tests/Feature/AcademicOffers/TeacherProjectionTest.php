<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AcademicOffers\Infrastructure\Listeners\ProjectTeacherListener;
use Modules\AcademicOffers\Infrastructure\Persistence\LocalProjectionTeacherReader;
use Modules\Users\Public\Events\UserCreated;
use Modules\Users\Public\Events\UserUpdated;

uses(RefreshDatabase::class);

// Events are constructed directly because these tests isolate projection
// listener behavior from the registration workflow.

test('UserCreated projects a new row into academic_offers_teacher_projection', function () {
    (new ProjectTeacherListener)->handle(new UserCreated(
        userId: 42,
        name: 'Ada Lovelace',
        email: 'ada@example.test',
        schoolId: 7,
        roles: ['teacher'],
    ));

    $row = DB::table('academic_offers_teacher_projection')->where('source_teacher_id', 42)->sole();

    expect($row->name)->toBe('Ada Lovelace')
        ->and($row->email)->toBe('ada@example.test')
        ->and($row->school_id)->toBe(7)
        ->and($row->last_event_version)->toBe(1);
});

test('UserUpdated refreshes an existing projected row', function () {
    (new ProjectTeacherListener)->handle(new UserCreated(
        userId: 42, name: 'Ada Lovelace', email: 'ada@example.test', schoolId: 7, roles: ['teacher'],
    ));

    (new ProjectTeacherListener)->handle(new UserUpdated(
        userId: 42, name: 'Ada L.', email: 'ada.l@example.test', schoolId: 7, roles: ['teacher'], version: 2,
    ));

    $row = DB::table('academic_offers_teacher_projection')->where('source_teacher_id', 42)->sole();

    expect($row->name)->toBe('Ada L.')->and($row->last_event_version)->toBe(2);
});

test('idempotency: an older or equal version never overwrites a newer projected row', function () {
    (new ProjectTeacherListener)->handle(new UserCreated(
        userId: 42, name: 'Ada Lovelace', email: 'ada@example.test', schoolId: 7, roles: ['teacher'], version: 3,
    ));

    (new ProjectTeacherListener)->handle(new UserUpdated(
        userId: 42, name: 'Stale Name', email: 'stale@example.test', schoolId: 7, roles: ['teacher'], version: 2,
    ));

    $row = DB::table('academic_offers_teacher_projection')->where('source_teacher_id', 42)->sole();

    expect($row->name)->toBe('Ada Lovelace')->and($row->last_event_version)->toBe(3);
});

test('LocalProjectionTeacherReader reads from the projection, scoped by school', function () {
    (new ProjectTeacherListener)->handle(new UserCreated(userId: 1, name: 'In School', email: 'a@x.test', schoolId: 7, roles: ['teacher']));
    (new ProjectTeacherListener)->handle(new UserCreated(userId: 2, name: 'Other School', email: 'b@x.test', schoolId: 9, roles: ['teacher']));

    $reader = new LocalProjectionTeacherReader;

    expect($reader->find(1)?->name)->toBe('In School')
        ->and($reader->find(999))->toBeNull()
        ->and(array_map(fn ($dto) => $dto->id, $reader->allForSchool(7)))->toBe([1]);
});

test('student and legacy role-less events never enter the teacher projection', function () {
    $listener = new ProjectTeacherListener;

    $listener->handle(new UserCreated(
        userId: 10, name: 'Student', email: 'student@example.test', schoolId: 7, roles: ['student'],
    ));
    $listener->handle(UserCreated::fromPayload([
        'userId' => 11,
        'name' => 'Legacy',
        'email' => 'legacy@example.test',
        'schoolId' => 7,
    ]));

    expect(DB::table('academic_offers_teacher_projection')->where('is_active', true)->count())->toBe(0);
});

test('role removal leaves a versioned tombstone that rejects duplicate and reordered teacher events', function () {
    $listener = new ProjectTeacherListener;
    $listener->handle(new UserCreated(
        userId: 42, name: 'Ada', email: 'ada@example.test', schoolId: 7, roles: ['teacher'], version: 1,
    ));
    $listener->handle(new UserUpdated(
        userId: 42, name: 'Ada', email: 'ada@example.test', schoolId: 7, roles: ['student'], version: 3,
    ));
    $listener->handle(new UserUpdated(
        userId: 42, name: 'Stale Teacher', email: 'stale@example.test', schoolId: 7, roles: ['teacher'], version: 2,
    ));
    $listener->handle(new UserUpdated(
        userId: 42, name: 'Duplicate', email: 'duplicate@example.test', schoolId: 7, roles: ['teacher'], version: 3,
    ));

    $row = DB::table('academic_offers_teacher_projection')->where('source_teacher_id', 42)->sole();

    expect($row->is_active)->toBe(0)
        ->and($row->last_event_version)->toBe(3)
        ->and($row->name)->toBe('Ada')
        ->and((new LocalProjectionTeacherReader)->find(42))->toBeNull();
});
