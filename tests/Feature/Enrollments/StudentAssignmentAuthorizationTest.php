<?php

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Contracts\ActivePeriod;
use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Database\Seeders\ModulePlatformSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\AcademicOffers\Infrastructure\Models\AcademicOffer;
use Modules\Enrollments\Application\DTOs\CreateEnrollmentData;
use Modules\Enrollments\Application\UseCases\CreateEnrollment;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->seed(ModulePlatformSeeder::class);
});

function enrollmentOfferFor(School $school): object
{
    $period = PeriodoAcademico::factory()->create([
        'school_id' => $school->id,
        'is_active' => true,
    ]);
    app(TenantContext::class)->set($school);
    app(AcademicPeriodContext::class)->set(new ActivePeriod(
        id: $period->id,
        name: $period->name,
        startsOn: $period->starts_on,
        endsOn: $period->ends_on,
    ));

    return AcademicOffer::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $period->id,
    ]);
}

test('CreateEnrollment rejects a student from another school', function () {
    $school = School::factory()->create();
    $otherSchool = School::factory()->create();
    $offer = enrollmentOfferFor($school);
    $student = User::factory()->create(['school_id' => $otherSchool->id]);
    $student->assignRole('student');

    expect(fn () => app(CreateEnrollment::class)->handle(new CreateEnrollmentData(
        academicOfferId: $offer->id,
        studentId: $student->id,
    )))->toThrow(DomainException::class, 'The enrolled user must be a student in the AcademicOffer school.');

    $this->assertDatabaseCount('matriculas', 0);
});

test('CreateEnrollment rejects a same-school user without the student role', function () {
    $school = School::factory()->create();
    $offer = enrollmentOfferFor($school);
    $teacher = User::factory()->create(['school_id' => $school->id]);
    $teacher->assignRole('teacher');

    expect(fn () => app(CreateEnrollment::class)->handle(new CreateEnrollmentData(
        academicOfferId: $offer->id,
        studentId: $teacher->id,
    )))->toThrow(DomainException::class, 'The enrolled user must be a student in the AcademicOffer school.');

    $this->assertDatabaseCount('matriculas', 0);
});

test('POST enrollments maps student authorization failures to student_id without persistence', function (string $assignment) {
    $school = School::factory()->create();
    $assignedSchool = $assignment === 'cross-school' ? School::factory()->create() : $school;
    $offer = enrollmentOfferFor($school);
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $assignee = User::factory()->create(['school_id' => $assignedSchool->id]);
    $assignee->assignRole($assignment === 'cross-school' ? 'student' : 'teacher');
    $url = 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/enrollments';

    $this->actingAs($admin)->post($url, [
        'academic_offer_id' => $offer->id,
        'student_id' => $assignee->id,
    ])->assertSessionHasErrors('student_id');

    $this->assertDatabaseCount('matriculas', 0);
})->with(['cross-school', 'wrong-role']);

test('POST enrollments succeeds for a same-school student and records exactly one outbox event', function () {
    $school = School::factory()->create();
    $offer = enrollmentOfferFor($school);
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');
    $admin = User::factory()->create(['school_id' => $school->id]);
    $admin->assignRole('staff/admin');
    $url = 'http://'.$school->subdomain.'.'.config('tenancy.base_domain').'/enrollments';

    $this->actingAs($admin)->post($url, [
        'academic_offer_id' => $offer->id,
        'student_id' => $student->id,
    ])->assertRedirect();

    $this->assertDatabaseHas('matriculas', [
        'school_id' => $school->id,
        'oferta_academica_id' => $offer->id,
        'student_id' => $student->id,
    ]);
    expect(DB::table('integration_outbox_events')->where('event_name', 'enrollment.created')->count())->toBe(1);
});

test('CreateEnrollment enforces capacity and does not write a second enrollment or outbox row', function () {
    $school = School::factory()->create();
    $offer = enrollmentOfferFor($school);
    $offer->update(['capacity' => 1]);
    $first = User::factory()->create(['school_id' => $school->id]);
    $first->assignRole('student');
    $second = User::factory()->create(['school_id' => $school->id]);
    $second->assignRole('student');
    $useCase = app(CreateEnrollment::class);

    $useCase->handle(new CreateEnrollmentData(academicOfferId: $offer->id, studentId: $first->id));

    expect(fn () => $useCase->handle(new CreateEnrollmentData(
        academicOfferId: $offer->id,
        studentId: $second->id,
    )))->toThrow(DomainException::class, 'The AcademicOffer has reached its enrollment capacity.');

    $this->assertDatabaseCount('matriculas', 1);
    expect(DB::table('integration_outbox_events')->where('event_name', 'enrollment.created')->count())->toBe(1);
});
