<?php

namespace Modules\Subjects\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\Exceptions\RecordInUse;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;

final class DeleteStudyPlan
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly PlanAssignmentRepositoryInterface $assignments,
    ) {}

    /**
     * @throws StudyPlanNotFound
     * @throws RecordInUse when the plan has subjects or assignments (archive it instead)
     */
    public function handle(int $planId, int $schoolId): void
    {
        DB::transaction(function () use ($planId, $schoolId): void {
            $this->plans->findInSchool($planId, $schoolId) ?? throw StudyPlanNotFound::withId($planId);

            $deletable = StudyPlan::canBeDeleted(
                $this->subjects->countForPlan($planId, $schoolId),
                $this->assignments->countForPlan($planId, $schoolId),
            );

            if (! $deletable) {
                throw RecordInUse::plan($planId);
            }

            $this->plans->delete($planId, $schoolId);
        });
    }
}
