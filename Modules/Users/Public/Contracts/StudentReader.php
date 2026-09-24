<?php

namespace Modules\Users\Public\Contracts;

use Modules\Users\Public\DTOs\StudentDTO;

/**
 * Stable public contract for sibling modules (e.g. Enrollments) that
 * need to read student data without importing Infrastructure\Models,
 * Domain\Repositories, Application\UseCases or Controllers internal to
 * this module. See plan §5.
 */
interface StudentReader
{
    public function find(int $id): ?StudentDTO;

    public function findForSchool(int $id, int $schoolId): ?StudentDTO;

    /**
     * @return array<int, StudentDTO>
     */
    public function all(): array;

    /**
     * @return array<int, StudentDTO>
     */
    public function allForSchool(int $schoolId): array;
}
