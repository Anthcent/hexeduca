<?php

namespace Modules\Enrollments\Public\DTOs;

use DateTimeImmutable;

/**
 * Plain read-only projection of an Enrollment, for other modules (Grades)
 * to build/rebuild their own local projections from. See plan §5/§8/§10.
 */
final readonly class EnrollmentProjectionRow
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicPeriodId,
        public int $academicOfferId,
        public int $studentId,
        public string $status,
        public DateTimeImmutable $enrolledAt,
        public int $version,
    ) {}
}
