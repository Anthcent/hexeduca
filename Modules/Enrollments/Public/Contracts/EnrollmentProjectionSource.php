<?php

namespace Modules\Enrollments\Public\Contracts;

use Modules\Enrollments\Public\DTOs\EnrollmentProjectionRow;

/**
 * Stable public contract for sibling modules that need to build/rebuild a
 * local projection of Enrollments data (Grades) without querying
 * Modules\Enrollments\Infrastructure\Models directly. See plan §5, §10
 * (rebuild) and §13 Fase 7-8. Reference contract for the
 * `grades:rebuild-enrollments` command example in plan §10.
 */
interface EnrollmentProjectionSource
{
    public function find(int $id): ?EnrollmentProjectionRow;

    /**
     * Full source-of-truth listing, for rebuild commands. Not for
     * request-time hot paths.
     *
     * @return iterable<EnrollmentProjectionRow>
     */
    public function allForRebuild(): iterable;
}
