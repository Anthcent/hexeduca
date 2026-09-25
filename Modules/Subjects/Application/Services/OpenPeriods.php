<?php

namespace Modules\Subjects\Application\Services;

use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;
use Modules\Subjects\Domain\Services\OpenPeriodPolicy;

final class OpenPeriods
{
    public function __construct(private readonly AcademicPeriodReader $periods) {}

    /**
     * @return list<int>
     */
    public function idsForSchool(int $schoolId): array
    {
        $today = now()->toDateString();

        return array_values(array_map(
            fn (AcademicPeriodDTO $period): int => $period->id,
            array_filter(
                $this->periods->allForSchool($schoolId),
                fn (AcademicPeriodDTO $period): bool => OpenPeriodPolicy::isOpen($period->isActive, $period->endsOn, $today),
            ),
        ));
    }
}
