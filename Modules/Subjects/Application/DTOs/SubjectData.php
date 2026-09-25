<?php

namespace Modules\Subjects\Application\DTOs;

final readonly class SubjectData
{
    public function __construct(
        public int $schoolId,
        public int $planId,
        public int $gradeLevelId,
        public string $name,
        public ?string $code,
        public ?int $weeklyHours,
    ) {}
}
