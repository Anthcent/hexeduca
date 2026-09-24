<?php

namespace Modules\GradeLevels\Domain\Repositories;

use Modules\GradeLevels\Domain\Entities\GradeLevel;

interface GradeLevelRepositoryInterface
{
    public function findById(int $id): ?GradeLevel;

    public function save(GradeLevel $gradeLevel): GradeLevel;
}
