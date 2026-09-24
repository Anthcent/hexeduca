<?php

use App\Tenancy\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico;
use Modules\AcademicOffers\Infrastructure\Models\AcademicOffer;
use Modules\Enrollments\Infrastructure\Models\Enrollment;
use Modules\Enrollments\Public\Events\EnrollmentCreated;
use Modules\Grades\Infrastructure\Listeners\ProjectEnrollmentListener;
use Modules\Users\Infrastructure\Models\User;

uses(RefreshDatabase::class);

test('grades:rebuild-enrollments preserves source version so a delayed older event cannot overwrite rebuilt state', function () {
    $school = School::factory()->create();
    $periodo = PeriodoAcademico::factory()->create(['school_id' => $school->id]);
    $offer = AcademicOffer::factory()->create(['school_id' => $school->id, 'periodo_academico_id' => $periodo->id]);
    $student = User::factory()->create(['school_id' => $school->id]);

    // Created directly against the model — no event, no outbox row at all.
    // This proves the rebuild command reads from
    // Modules\Enrollments\Public\Contracts\EnrollmentProjectionSource, not
    // from the outbox/event trail.
    $enrollment = Enrollment::withoutTenantScope()->withoutActivePeriodScope()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $periodo->id,
        'oferta_academica_id' => $offer->id,
        'student_id' => $student->id,
        'status' => 'active',
        'source_version' => 7,
        'enrolled_at' => now(),
    ]);

    expect(DB::table('integration_outbox_events')->count())->toBe(0)
        ->and(DB::table('grades_enrollment_projection')->count())->toBe(0);

    $this->artisan('grades:rebuild-enrollments')->assertSuccessful();

    $row = DB::table('grades_enrollment_projection')->where('source_enrollment_id', $enrollment->id)->sole();

    expect($row->student_id)->toBe($student->id)
        ->and($row->academic_offer_id)->toBe($offer->id)
        ->and($row->status)->toBe('active')
        ->and($row->last_event_version)->toBe(7);

    app(ProjectEnrollmentListener::class)->handle(new EnrollmentCreated(
        enrollmentId: $enrollment->id,
        schoolId: $school->id,
        academicPeriodId: $periodo->id,
        academicOfferId: $offer->id,
        studentId: $student->id,
        status: 'cancelled',
        version: 6,
    ));

    $afterDelayedEvent = DB::table('grades_enrollment_projection')
        ->where('source_enrollment_id', $enrollment->id)
        ->sole();

    expect($afterDelayedEvent->status)->toBe('active')
        ->and($afterDelayedEvent->last_event_version)->toBe(7);
});

test('rebuilding truncates stale rows that no longer exist at the source', function () {
    DB::table('grades_enrollment_projection')->insert([
        'source_enrollment_id' => 4242,
        'student_id' => 1,
        'academic_offer_id' => 1,
        'school_id' => 1,
        'academic_period_id' => 1,
        'status' => 'active',
        'source_updated_at' => now(),
        'last_event_version' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('grades:rebuild-enrollments')->assertSuccessful();

    expect(DB::table('grades_enrollment_projection')->where('source_enrollment_id', 4242)->exists())->toBeFalse();
});
