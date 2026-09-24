<?php

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Contracts\ActivePeriod;
use App\IntegrationEvents\Outbox\OutboxWorker;
use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\AcademicOffers\Infrastructure\Models\AcademicOffer;
use Modules\Enrollments\Application\DTOs\CreateEnrollmentData;
use Modules\Enrollments\Application\UseCases\CreateEnrollment;
use Modules\Enrollments\Public\Events\EnrollmentCreated;
use Modules\Grades\Infrastructure\Listeners\ProjectEnrollmentListener;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

function setActivePeriodForGradesTest(PeriodoAcademico $periodo): void
{
    app(AcademicPeriodContext::class)->set(new ActivePeriod(
        id: $periodo->id,
        name: $periodo->name,
        startsOn: $periodo->starts_on,
        endsOn: $periodo->ends_on,
    ));
}

test('CreateEnrollment writes to the outbox, and running the worker projects the row into grades_enrollment_projection', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id, 'is_active' => true]);
    $offer = AcademicOffer::factory()->create(['school_id' => $school->id, 'periodo_academico_id' => $periodo->id]);
    $student = User::factory()->create(['school_id' => $school->id]);
    $this->seed(RoleAndPermissionSeeder::class);
    $student->assignRole('student');

    app(TenantContext::class)->set($school);
    setActivePeriodForGradesTest($periodo);

    $enrollment = app(CreateEnrollment::class)->handle(new CreateEnrollmentData(
        academicOfferId: $offer->id,
        studentId: $student->id,
    ));

    // Nothing projected yet — only the outbox row exists until the worker runs.
    expect(DB::table('grades_enrollment_projection')->count())->toBe(0);
    expect(DB::table('integration_outbox_events')->where('event_name', 'enrollment.created')->count())->toBe(1);

    app(OutboxWorker::class)->run();

    $row = DB::table('grades_enrollment_projection')->where('source_enrollment_id', $enrollment->id())->sole();

    expect($row->student_id)->toBe($student->id)
        ->and($row->academic_offer_id)->toBe($offer->id)
        ->and($row->school_id)->toBe($school->id)
        ->and($row->academic_period_id)->toBe($periodo->id)
        ->and($row->status)->toBe('active')
        ->and($row->last_event_version)->toBe(1);
});

test('the projection stores versions above the signed 32-bit range and rejects older events', function () {
    $highVersion = 2_147_483_648;

    DB::table('grades_enrollment_projection')->insert([
        'source_enrollment_id' => 99,
        'student_id' => 1,
        'academic_offer_id' => 1,
        'school_id' => 1,
        'academic_period_id' => 1,
        'status' => 'active',
        'source_updated_at' => now(),
        'last_event_version' => $highVersion,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $listener = new ProjectEnrollmentListener;

    // An older version arriving late must be a no-op.
    $listener->handle(new EnrollmentCreated(
        enrollmentId: 99,
        schoolId: 1,
        academicPeriodId: 1,
        academicOfferId: 1,
        studentId: 1,
        status: 'cancelled',
        version: $highVersion - 1,
    ));

    $unchanged = DB::table('grades_enrollment_projection')->where('source_enrollment_id', 99)->sole();
    expect($unchanged->status)->toBe('active')
        ->and($unchanged->last_event_version)->toBe($highVersion);

    // A genuinely newer version DOES apply.
    $listener->handle(new EnrollmentCreated(
        enrollmentId: 99,
        schoolId: 1,
        academicPeriodId: 1,
        academicOfferId: 1,
        studentId: 1,
        status: 'cancelled',
        version: $highVersion + 1,
    ));

    $updated = DB::table('grades_enrollment_projection')->where('source_enrollment_id', 99)->sole();
    expect($updated->status)->toBe('cancelled')
        ->and($updated->last_event_version)->toBe($highVersion + 1);
});

test('atomic projection insert handles duplicate and reordered delivery without regressing version', function () {
    $listenerA = new ProjectEnrollmentListener;
    $listenerB = new ProjectEnrollmentListener;
    $versionTwo = new EnrollmentCreated(
        enrollmentId: 500,
        schoolId: 1,
        academicPeriodId: 2,
        academicOfferId: 3,
        studentId: 4,
        status: 'active',
        version: 2,
    );

    $listenerA->handle($versionTwo);
    $listenerB->handle($versionTwo);
    $listenerB->handle(new EnrollmentCreated(
        enrollmentId: 500,
        schoolId: 1,
        academicPeriodId: 2,
        academicOfferId: 3,
        studentId: 4,
        status: 'cancelled',
        version: 1,
    ));

    expect(DB::table('grades_enrollment_projection')->where('source_enrollment_id', 500)->count())->toBe(1);
    $row = DB::table('grades_enrollment_projection')->where('source_enrollment_id', 500)->sole();
    expect($row->status)->toBe('active')
        ->and($row->last_event_version)->toBe(2);
});
