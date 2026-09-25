<?php

namespace Modules\Subjects\Application\DTOs;

final readonly class StudyPlanData
{
    public function __construct(
        public int $schoolId,
        public string $code,
        public string $name,
        public ?string $observation,
    ) {}
}
