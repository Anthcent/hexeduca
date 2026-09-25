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

    public function hasExclusions(int $subjectId, int $schoolId): bool;
}
