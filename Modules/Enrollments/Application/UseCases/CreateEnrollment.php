<?php

namespace Modules\Enrollments\Application\UseCases;

use App\IntegrationEvents\Outbox\OutboxEventRecorder;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferProjectionSource;
use Modules\Enrollments\Application\DTOs\CreateEnrollmentData;
use Modules\Enrollments\Domain\Entities\Enrollment;
use Modules\Enrollments\Domain\Events\EnrollmentCreated as DomainEnrollmentCreated;
use Modules\Enrollments\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Enrollments\Public\Events\EnrollmentCreated as IntegrationEnrollmentCreated;
use Modules\Users\Public\Contracts\StudentReader;

/**
 * Reads the target AcademicOffer exclusively through
 * Modules\AcademicOffers\Public\Contracts\AcademicOfferProjectionSource —
 * never through Modules\AcademicOffers\Domain\Repositories or
 * Infrastructure\Models. This is the one place where Enrollments needs
 * another module's data mid-transaction (capacity + existence), as
 * opposed to a background projection (plan §5/§8).
 */
final class CreateEnrollment
{
    public function __construct(
        private readonly AcademicOfferProjectionSource $academicOffers,
        private readonly EnrollmentRepositoryInterface $enrollments,
        private readonly OutboxEventRecorder $outbox,
        private readonly StudentReader $students,
    ) {}

    public function handle(CreateEnrollmentData $data): Enrollment
    {
        $saved = DB::transaction(function () use ($data) {
            // Both enrollment paths lock this same ofertas_academicas row.
            // PostgreSQL therefore serializes duplicate/capacity decisions
            // for one offer; SQLite keeps this code portable but its tests do
            // not claim to prove row-lock concurrency semantics.
            $academicOffer = $this->academicOffers->findForEnrollment($data->academicOfferId);

            if ($academicOffer === null) {
                throw new DomainException('The AcademicOffer to enroll into does not exist.');
            }

            if ($this->students->findForSchool($data->studentId, $academicOffer->schoolId) === null) {
                throw new DomainException('The enrolled user must be a student in the AcademicOffer school.');
            }

            if ($this->enrollments->existsForAcademicOfferAndStudent($data->academicOfferId, $data->studentId)) {
                throw new DomainException('The student is already enrolled in this AcademicOffer.');
            }

            if ($this->enrollments->countByAcademicOffer($data->academicOfferId) >= $academicOffer->capacity) {
                throw new DomainException('The AcademicOffer has reached its enrollment capacity.');
            }

            $enrollment = new Enrollment(
                id: null,
                schoolId: $academicOffer->schoolId,
                academicPeriodId: $academicOffer->academicPeriodId,
                academicOfferId: $data->academicOfferId,
                studentId: $data->studentId,
                status: $data->status,
                enrolledAt: $data->enrolledAt ?? new DateTimeImmutable,
            );

            $saved = $this->enrollments->save($enrollment);

            // Recorded to the outbox inside the write transaction — see
            // plan §9/§10. This is the reference event the Grades
            // projection listener consumes.
            $this->outbox->record(new IntegrationEnrollmentCreated(
                enrollmentId: $saved->id(),
                schoolId: $saved->schoolId(),
                academicPeriodId: $saved->academicPeriodId(),
                academicOfferId: $saved->academicOfferId(),
                studentId: $saved->studentId(),
                status: $saved->status(),
                version: $saved->version(),
            ));

            return $saved;
        });

        event(new DomainEnrollmentCreated($saved));

        return $saved;
    }
}
