<?php

namespace Modules\Users\Public\Contracts;

use Modules\Users\Public\DTOs\TeacherDTO;

/**
 * Stable public contract for sibling modules (e.g. AcademicOffers) that
 * need to read teacher data without importing Infrastructure\Models,
 * Domain\Repositories, Application\UseCases or Controllers internal to
 * this module. See plan §5.
 */
interface TeacherReader
{
    public function find(int $id): ?TeacherDTO;

    public function findForSchool(int $id, int $schoolId): ?TeacherDTO;

    /**
     * @return array<int, TeacherDTO>
     */
    public function all(): array;

    /**
     * @return array<int, TeacherDTO>
     */
    public function allForSchool(int $schoolId): array;
}
