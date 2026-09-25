<?php

namespace Modules\Subjects\Application\UseCases;

use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Exceptions\InvalidStatusChange;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Exceptions\SubjectNotFound;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;

/**
 * Archives or reactivates a subject. Both need an active plan: an archived
 * plan is frozen together with its subjects. A subject has no slots, so
 * its reactivation never conflicts; the review screen only informs.
 */
final class ChangeSubjectStatus
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly SubjectRepositoryInterface $subjects,
    ) {}

    /**
     * @throws StudyPlanNotFound
     * @throws SubjectNotFound
     * @throws RecordArchived when the plan is archived
     * @throws InvalidStatusChange
     */
    public function archive(int $subjectId, int $planId, int $schoolId): Subject
    {
        return $this->subjects->save($this->load($subjectId, $planId, $schoolId)->archive());
    }

    /**
     * @throws StudyPlanNotFound
     * @throws SubjectNotFound
     * @throws RecordArchived when the plan is archived
     * @throws InvalidStatusChange
     */
    public function reactivate(int $subjectId, int $planId, int $schoolId): Subject
    {
        return $this->subjects->save($this->load($subjectId, $planId, $schoolId)->reactivate());
    }

    private function load(int $subjectId, int $planId, int $schoolId): Subject
    {
        $plan = $this->plans->findInSchool($planId, $schoolId) ?? throw StudyPlanNotFound::withId($planId);
        $subject = $this->subjects->findInPlan($subjectId, $planId, $schoolId) ?? throw SubjectNotFound::withId($subjectId);
        $plan->assertEditable();

        return $subject;
    }
}
