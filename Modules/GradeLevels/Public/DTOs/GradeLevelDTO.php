<?php

namespace Modules\GradeLevels\Public\DTOs;

final readonly class GradeLevelDTO
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $name,
        public int $order,
    ) {}
}
