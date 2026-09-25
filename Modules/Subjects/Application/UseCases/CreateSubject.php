<?php

namespace Modules\Subjects\Application\UseCases;

use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\Subjects\Application\DTOs\SubjectData;
use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Exceptions\InvalidSubject;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;

final class CreateSubject
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly GradeLevelReader $gradeLevels,
    ) {}

    /**
     * @throws StudyPlanNotFound
     * @throws RecordArchived when the plan is archived
     * @throws InvalidSubject
     */
    public function handle(SubjectData $data): Subject
    {
        $plan = $this->plans->findInSchool($data->planId, $data->schoolId) ?? throw StudyPlanNotFound::withId($data->planId);
        $plan->assertEditable();

        if ($this->gradeLevels->findForSchool($data->gradeLevelId, $data->schoolId) === null) {
            throw InvalidSubject::unknownGradeLevel($data->gradeLevelId);
        }

        return $this->subjects->save(Subject::create(
            $data->schoolId,
            $data->planId,
            $data->gradeLevelId,
            $data->name,
            $data->code,
            $data->weeklyHours,
        ));
    }
}
