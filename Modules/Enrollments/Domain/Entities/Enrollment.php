<?php

namespace Modules\Enrollments\Domain\Entities;

use DateTimeImmutable;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 *
 * Enrolls a student into exactly one AcademicOffer. MUST NOT reference a
 * catalog GradeLevel/Section directly — enrollment always targets an
 * offering (mirrors the invariant already enforced by
 * tests/Architecture/Academic/EnrollmentTargetsOfertaOnlyTest for the
 * legacy Matricula). Tenant+period tier.
 *
 * Port of Modules\Academic\Domain\Entities\Matricula — same behavior,
 * this module is now the sole owner of the enrollment domain.
 */
final class Enrollment
{
    public function __construct(
        private readonly ?int $id,
        private readonly int $schoolId,
        private readonly int $academicPeriodId,
        private readonly int $academicOfferId,
        private readonly int $studentId,
        private string $status,
        private readonly DateTimeImmutable $enrolledAt,
        private readonly int $version = 1,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function schoolId(): int
    {
        return $this->schoolId;
    }

    public function academicPeriodId(): int
    {
        return $this->academicPeriodId;
    }

    public function academicOfferId(): int
    {
        return $this->academicOfferId;
    }

    public function studentId(): int
    {
        return $this->studentId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function enrolledAt(): DateTimeImmutable
    {
        return $this->enrolledAt;
    }

    public function version(): int
    {
        return $this->version;
    }

    public function changeStatusTo(string $status): void
    {
        $this->status = $status;
    }
}
