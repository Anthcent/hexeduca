<?php

namespace Modules\Subjects\Application\Services;

use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;

/**
 * Frees a slot before another assignment takes it. An assignment of an
 * archived plan is frozen history, so it is only marked replaced (a later
 * reactivation may restore it); any other one is deleted with its
 * exclusions. Call it inside the caller's transaction.
 */
final class AssignmentSlots
{
    public function __construct(
        private readonly PlanAssignmentRepositoryInterface $assignments,
        private readonly StudyPlanRepositoryInterface $plans,
    ) {}

    public function vacate(PlanAssignment $holder): void
    {
        $plan = $this->plans->findInSchool($holder->planId(), $holder->schoolId());

        if ($plan !== null && $plan->isArchived()) {
            $this->assignments->markReplaced((int) $holder->id(), $holder->schoolId());

            return;
        }

        $this->assignments->delete((int) $holder->id(), $holder->schoolId());
    }
}
