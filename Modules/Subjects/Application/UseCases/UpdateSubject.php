<?php

namespace Modules\Subjects\Application\UseCases;

use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\Subjects\Application\DTOs\SubjectData;
use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Exceptions\InvalidSubject;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Exceptions\SubjectNotFound;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;

final class UpdateSubject
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly GradeLevelReader $gradeLevels,
    ) {}

    /**
     * @throws StudyPlanNotFound
     * @throws SubjectNotFound
     * @throws RecordArchived when the plan or the subject is archived
     * @throws InvalidSubject
     */
    public function handle(int $subjectId, SubjectData $data): Subject
    {
        $plan = $this->plans->findInSchool($data->planId, $data->schoolId) ?? throw StudyPlanNotFound::withId($data->planId);
        $subject = $this->subjects->findInPlan($subjectId, $data->planId, $data->schoolId) ?? throw SubjectNotFound::withId($subjectId);
        $plan->assertEditable();

        if ($this->gradeLevels->findForSchool($data->gradeLevelId, $data->schoolId) === null) {
            throw InvalidSubject::unknownGradeLevel($data->gradeLevelId);
        }

        return $this->subjects->save($subject->withDetails($data->gradeLevelId, $data->name, $data->code, $data->weeklyHours));
    }
}
