<?php

namespace Modules\Subjects\Domain\Repositories;

use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;

interface PlanAssignmentRepositoryInterface
{
    public function save(PlanAssignment $assignment): PlanAssignment;

    /**
     * A current (not replaced) assignment of the school.
     */
    public function findCurrentInSchool(int $id, int $schoolId): ?PlanAssignment;

    public function currentInSlot(int $schoolId, int $periodId, AssignmentScope $scope, int $targetId): ?PlanAssignment;

    /**
     * @return list<PlanAssignment>
     */
    public function currentForPeriod(int $schoolId, int $periodId): array;

    /**
     * Current assignments of the given periods, keyed by slotKey().
     *
     * @param  list<int>  $periodIds
     * @return array<string, PlanAssignment>
     */
    public function currentBySlotForPeriods(int $schoolId, array $periodIds): array;

    /**
     * @param  list<int>  $periodIds
     * @return list<PlanAssignment>
     */
    public function replacedForPlanInPeriods(int $planId, int $schoolId, array $periodIds): array;

    /**
     * Every assignment of the plan, current or replaced (history included).
     */
    public function countForPlan(int $planId, int $schoolId): int;

    /**
     * Whether any assignment of the plan (current or replaced) covers the
     * grade level, i.e. has activated or would activate its subjects.
     */
    public function planCoversGradeLevel(int $planId, int $gradeLevelId, int $schoolId): bool;

    /**
     * Deletes the assignment and, by cascade, its exclusions.
     */
    public function delete(int $id, int $schoolId): void;

    public function markReplaced(int $id, int $schoolId): void;

    public function markCurrent(int $id, int $schoolId): void;

    /**
     * @return list<int>
     */
    public function excludedSubjectIds(int $assignmentId, int $schoolId): array;

    /**
     * @param  list<int>  $assignmentIds
     * @return array<int, list<int>> excluded subject ids keyed by assignment id
     */
    public function excludedSubjectIdsFor(array $assignmentIds, int $schoolId): array;

    /**
     * Current assignments of the plan, in every period.
     *
     * @return list<PlanAssignment>
     */
    public function currentForPlan(int $planId, int $schoolId): array;

    public function setExclusion(int $assignmentId, int $subjectId, bool $excluded, int $schoolId): void;

    public function copyExclusions(int $fromAssignmentId, int $toAssignmentId, int $schoolId): void;
}
