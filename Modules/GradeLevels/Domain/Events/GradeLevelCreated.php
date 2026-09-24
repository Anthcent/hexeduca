<?php

namespace Modules\GradeLevels\Domain\Events;

final readonly class GradeLevelCreated
{
    public function __construct(
        public int $gradeLevelId,
        public int $schoolId,
    ) {}
}
