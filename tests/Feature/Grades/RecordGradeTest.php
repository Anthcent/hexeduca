<?php

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Contracts\ActivePeriod;
use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\AcademicOffers\Infrastructure\Models\AcademicOffer;
use Modules\Grades\Application\DTOs\RecordGradeData;
use Modules\Grades\Application\UseCases\RecordGrade;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(ModulePlatformSeeder::class);
});

function seedEnrollmentProjectionRow(int $schoolId, int $periodId, int $offerId, int $studentId, string $status = 'active'): void
{
    DB::table('grades_enrollment_projection')->insert([
        'source_enrollment_id' => random_int(1, 1_000_000),
        'student_id' => $studentId,
        'academic_offer_id' => $offerId,
        'school_id' => $schoolId,
        'academic_period_id' => $periodId,
        'status' => $status,
        'source_updated_at' => now(),
        'last_event_version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function gradeTestContext(School $school, PeriodoAcademico $period): void
{
    app(TenantContext::class)->set($school);
    app(AcademicPeriodContext::class)->set(new ActivePeriod(
        id: $period->id,
        name: $period->name,
        startsOn: $period->starts_on,
        endsOn: $period->ends_on,
    ));
}

test('RecordGrade saves a grade for the assigned same-school teacher in the active period', function () {
    $school = School::factory()->create();
    $period = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    gradeTestContext($school, $period);
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');
    $offer = AcademicOffer::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $period->id,
        'teacher_id' => $teacher->id,
    ]);
    seedEnrollmentProjectionRow($school->id, $period->id, $offer->id, 20);

    $grade = app(RecordGrade::class)->handle(new RecordGradeData(
        academicOfferId: $offer->id,
        studentId: 20,
        teacherId: $teacher->id,
        value: 85.5,
        actorId: $teacher->id,
        actorSchoolId: $school->id,
        activeAcademicPeriodId: $period->id,
    ));

    expect($grade->schoolId())->toBe($school->id)
        ->and($grade->academicPeriodId())->toBe($period->id)
        ->and($grade->value())->toBe(85.5);

    $row = DB::table('grades')->where('id', $grade->id())->sole();
    expect((float) $row->value)->toBe(85.5)
        ->and($row->periodo_academico_id)->toBe($period->id);
});

test('RecordGrade rejects a student with no projected enrollment in the offer', function () {
    $school = School::factory()->create();
    $period = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    gradeTestContext($school, $period);
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');
    $offer = AcademicOffer::factory()->create(['school_id' => $school->id, 'periodo_academico_id' => $period->id, 'teacher_id' => $teacher->id]);

    app(RecordGrade::class)->handle(new RecordGradeData(
        academicOfferId: $offer->id,
        studentId: 20,
        teacherId: $teacher->id,
        value: 90,
        actorId: $teacher->id,
        actorSchoolId: $school->id,
        activeAcademicPeriodId: $period->id,
    ));
})->throws(DomainException::class, 'The student is not enrolled in this AcademicOffer.');

test('RecordGrade rejects a student whose enrollment projection is not active', function () {
    $school = School::factory()->create();
    $period = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    gradeTestContext($school, $period);
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');
    $offer = AcademicOffer::factory()->create(['school_id' => $school->id, 'periodo_academico_id' => $period->id, 'teacher_id' => $teacher->id]);
    seedEnrollmentProjectionRow($school->id, $period->id, $offer->id, 20, status: 'cancelled');

    app(RecordGrade::class)->handle(new RecordGradeData(
        academicOfferId: $offer->id,
        studentId: 20,
        teacherId: $teacher->id,
        value: 90,
        actorId: $teacher->id,
        actorSchoolId: $school->id,
        activeAcademicPeriodId: $period->id,
    ));
})->throws(DomainException::class);

test('RecordGrade forbids another teacher and rejects cross-tenant enrollment projection rows', function () {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $period = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    gradeTestContext($school, $period);
    $assigned = User::factory()->create(['school_id' => $school->id]);
    $assigned->assignRole('teacher');
    $actor = User::factory()->create(['school_id' => $school->id]);
    $actor->assignRole('teacher');
    $offer = AcademicOffer::factory()->create(['school_id' => $school->id, 'periodo_academico_id' => $period->id, 'teacher_id' => $assigned->id]);
    seedEnrollmentProjectionRow($otherSchool->id, $period->id, $offer->id, 20);

    expect(fn () => app(RecordGrade::class)->handle(new RecordGradeData(
        academicOfferId: $offer->id,
        studentId: 20,
        teacherId: $actor->id,
        value: 90,
        actorId: $actor->id,
        actorSchoolId: $school->id,
        activeAcademicPeriodId: $period->id,
    )))->toThrow(AuthorizationException::class);

    expect(fn () => app(RecordGrade::class)->handle(new RecordGradeData(
        academicOfferId: $offer->id,
        studentId: 20,
        teacherId: $assigned->id,
        value: 90,
        actorId: $actor->id,
        actorSchoolId: $school->id,
        activeAcademicPeriodId: $period->id,
        actorCanDelegate: true,
    )))->toThrow(DomainException::class, 'The student is not enrolled in this AcademicOffer.');

    $this->assertDatabaseCount('grades', 0);
});

test('POST grades succeeds for the assigned teacher and forbids an unassigned teacher', function () {
    $school = School::factory()->create();
    $period = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    $assigned = User::factory()->create(['school_id' => $school->id]);
    $assigned->assignRole('teacher');
    $unassigned = User::factory()->create(['school_id' => $school->id]);
    $unassigned->assignRole('teacher');
    $student = User::factory()->create(['school_id' => $school->id]);
    $offer = AcademicOffer::factory()->create(['school_id' => $school->id, 'periodo_academico_id' => $period->id, 'teacher_id' => $assigned->id]);
    seedEnrollmentProjectionRow($school->id, $period->id, $offer->id, $student->id);
    $url = 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/grades';
    $payload = ['academic_offer_id' => $offer->id, 'student_id' => $student->id, 'value' => 88];

    $this->actingAs($assigned)->post($url, $payload)->assertRedirect(route('grades.create'));
    $this->actingAs($unassigned)->post($url, $payload)->assertForbidden();

    $otherSchool = School::factory()->create();
    $otherPeriod = PeriodoAcademico::factory()->create(['school_id' => $otherSchool->id, 'is_active' => true]);
    app(TenantContext::class)->set($otherSchool);
    app(AcademicPeriodContext::class)->set(new ActivePeriod(
        id: $otherPeriod->id,
        name: $otherPeriod->name,
        startsOn: $otherPeriod->starts_on,
        endsOn: $otherPeriod->ends_on,
    ));
    $otherTeacher = User::factory()->create(['school_id' => $otherSchool->id]);
    $otherTeacher->assignRole('teacher');
    $otherOffer = AcademicOffer::factory()->create([
        'school_id' => $otherSchool->id,
        'periodo_academico_id' => $otherPeriod->id,
        'teacher_id' => $otherTeacher->id,
    ]);

    $this->actingAs($assigned)->post($url, [
        'academic_offer_id' => $otherOffer->id,
        'student_id' => $student->id,
        'value' => 88,
    ])->assertForbidden();

    $this->assertDatabaseCount('grades', 1);
});
