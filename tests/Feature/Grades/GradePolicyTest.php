<?php

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Contracts\ActivePeriod;
use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Grades\Infrastructure\Models\Grade;
use Modules\Grades\Infrastructure\Policies\GradePolicy;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function setActivePeriod(int $periodId): void
{
    app(AcademicPeriodContext::class)->set(new ActivePeriod(
        id: $periodId,
        name: 'test-period',
        startsOn: new DateTimeImmutable('2026-01-01'),
        endsOn: new DateTimeImmutable('2026-12-01'),
    ));
}

function makeGrade(int $schoolId, int $periodId, int $teacherId): Grade
{
    return Grade::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $schoolId,
        'periodo_academico_id' => $periodId,
        'academic_offer_id' => 1,
        'student_id' => 1,
        'teacher_id' => $teacherId,
        'value' => 80,
        'recorded_at' => now(),
    ]);
}

test('the assigned teacher, same school and same active period, may update the grade', function () {
    $school = School::factory()->create();
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');
    setActivePeriod(5);
    $grade = makeGrade($school->id, 5, $teacher->id);

    expect((new GradePolicy)->update($teacher, $grade))->toBeTrue();
});

test('a teacher not assigned to the grade is rejected', function () {
    $school = School::factory()->create();
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $otherTeacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');
    setActivePeriod(5);
    $grade = makeGrade($school->id, 5, $otherTeacher->id);

    expect((new GradePolicy)->update($teacher, $grade))->toBeFalse();
});

test('staff/admin bypasses the assigned-teacher check but still needs matching school and period', function () {
    $school = School::factory()->create();
    $staff = User::factory()->create(['school_id' => $school->id]);
    $staff->assignRole('staff/admin');
    setActivePeriod(5);
    $grade = makeGrade($school->id, 5, 999);

    expect((new GradePolicy)->update($staff, $grade))->toBeTrue();
});

test('a mismatched active period is rejected even for the assigned teacher', function () {
    $school = School::factory()->create();
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');
    setActivePeriod(5);
    $grade = makeGrade($school->id, 6, $teacher->id);

    expect((new GradePolicy)->update($teacher, $grade))->toBeFalse();
});

test('a cross-tenant teacher is rejected even with a matching teacher_id', function () {
    $schoolA = School::factory()->create();
    $schoolB = School::factory()->create();
    $teacher = User::factory()->create(['school_id' => $schoolA->id]);
    $teacher->assignRole('teacher');
    setActivePeriod(5);
    $grade = makeGrade($schoolB->id, 5, $teacher->id);

    expect((new GradePolicy)->update($teacher, $grade))->toBeFalse();
});
