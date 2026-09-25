<?php

namespace Modules\Subjects\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\RecordInUse;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Exceptions\SubjectNotFound;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;

final class DeleteSubject
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly PlanAssignmentRepositoryInterface $assignments,
    ) {}

    /**
     * @throws StudyPlanNotFound
     * @throws SubjectNotFound
     * @throws RecordArchived when the plan is archived
     * @throws RecordInUse when an assignment activates it or it has exclusions (archive it instead)
     */
    public function handle(int $subjectId, int $planId, int $schoolId): void
    {
        DB::transaction(function () use ($subjectId, $planId, $schoolId): void {
            $plan = $this->plans->findInSchool($planId, $schoolId) ?? throw StudyPlanNotFound::withId($planId);
            $subject = $this->subjects->findInPlan($subjectId, $planId, $schoolId) ?? throw SubjectNotFound::withId($subjectId);
            $plan->assertEditable();

            $deletable = Subject::canBeDeleted(
                $this->assignments->planCoversGradeLevel($planId, $subject->gradeLevelId(), $schoolId),
                $this->subjects->hasExclusions($subjectId, $schoolId),
            );

            if (! $deletable) {
                throw RecordInUse::subject($subjectId);
            }

            $this->subjects->delete($subjectId, $schoolId);
        });
    }
}
