<?php

namespace Modules\TeachingAssignments\Domain\Repositories;

use Modules\TeachingAssignments\Domain\Entities\TeachingAssignment;
use Modules\TeachingAssignments\Domain\ValueObjects\TeachingRole;

interface TeachingAssignmentRepositoryInterface
{
    public function save(TeachingAssignment $assignment): TeachingAssignment;

    public function findActiveInSchool(int $id, int $schoolId): ?TeachingAssignment;

    public function activeInSlot(int $schoolId, int $offerId, int $subjectId, TeachingRole $role): ?TeachingAssignment;

    /**
     * @return list<TeachingAssignment>
     */
    public function activeForPeriod(int $schoolId, int $periodId): array;

    /**
     * @return array<int, int> coordinator teacher id keyed by offer id
     */
    public function coordinatorsForPeriod(int $schoolId, int $periodId): array;

    public function setCoordinator(int $schoolId, int $periodId, int $offerId, ?int $teacherId): void;
}
