<?php

namespace Modules\AcademicLevels\Public\Contracts;

use Modules\AcademicLevels\Public\DTOs\AcademicLevelDTO;

/**
 * Stable public contract for sibling modules that need to read
 * AcademicLevels data without importing Infrastructure\Models,
 * Domain\Repositories, Application\UseCases or Controllers internal to
 * this module.
 *
 * First real cross-module contract of the refactor — reference pattern for
 * every future sibling-module dependency (see plan §5).
 */
interface AcademicLevelReader
{
    public function find(int $id): ?AcademicLevelDTO;

    /**
     * @return array<int, AcademicLevelDTO>
     */
    public function allForSchool(int $schoolId): array;
}
