<?php

use App\Tenancy\Models\School;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Infrastructure\Models\Grado;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\Academic\Infrastructure\Models\Seccion;
use Modules\AcademicOffers\Application\DTOs\CreateAcademicOfferData;
use Modules\AcademicOffers\Application\UseCases\CreateAcademicOffer;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function academicOfferDataWithTeacher(int $schoolId, int $teacherId): CreateAcademicOfferData
{
    $gradeLevel = Grado::factory()->create(['school_id' => $schoolId]);
    $section = Seccion::factory()->create(['school_id' => $schoolId]);

    return new CreateAcademicOfferData(
        schoolId: $schoolId,
        academicPeriodId: 100,
        gradeLevelId: $gradeLevel->id,
        sectionId: $section->id,
        teacherId: $teacherId,
        capacity: 30,
    );
}

test('CreateAcademicOffer rejects a teacher from another school', function () {
    $targetSchool = School::factory()->create();
    $otherSchool = School::factory()->create();
    $teacher = User::factory()->create(['school_id' => $otherSchool->id]);
    $teacher->assignRole('teacher');

    expect(fn () => app(CreateAcademicOffer::class)->handle(
        academicOfferDataWithTeacher($targetSchool->id, $teacher->id),
    ))->toThrow(DomainException::class, 'The assigned teacher must be a teacher in this school.');

    $this->assertDatabaseCount('ofertas_academicas', 0);
});

test('CreateAcademicOffer rejects a same-school user without the teacher role', function () {
    $school = School::factory()->create();
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');

    expect(fn () => app(CreateAcademicOffer::class)->handle(
        academicOfferDataWithTeacher($school->id, $student->id),
    ))->toThrow(DomainException::class, 'The assigned teacher must be a teacher in this school.');

    $this->assertDatabaseCount('ofertas_academicas', 0);
});

test('POST academic offers maps teacher authorization failures to teacher_id without persistence', function (string $assignment) {
    $school = School::factory()->create();
    $assignedSchool = $assignment === 'cross-school' ? School::factory()->create() : $school;
    PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $gradeLevel = Grado::factory()->create(['school_id' => $school->id]);
    $section = Seccion::factory()->create(['school_id' => $school->id]);
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $assignee = User::factory()->create(['school_id' => $assignedSchool->id]);
    $assignee->assignRole($assignment === 'cross-school' ? 'teacher' : 'student');
    $url = 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/academic-offers';

    $this->actingAs($admin)->post($url, [
        'grade_level_id' => $gradeLevel->id,
        'section_id' => $section->id,
        'teacher_id' => $assignee->id,
        'capacity' => 30,
    ])->assertSessionHasErrors('teacher_id');

    $this->assertDatabaseCount('ofertas_academicas', 0);
})->with(['cross-school', 'wrong-role']);

test('CreateAcademicOffer rejects cross-school academic catalogs without persistence or outbox', function (string $reference) {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $gradeLevel = Grado::factory()->create([
        'school_id' => $reference === 'grade' ? $otherSchool->id : $school->id,
    ]);
    $section = Seccion::factory()->create([
        'school_id' => $reference === 'section' ? $otherSchool->id : $school->id,
    ]);

    expect(fn () => app(CreateAcademicOffer::class)->handle(new CreateAcademicOfferData(
        schoolId: $school->id,
        academicPeriodId: 100,
        gradeLevelId: $gradeLevel->id,
        sectionId: $section->id,
        teacherId: null,
        capacity: 30,
    )))->toThrow(DomainException::class);

    $this->assertDatabaseCount('ofertas_academicas', 0);
    $this->assertDatabaseCount('integration_outbox_events', 0);
})->with(['grade', 'section']);

test('POST academic offers maps cross-school catalogs to their fields without persistence or outbox', function (string $field) {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $gradeLevel = Grado::factory()->create([
        'school_id' => $field === 'grade_level_id' ? $otherSchool->id : $school->id,
    ]);
    $section = Seccion::factory()->create([
        'school_id' => $field === 'section_id' ? $otherSchool->id : $school->id,
    ]);
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $url = 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/academic-offers';

    $this->actingAs($admin)->post($url, [
        'grade_level_id' => $gradeLevel->id,
        'section_id' => $section->id,
        'capacity' => 30,
    ])->assertSessionHasErrors($field);

    $this->assertDatabaseCount('ofertas_academicas', 0);
    $this->assertDatabaseCount('integration_outbox_events', 0);
})->with(['grade_level_id', 'section_id']);

test('POST academic offers succeeds with same-school references and records exactly one outbox event', function () {
    $school = School::factory()->create();
    PeriodoAcademico::factory()->active()->create(['school_id' => $school->id]);
    $gradeLevel = Grado::factory()->create(['school_id' => $school->id]);
    $section = Seccion::factory()->create(['school_id' => $school->id]);
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $url = 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/academic-offers';

    $this->actingAs($admin)->post($url, [
        'grade_level_id' => $gradeLevel->id,
        'section_id' => $section->id,
        'teacher_id' => $teacher->id,
        'capacity' => 30,
    ])->assertRedirect();

    $this->assertDatabaseHas('ofertas_academicas', [
        'school_id' => $school->id,
        'grado_id' => $gradeLevel->id,
        'seccion_id' => $section->id,
        'teacher_id' => $teacher->id,
    ]);
    expect(DB::table('integration_outbox_events')->where('event_name', 'academic_offer.created')->count())->toBe(1);
});
