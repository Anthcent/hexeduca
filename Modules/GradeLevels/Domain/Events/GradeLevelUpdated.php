<?php

namespace Modules\GradeLevels\Domain\Events;

final readonly class GradeLevelUpdated
{
    public function __construct(
        public int $gradeLevelId,
        public int $schoolId,
    ) {}
}
