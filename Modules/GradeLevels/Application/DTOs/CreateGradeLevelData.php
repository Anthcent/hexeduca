<?php

namespace Modules\GradeLevels\Application\DTOs;

final readonly class CreateGradeLevelData
{
    public function __construct(
        public int $schoolId,
        public int $academicLevelId,
        public string $name,
        public int $order,
    ) {}
}
