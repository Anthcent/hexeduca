<?php

namespace Modules\TeachingAssignments\Public\Contracts;

use Modules\TeachingAssignments\Public\DTOs\TeachingAssignmentDTO;

/**
 * Who teaches what. Only ACTIVE assignments unless stated otherwise; an ended
 * assignment is history.
 */
interface TeachingAssignmentReader
{
    /**
     * A teacher's active assignments in a period (their subjects and offers).
     *
     * @return list<TeachingAssignmentDTO>
     */
    public function forTeacher(int $schoolId, int $periodId, int $teacherId): array;

    /**
     * The active assignments of one offer's subject (titular and substitute).
     *
     * @return list<TeachingAssignmentDTO>
     */
    public function forOfferSubject(int $schoolId, int $offerId, int $subjectId): array;

    /**
     * Whether the teacher actively teaches that subject of that offer, in any role.
     */
    public function teaches(int $schoolId, int $teacherId, int $offerId, int $subjectId): bool;
}
