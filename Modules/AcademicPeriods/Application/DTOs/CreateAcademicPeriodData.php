<?php

namespace Modules\AcademicPeriods\Application\DTOs;

final class CreateAcademicPeriodData
{
    public function __construct(
        public readonly int $schoolId,
        public readonly string $name,
        public readonly \DateTimeImmutable $startsOn,
        public readonly \DateTimeImmutable $endsOn,
    ) {}
}
