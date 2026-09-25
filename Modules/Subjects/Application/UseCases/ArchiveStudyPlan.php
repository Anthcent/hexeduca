<?php

namespace Modules\Subjects\Application\UseCases;

use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\Exceptions\InvalidStatusChange;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;

final class ArchiveStudyPlan
{
    public function __construct(private readonly StudyPlanRepositoryInterface $plans) {}

    /**
     * Its assignments stay in place, frozen: they keep giving their slots
     * the plan until staff assigns another plan there.
     *
     * @throws StudyPlanNotFound
     * @throws InvalidStatusChange
     */
    public function handle(int $planId, int $schoolId): StudyPlan
    {
        $plan = $this->plans->findInSchool($planId, $schoolId) ?? throw StudyPlanNotFound::withId($planId);

        return $this->plans->save($plan->archive());
    }
}
