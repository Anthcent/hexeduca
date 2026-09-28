<?php

namespace Modules\Subjects\Application\Services;

use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;
use Modules\Subjects\Domain\Exceptions\PeriodClosed;
use Modules\Subjects\Domain\Services\OpenPeriodPolicy;

final class OpenPeriods
{
    public function __construct(private readonly AcademicPeriodReader $periods) {}

    /**
     * @return list<int>
     */
    public function idsForSchool(int $schoolId): array
    {
        return array_values(array_map(
            fn (AcademicPeriodDTO $period): int => $period->id,
            array_filter($this->periods->allForSchool($schoolId), fn (AcademicPeriodDTO $period): bool => $this->isOpen($period)),
        ));
    }

    public function isOpen(AcademicPeriodDTO $period): bool
    {
        return OpenPeriodPolicy::isOpen($period->isActive, $period->endsOn, now()->toDateString());
    }

    /**
     * A closed period's assignments and exclusions are read-only. An
     * unknown period is left to the caller's own check.
     *
     * @throws PeriodClosed
     */
    public function assertOpen(int $periodId, int $schoolId): void
    {
        $period = $this->periods->findForSchool($periodId, $schoolId);

        if ($period !== null && ! $this->isOpen($period)) {
            throw PeriodClosed::withId($periodId);
        }
    }
}
