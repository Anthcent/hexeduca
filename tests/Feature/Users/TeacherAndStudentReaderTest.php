<?php

/**
 * Fase 6 del refactor arquitectónico (ver
 * C:\Users\DELL 3380\.claude\plans\soft-imagining-pascal.md §5). Cubre los
 * contratos públicos nuevos que reemplazan el import directo de
 * Modules\Users\Infrastructure\Models\User desde AcademicOffers y
 * Enrollments.
 */

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Users\Infrastructure\Models\User;
use Modules\Users\Public\Contracts\StudentReader;
use Modules\Users\Public\Contracts\TeacherReader;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

test('TeacherReader::find resolves a plain DTO, never the Eloquent model', function () {
    $school = School::factory()->create();
    $teacher = User::factory()->create(['school_id' => $school->id, 'name' => 'Ada Lovelace']);
    $teacher->assignRole('teacher');

    $dto = app(TeacherReader::class)->find($teacher->id);

    expect($dto)->not->toBeNull()
        ->and($dto->id)->toBe($teacher->id)
        ->and($dto->name)->toBe('Ada Lovelace')
        ->and($dto->email)->toBe($teacher->email)
        ->and($dto)->not->toBeInstanceOf(User::class);
});

test('TeacherReader::find returns null for a non-existent id', function () {
    expect(app(TeacherReader::class)->find(999999))->toBeNull();
});

test('TeacherReader::allForSchool only returns teachers scoped to the given school', function () {
    $schoolOne = School::factory()->create();
    $schoolTwo = School::factory()->create();

    $teacherInSchoolOne = User::factory()->create(['school_id' => $schoolOne->id, 'name' => 'Grace Hopper']);
    $teacherInSchoolOne->assignRole('teacher');

    $teacherInSchoolTwo = User::factory()->create(['school_id' => $schoolTwo->id]);
    $teacherInSchoolTwo->assignRole('teacher');

    $studentInSchoolOne = User::factory()->create(['school_id' => $schoolOne->id]);
    $studentInSchoolOne->assignRole('student');

    $teachers = app(TeacherReader::class)->allForSchool($schoolOne->id);

    expect($teachers)->toHaveCount(1)
        ->and($teachers[0]->id)->toBe($teacherInSchoolOne->id)
        ->and($teachers[0]->name)->toBe('Grace Hopper');
});

test('StudentReader::find resolves a plain DTO, never the Eloquent model', function () {
    $school = School::factory()->create();
    $student = User::factory()->create(['school_id' => $school->id, 'name' => 'Alan Turing']);
    $student->assignRole('student');

    $dto = app(StudentReader::class)->find($student->id);

    expect($dto)->not->toBeNull()
        ->and($dto->id)->toBe($student->id)
        ->and($dto->name)->toBe('Alan Turing')
        ->and($dto)->not->toBeInstanceOf(User::class);
});

test('StudentReader::allForSchool only returns students scoped to the given school', function () {
    $school = School::factory()->create();

    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');

    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');

    $students = app(StudentReader::class)->allForSchool($school->id);

    expect($students)->toHaveCount(1)
        ->and($students[0]->id)->toBe($student->id);
});
