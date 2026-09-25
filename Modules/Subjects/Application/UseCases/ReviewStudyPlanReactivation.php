<?php

namespace Modules\Subjects\Application\UseCases;

use Modules\Subjects\Application\Services\OpenPeriods;
use Modules\Subjects\Domain\Exceptions\InvalidStatusChange;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Services\PlanReactivationPlanner;
use Modules\Subjects\Domain\ValueObjects\PlanReactivationImpact;

/**
 * Computes, without changing anything, what reactivating an archived plan
 * would do to the assignments of the open periods.
 */
final class ReviewStudyPlanReactivation
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly PlanAssignmentRepositoryInterface $assignments,
        private readonly OpenPeriods $openPeriods,
    ) {}

    /**
     * @throws StudyPlanNotFound
     * @throws InvalidStatusChange when the plan is not archived
     */
    public function handle(int $planId, int $schoolId): PlanReactivationImpact
    {
        $plan = $this->plans->findInSchool($planId, $schoolId) ?? throw StudyPlanNotFound::withId($planId);

        if (! $plan->isArchived()) {
            throw InvalidStatusChange::notArchived();
        }

        $periodIds = $this->openPeriods->idsForSchool($schoolId);

        return PlanReactivationPlanner::plan(
            $planId,
            $this->assignments->replacedForPlanInPeriods($planId, $schoolId, $periodIds),
            $this->assignments->currentBySlotForPeriods($schoolId, $periodIds),
        );
    }
}
