<?php

namespace Modules\AcademicPeriods\Public\DTOs;

final readonly class AcademicPeriodDTO
{
    /**
     * @param  string  $startsOn  ISO date (Y-m-d)
     * @param  string  $endsOn  ISO date (Y-m-d)
     */
    public function __construct(
        public int $id,
        public int $schoolId,
        public string $name,
        public bool $isActive,
        public string $startsOn,
        public string $endsOn,
    ) {}
}
