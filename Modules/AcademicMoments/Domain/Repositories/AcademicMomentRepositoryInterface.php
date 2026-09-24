<?php

namespace Modules\AcademicMoments\Domain\Repositories;

use Modules\AcademicMoments\Domain\Entities\AcademicMoment;

interface AcademicMomentRepositoryInterface
{
    public function findById(int $id): ?AcademicMoment;

    public function save(AcademicMoment $academicMoment): AcademicMoment;
}
