<?php

namespace Modules\Subjects\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Subjects\Application\Services\OpenPeriods;
use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\RecordInUse;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Exceptions\SubjectNotFound;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;

/**
 * Only open periods can block the delete. The subject's exclusions in
 * closed periods are history that goes with it.
 */
final class DeleteSubject
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly PlanAssignmentRepositoryInterface $assignments,
        private readonly OpenPeriods $openPeriods,
    ) {}

    /**
     * @throws StudyPlanNotFound
     * @throws SubjectNotFound
     * @throws RecordArchived when the plan is archived
     * @throws RecordInUse when it is active or excluded in an open period (archive it instead)
     */
    public function handle(int $subjectId, int $planId, int $schoolId): void
    {
        DB::transaction(function () use ($subjectId, $planId, $schoolId): void {
            $plan = $this->plans->findInSchool($planId, $schoolId) ?? throw StudyPlanNotFound::withId($planId);
            $subject = $this->subjects->findInPlan($subjectId, $planId, $schoolId) ?? throw SubjectNotFound::withId($subjectId);
            $plan->assertEditable();

            $openPeriodIds = $this->openPeriods->idsForSchool($schoolId);
            $deletable = Subject::canBeDeleted(
                $this->assignments->planCoversGradeLevelInPeriods($planId, $subject->gradeLevelId(), $schoolId, $openPeriodIds),
                $this->subjects->excludedInPeriods([$subjectId], $schoolId, $openPeriodIds) !== [],
            );

            if (! $deletable) {
                throw RecordInUse::subject($subjectId);
            }

            // Only closed-period exclusions are left; their FK restricts the delete.
            $this->subjects->deleteExclusions($subjectId, $schoolId);
            $this->subjects->delete($subjectId, $schoolId);
        });
    }
}
