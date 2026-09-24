<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\AcademicOffers\Infrastructure\Persistence\LocalProjectionTeacherReader;
use Modules\Enrollments\Infrastructure\Persistence\LocalProjectionStudentReader;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('teacher projection rebuild backfills existing teachers and tombstones stale rows', function () {
    $school = School::factory()->create();
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');
    DB::table('academic_offers_teacher_projection')->insert([
        'source_teacher_id' => 999,
        'school_id' => $school->id,
        'name' => 'Stale Teacher',
        'email' => 'stale-teacher@example.test',
        'is_active' => true,
        'source_updated_at' => now(),
        'last_event_version' => 7,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('academic-offers:rebuild-teacher-projection')
        ->expectsOutput('Teacher projection rebuilt: 1 active, 1 tombstoned.')
        ->assertSuccessful();

    expect((new LocalProjectionTeacherReader)->find($teacher->id)?->schoolId)->toBe($school->id)
        ->and((new LocalProjectionTeacherReader)->find(999))->toBeNull();
    expect((bool) DB::table('academic_offers_teacher_projection')->where('source_teacher_id', 999)->value('is_active'))->toBeFalse()
        ->and(DB::table('academic_offers_teacher_projection')->where('source_teacher_id', 999)->value('last_event_version'))->toBe(7);
});

test('student projection rebuild backfills existing students and tombstones stale rows', function () {
    $school = School::factory()->create();
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');
    DB::table('enrollments_student_projection')->insert([
        'source_student_id' => 999,
        'school_id' => $school->id,
        'name' => 'Stale Student',
        'email' => 'stale-student@example.test',
        'is_active' => true,
        'source_updated_at' => now(),
        'last_event_version' => 9,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('enrollments:rebuild-student-projection')
        ->expectsOutput('Student projection rebuilt: 1 active, 1 tombstoned.')
        ->assertSuccessful();

    expect((new LocalProjectionStudentReader)->find($student->id)?->schoolId)->toBe($school->id)
        ->and((new LocalProjectionStudentReader)->find(999))->toBeNull();
    expect((bool) DB::table('enrollments_student_projection')->where('source_student_id', 999)->value('is_active'))->toBeFalse()
        ->and(DB::table('enrollments_student_projection')->where('source_student_id', 999)->value('last_event_version'))->toBe(9);
});
