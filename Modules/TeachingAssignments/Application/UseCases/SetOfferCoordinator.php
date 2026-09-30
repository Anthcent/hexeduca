<?php

namespace Modules\TeachingAssignments\Application\UseCases;

use Modules\TeachingAssignments\Application\Services\PeriodGuard;
use Modules\TeachingAssignments\Domain\Exceptions\InvalidTeachingAssignment;
use Modules\TeachingAssignments\Domain\Exceptions\PeriodClosed;
use Modules\TeachingAssignments\Domain\Repositories\TeachingAssignmentRepositoryInterface;
use Modules\Users\Public\Contracts\TeacherReader;

/**
 * Sets or clears the coordinator of an offer (null clears it).
 */
final class SetOfferCoordinator
{
    public function __construct(
        private readonly TeachingAssignmentRepositoryInterface $assignments,
        private readonly PeriodGuard $guard,
        private readonly TeacherReader $teachers,
    ) {}

    /**
     * @throws InvalidTeachingAssignment
     * @throws PeriodClosed
     */
    public function handle(int $schoolId, int $periodId, int $offerId, ?int $teacherId): void
    {
        $this->guard->openPeriod($periodId, $schoolId);
        $this->guard->offerInPeriod($offerId, $periodId, $schoolId);

        if ($teacherId !== null && $this->teachers->findForSchool($teacherId, $schoolId) === null) {
            throw InvalidTeachingAssignment::unknownTeacher($teacherId);
        }

        $this->assignments->setCoordinator($schoolId, $periodId, $offerId, $teacherId);
    }
}
