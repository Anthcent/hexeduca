<?php

namespace Modules\Grades\Domain\Repositories;

use Modules\Grades\Domain\Entities\Grade;

interface GradeRepositoryInterface
{
    public function findById(int $id): ?Grade;

    public function save(Grade $grade): Grade;
}
