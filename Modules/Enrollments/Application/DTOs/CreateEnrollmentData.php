<?php

namespace Modules\Enrollments\Application\DTOs;

use DateTimeImmutable;

final readonly class CreateEnrollmentData
{
    public function __construct(
        public int $academicOfferId,
        public int $studentId,
        public string $status = 'active',
        public ?DateTimeImmutable $enrolledAt = null,
    ) {}
}
