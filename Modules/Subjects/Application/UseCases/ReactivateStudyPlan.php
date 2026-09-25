<?php

namespace Modules\Subjects\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Subjects\Application\Services\AssignmentSlots;
use Modules\Subjects\Domain\Exceptions\InvalidStatusChange;
use Modules\Subjects\Domain\Exceptions\ReactivationNotApproved;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\ValueObjects\PlanReactivationImpact;

/**
 * Reactivates an archived plan and restores the slots it lost in open
 * periods, replacing their current holders. The impact is recomputed
 * inside the transaction, so what is applied is never staler than what the
 * checks saw; without approval of every conflict nothing changes.
 */
final class ReactivateStudyPlan
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly PlanAssignmentRepositoryInterface $assignments,
        private readonly ReviewStudyPlanReactivation $review,
        private readonly AssignmentSlots $slots,
    ) {}

    /**
     * @param  list<int>  $approvedHolderIds  ids of the current assignments the user approved replacing
     *
     * @throws StudyPlanNotFound
     * @throws InvalidStatusChange when the plan is not archived
     * @throws ReactivationNotApproved when a conflict was not approved
     */
    public function handle(int $planId, int $schoolId, array $approvedHolderIds): PlanReactivationImpact
    {
        return DB::transaction(function () use ($planId, $schoolId, $approvedHolderIds): PlanReactivationImpact {
            $impact = $this->review->handle($planId, $schoolId);
            $impact->assertApproved($approvedHolderIds);

            foreach ($impact->restorations as $restoration) {
                if ($restoration->holder !== null) {
                    $this->slots->vacate($restoration->holder);
                }

                $this->assignments->markCurrent((int) $restoration->restored->id(), $schoolId);
            }

            $plan = $this->plans->findInSchool($planId, $schoolId) ?? throw StudyPlanNotFound::withId($planId);
            $this->plans->save($plan->reactivate());

            return $impact;
        });
    }
}
