<?php

namespace Modules\AcademicPeriods\Domain\Repositories;

use Modules\AcademicPeriods\Domain\Entities\AcademicPeriod;

interface AcademicPeriodRepositoryInterface
{
    public function findById(int $id): ?AcademicPeriod;

    public function save(AcademicPeriod $academicPeriod): AcademicPeriod;
}
