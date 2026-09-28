<?php

namespace Modules\Subjects\Domain\Repositories;

use Modules\Subjects\Domain\Entities\Subject;

interface SubjectRepositoryInterface
{
    public function save(Subject $subject): Subject;

    public function findInPlan(int $id, int $planId, int $schoolId): ?Subject;

    public function delete(int $id, int $schoolId): void;

    public function countForPlan(int $planId, int $schoolId): int;

    /**
     * @return list<Subject>
     */
    public function forPlan(int $planId, int $schoolId): array;

    /**
     * @param  list<int>  $planIds
     * @return array<int, list<Subject>> keyed by plan id
     */
    public function forPlans(array $planIds, int $schoolId): array;

    /**
     * The given subjects that are excluded on a current assignment of one of
     * the periods. Exclusions on replaced assignments are history.
     *
     * @param  list<int>  $subjectIds
     * @param  list<int>  $periodIds
     * @return list<int>
     */
    public function excludedInPeriods(array $subjectIds, int $schoolId, array $periodIds): array;

    /**
     * Deletes every exclusion of the subject, in any period.
     */
    public function deleteExclusions(int $subjectId, int $schoolId): void;
}
