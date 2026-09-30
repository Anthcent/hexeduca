<?php

namespace Modules\Subjects\Application\UseCases;

use Illuminate\Support\Facades\DB;
use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Exceptions\InvalidSubject;
use Modules\Subjects\Domain\Exceptions\RecordArchived;
use Modules\Subjects\Domain\Exceptions\StudyPlanNotFound;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;

/**
 * Quick entry: several subjects of one grade level at once, by name only.
 * Code and weekly hours are filled in later from the subject's edit form.
 */
final class CreateSubjects
{
    public function __construct(
        private readonly StudyPlanRepositoryInterface $plans,
        private readonly SubjectRepositoryInterface $subjects,
        private readonly GradeLevelReader $gradeLevels,
    ) {}

    /**
     * @param  list<string>  $names
     * @return int how many subjects were created
     *
     * @throws StudyPlanNotFound
     * @throws RecordArchived when the plan is archived
     * @throws InvalidSubject
     */
    public function handle(int $schoolId, int $planId, int $gradeLevelId, array $names): int
    {
        $plan = $this->plans->findInSchool($planId, $schoolId) ?? throw StudyPlanNotFound::withId($planId);
        $plan->assertEditable();

        if ($this->gradeLevels->findForSchool($gradeLevelId, $schoolId) === null) {
            throw InvalidSubject::unknownGradeLevel($gradeLevelId);
        }

        return DB::transaction(function () use ($schoolId, $planId, $gradeLevelId, $names): int {
            foreach ($names as $name) {
                $this->subjects->save(Subject::create($schoolId, $planId, $gradeLevelId, $name, null, null));
            }

            return count($names);
        });
    }
}
