<?php

use App\AcademicPeriod\Context\AcademicPeriodContext;
use App\AcademicPeriod\Contracts\ActivePeriod;
use App\Tenancy\Models\School;
use App\Tenancy\TenantContext;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\AcademicOffers\Application\DTOs\CreateAcademicOfferData;
use Modules\AcademicOffers\Application\UseCases\CreateAcademicOffer;
use Modules\AcademicOffers\Domain\Events\AcademicOfferCreated;
use Modules\AcademicOffers\Infrastructure\Models\AcademicOffer;
use Modules\AcademicPeriods\Application\DTOs\CreateAcademicPeriodData;
use Modules\AcademicPeriods\Application\UseCases\ActivateAcademicPeriod;
use Modules\AcademicPeriods\Application\UseCases\CreateAcademicPeriod;
use Modules\AcademicPeriods\Domain\Events\AcademicPeriodActivated;
use Modules\AcademicPeriods\Domain\Events\AcademicPeriodCreated;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod as AcademicPeriodModel;
use Modules\Enrollments\Application\DTOs\CreateEnrollmentData;
use Modules\Enrollments\Application\UseCases\CreateEnrollment;
use Modules\Enrollments\Domain\Events\EnrollmentCreated;
use Modules\GradeLevels\Infrastructure\Models\GradeLevel;
use Modules\Sections\Infrastructure\Models\Section;
use Modules\Users\Infrastructure\Models\User;
use Tests\Support\ForcedInsertFailure;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

function failOutboxEvent(string $eventName): void
{
    $trigger = str_replace('.', '_', $eventName);
    ForcedInsertFailure::install("fail_{$trigger}_outbox", 'integration_outbox_events', 'forced outbox failure', $eventName);
}

test('CreateAcademicPeriod records academic_period.created in the outbox', function () {
    $school = School::factory()->create();
    app(TenantContext::class)->set($school);

    app(CreateAcademicPeriod::class)->handle(new CreateAcademicPeriodData(
        schoolId: $school->id,
        name: '2026-2027',
        startsOn: new DateTimeImmutable('2026-01-01'),
        endsOn: new DateTimeImmutable('2026-12-01'),
    ));

    expect(DB::table('integration_outbox_events')->where('event_name', 'academic_period.created')->count())->toBe(1);
});

test('ActivateAcademicPeriod records academic_period.activated in the outbox', function () {
    $school = School::factory()->create();
    app(TenantContext::class)->set($school);

    $period = AcademicPeriodModel::withoutTenantScope()->create([
        'school_id' => $school->id,
        'name' => '2026-2027',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-01',
        'is_active' => false,
    ]);

    app(ActivateAcademicPeriod::class)->handle($period);

    expect(DB::table('integration_outbox_events')->where('event_name', 'academic_period.activated')->count())->toBe(1);
});

test('period activation preserves exactly one active period per school and schema rejects bypasses', function () {
    $school = School::factory()->create();
    app(TenantContext::class)->set($school);
    $first = AcademicPeriodModel::withoutTenantScope()->create([
        'school_id' => $school->id,
        'name' => '2026',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-01',
        'is_active' => false,
    ]);
    $second = AcademicPeriodModel::withoutTenantScope()->create([
        'school_id' => $school->id,
        'name' => '2027',
        'starts_on' => '2027-01-01',
        'ends_on' => '2027-12-01',
        'is_active' => false,
    ]);

    app(ActivateAcademicPeriod::class)->handle($first);
    app(ActivateAcademicPeriod::class)->handle($second);

    expect(AcademicPeriodModel::withoutTenantScope()->where('school_id', $school->id)->where('is_active', true)->count())
        ->toBe(1);
    expect((bool) $first->fresh()->is_active)->toBeFalse()
        ->and((bool) $second->fresh()->is_active)->toBeTrue();

    expect(fn () => AcademicPeriodModel::withoutTenantScope()->create([
        'school_id' => $school->id,
        'name' => 'Bypass',
        'starts_on' => '2028-01-01',
        'ends_on' => '2028-12-01',
        'is_active' => true,
    ]))->toThrow(QueryException::class);
});

test('CreateAcademicOffer records academic_offer.created in the outbox', function () {
    $school = School::factory()->create();
    $period = AcademicPeriodModel::withoutTenantScope()->create([
        'school_id' => $school->id,
        'name' => '2026-2027',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-01',
        'is_active' => true,
    ]);
    $gradeLevel = GradeLevel::factory()->create(['school_id' => $school->id]);
    $section = Section::factory()->create(['school_id' => $school->id]);

    app(TenantContext::class)->set($school);
    app(AcademicPeriodContext::class)->set(new ActivePeriod(
        id: $period->id,
        name: $period->name,
        startsOn: $period->starts_on,
        endsOn: $period->ends_on,
    ));

    app(CreateAcademicOffer::class)->handle(new CreateAcademicOfferData(
        schoolId: $school->id,
        academicPeriodId: $period->id,
        gradeLevelId: $gradeLevel->id,
        sectionId: $section->id,
        teacherId: null,
        capacity: 30,
    ));

    expect(DB::table('integration_outbox_events')->where('event_name', 'academic_offer.created')->count())->toBe(1);
});

test('CreateAcademicPeriod rolls back source and domain event when outbox recording fails', function () {
    $school = School::factory()->create();
    app(TenantContext::class)->set($school);
    Event::fake([AcademicPeriodCreated::class]);
    failOutboxEvent('academic_period.created');

    expect(fn () => app(CreateAcademicPeriod::class)->handle(new CreateAcademicPeriodData(
        schoolId: $school->id,
        name: '2027',
        startsOn: new DateTimeImmutable('2027-01-01'),
        endsOn: new DateTimeImmutable('2027-12-01'),
    )))->toThrow(QueryException::class);

    $this->assertDatabaseCount('periodos_academicos', 0);
    $this->assertDatabaseCount('integration_outbox_events', 0);
    Event::assertNotDispatched(AcademicPeriodCreated::class);
});

test('ActivateAcademicPeriod rolls back all activation state and domain event when outbox recording fails', function () {
    $school = School::factory()->create();
    app(TenantContext::class)->set($school);
    $active = AcademicPeriodModel::withoutTenantScope()->create([
        'school_id' => $school->id,
        'name' => '2026',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-01',
        'is_active' => true,
    ]);
    $target = AcademicPeriodModel::withoutTenantScope()->create([
        'school_id' => $school->id,
        'name' => '2027',
        'starts_on' => '2027-01-01',
        'ends_on' => '2027-12-01',
        'is_active' => false,
    ]);
    Event::fake([AcademicPeriodActivated::class]);
    failOutboxEvent('academic_period.activated');

    expect(fn () => app(ActivateAcademicPeriod::class)->handle($target))->toThrow(QueryException::class);

    expect((bool) $active->fresh()->is_active)->toBeTrue()
        ->and((bool) $target->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseCount('integration_outbox_events', 0);
    Event::assertNotDispatched(AcademicPeriodActivated::class);
});

test('CreateAcademicOffer rolls back source and domain event when outbox recording fails', function () {
    $school = School::factory()->create();
    $period = AcademicPeriodModel::withoutTenantScope()->create([
        'school_id' => $school->id,
        'name' => '2026',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-01',
        'is_active' => true,
    ]);
    $gradeLevel = GradeLevel::factory()->create(['school_id' => $school->id]);
    $section = Section::factory()->create(['school_id' => $school->id]);
    app(TenantContext::class)->set($school);
    Event::fake([AcademicOfferCreated::class]);
    failOutboxEvent('academic_offer.created');

    expect(fn () => app(CreateAcademicOffer::class)->handle(new CreateAcademicOfferData(
        schoolId: $school->id,
        academicPeriodId: $period->id,
        gradeLevelId: $gradeLevel->id,
        sectionId: $section->id,
        teacherId: null,
        capacity: 30,
    )))->toThrow(QueryException::class);

    $this->assertDatabaseCount('ofertas_academicas', 0);
    $this->assertDatabaseCount('integration_outbox_events', 0);
    Event::assertNotDispatched(AcademicOfferCreated::class);
});

test('CreateEnrollment rolls back source and domain event when outbox recording fails', function () {
    $school = School::factory()->create();
    $period = AcademicPeriodModel::withoutTenantScope()->create([
        'school_id' => $school->id,
        'name' => '2026',
        'starts_on' => '2026-01-01',
        'ends_on' => '2026-12-01',
        'is_active' => true,
    ]);
    $offer = AcademicOffer::factory()->create([
        'school_id' => $school->id,
        'periodo_academico_id' => $period->id,
    ]);
    $student = User::factory()->create(['school_id' => $school->id]);
    $student->assignRole('student');
    app(TenantContext::class)->set($school);
    app(AcademicPeriodContext::class)->set(new ActivePeriod(
        id: $period->id,
        name: $period->name,
        startsOn: $period->starts_on,
        endsOn: $period->ends_on,
    ));
    Event::fake([EnrollmentCreated::class]);
    failOutboxEvent('enrollment.created');

    expect(fn () => app(CreateEnrollment::class)->handle(new CreateEnrollmentData(
        academicOfferId: $offer->id,
        studentId: $student->id,
    )))->toThrow(QueryException::class);

    $this->assertDatabaseCount('matriculas', 0);
    $this->assertDatabaseCount('integration_outbox_events', 0);
    Event::assertNotDispatched(EnrollmentCreated::class);
});
