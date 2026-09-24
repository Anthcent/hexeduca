<?php

namespace Modules\Enrollments\Domain\Repositories;

use Modules\Enrollments\Domain\Entities\Enrollment;

interface EnrollmentRepositoryInterface
{
    public function findById(int $id): ?Enrollment;

    /**
     * Counts enrollments with status = 'active' for an offering — used to
     * enforce the offering's capacity before inserting a new Enrollment. A
     * withdrawn/inactive enrollment does not occupy a capacity seat.
     */
    public function countByAcademicOffer(int $academicOfferId): int;

    /**
     * True when the student is already enrolled in the offering — enforces
     * the unique(academic_offer_id, student_id) constraint before insert.
     */
    public function existsForAcademicOfferAndStudent(int $academicOfferId, int $studentId): bool;

    public function save(Enrollment $enrollment): Enrollment;
}
