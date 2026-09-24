<?php

namespace Modules\GradeLevels\Public\Contracts;

use Modules\GradeLevels\Public\DTOs\GradeLevelDTO;

interface GradeLevelReader
{
    public function find(int $id): ?GradeLevelDTO;

    public function findForSchool(int $id, int $schoolId): ?GradeLevelDTO;

    /**
     * @return array<int, GradeLevelDTO>
     */
    public function allForSchool(int $schoolId): array;
}
