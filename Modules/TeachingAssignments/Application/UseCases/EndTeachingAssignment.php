<?php

namespace Modules\TeachingAssignments\Application\UseCases;

use Modules\TeachingAssignments\Application\Services\PeriodGuard;
use Modules\TeachingAssignments\Domain\Exceptions\AssignmentNotFound;
use Modules\TeachingAssignments\Domain\Exceptions\PeriodClosed;
use Modules\TeachingAssignments\Domain\Repositories\TeachingAssignmentRepositoryInterface;

/**
 * Removes a teacher from a subject: the assignment ends today and stays as
 * history.
 */
final class EndTeachingAssignment
{
    public function __construct(
        private readonly TeachingAssignmentRepositoryInterface $assignments,
        private readonly PeriodGuard $guard,
    ) {}

    /**
     * @throws AssignmentNotFound
     * @throws PeriodClosed
     */
    public function handle(int $assignmentId, int $schoolId): void
    {
        $assignment = $this->assignments->findActiveInSchool($assignmentId, $schoolId) ?? throw AssignmentNotFound::withId($assignmentId);
        $this->guard->openPeriod($assignment->periodId(), $schoolId);

        $this->assignments->save($assignment->end(now()->toDateString()));
    }
}
