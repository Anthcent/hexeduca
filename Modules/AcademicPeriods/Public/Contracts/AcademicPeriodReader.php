<?php

namespace Modules\AcademicPeriods\Public\Contracts;

use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;

/**
 * Read-only, tenant-scoped access to a school's academic periods for sibling
 * modules (e.g. a period selector). Every method filters by `$schoolId`.
 */
interface AcademicPeriodReader
{
    public function findForSchool(int $id, int $schoolId): ?AcademicPeriodDTO;

    public function activeForSchool(int $schoolId): ?AcademicPeriodDTO;

    /**
     * Newest first (by start date).
     *
     * @return array<int, AcademicPeriodDTO>
     */
    public function allForSchool(int $schoolId): array;
}
