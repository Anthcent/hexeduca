<?php

namespace Modules\AcademicMoments\Public\Contracts;

use Modules\AcademicMoments\Public\DTOs\AcademicMomentDTO;

interface AcademicMomentReader
{
    /**
     * The moments of one of the school's periods, by order.
     *
     * @return list<AcademicMomentDTO>
     */
    public function forPeriod(int $schoolId, int $periodId): array;

    /**
     * A moment, only when its period belongs to the school.
     */
    public function findForSchool(int $id, int $schoolId): ?AcademicMomentDTO;
}
