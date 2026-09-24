<?php

namespace Modules\AcademicLevels\Domain\Repositories;

use Modules\AcademicLevels\Domain\Entities\AcademicLevel;

interface AcademicLevelRepositoryInterface
{
    public function findById(int $id): ?AcademicLevel;

    public function save(AcademicLevel $academicLevel): AcademicLevel;
}
