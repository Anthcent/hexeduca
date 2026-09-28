<?php

namespace Modules\Subjects\Application\UseCases;

use Modules\Subjects\Application\Services\OpenPeriods;
use Modules\Subjects\Domain\Exceptions\InvalidAssignment;
use Modules\Subjects\Domain\Exceptions\PeriodClosed;
use Modules\Subjects\Domain\Exceptions\PlanAssignmentNotFound;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;

/**
 * Unchecks (excludes) or re-checks a subject on one assignment. The change
 * applies to every offer that takes its plan from that assignment.
 */
final class SetSubjectExclusion
{
    public function __construct(
        private readonly PlanAssignmentRepositoryInterface $assignments,
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly OpenPeriods $openPeriods,
    ) {}

    /**
     * @throws PlanAssignmentNotFound
     * @throws PeriodClosed
     * @throws StudyPlanNotFound
     * @throws RecordArchived when the assigned plan is archived (frozen)
     * @throws InvalidAssignment when the subject is not an active subject of the plan for a covered grade level
     */
    public function handle(int $assignmentId, int $subjectId, bool $excluded, int $schoolId): void
    {
        $assignment = $this->assignments->findCurrentInSchool($assignmentId, $schoolId) ?? throw PlanAssignmentNotFound::withId($assignmentId);
        $this->openPeriods->assertOpen($assignment->periodId(), $schoolId);
        $plan = $this->plans->findInSchool($assignment->planId(), $schoolId) ?? throw StudyPlanNotFound::withId($assignment->planId());
        $plan->assertEditable();

        $subject = $this->subjects->findInPlan($subjectId, $assignment->planId(), $schoolId);

        if ($subject === null || $subject->isArchived() || ! $assignment->coversGradeLevel($subject->gradeLevelId())) {
            throw InvalidAssignment::subjectOutsidePlan($subjectId);
        }

        $this->assignments->setExclusion($assignmentId, $subjectId, $excluded, $schoolId);
    }
}
