<?php

namespace Modules\Subjects\Infrastructure\Persistence;

use Modules\Subjects\Domain\Entities\PlanAssignment;
use Modules\Subjects\Domain\Repositories\PlanAssignmentRepositoryInterface;
use Modules\Subjects\Domain\ValueObjects\AssignmentScope;
use Modules\Subjects\Infrastructure\Models\PlanAssignmentModel;
use Modules\Subjects\Infrastructure\Models\SubjectExclusionModel;

final class EloquentPlanAssignmentRepository implements PlanAssignmentRepositoryInterface
{
    public function save(PlanAssignment $assignment): PlanAssignment
    {
        $model = $assignment->id()
            ? PlanAssignmentModel::query()->where('school_id', $assignment->schoolId())->findOrFail($assignment->id())
            : new PlanAssignmentModel;

        $model->fill([
            'school_id' => $assignment->schoolId(),
            'academic_period_id' => $assignment->periodId(),
            'study_plan_id' => $assignment->planId(),
            'scope' => $assignment->scope()->value,
            'grade_level_id' => $assignment->gradeLevelId(),
            'academic_offer_id' => $assignment->offerId(),
            'target_id' => $assignment->targetId(),
        ])->save();

        return $model->toEntity();
    }

    public function findCurrentInSchool(int $id, int $schoolId): ?PlanAssignment
    {
        return PlanAssignmentModel::query()->where('school_id', $schoolId)->current()->find($id)?->toEntity();
    }

    public function currentInSlot(int $schoolId, int $periodId, AssignmentScope $scope, int $targetId): ?PlanAssignment
    {
        return PlanAssignmentModel::query()
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $periodId)
            ->where('scope', $scope->value)
            ->where('target_id', $targetId)
            ->current()
            ->first()
            ?->toEntity();
    }

    public function currentForPeriod(int $schoolId, int $periodId): array
    {
        return PlanAssignmentModel::query()
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $periodId)
            ->current()
            ->orderBy('id')
            ->get()
            ->map(fn (PlanAssignmentModel $model): PlanAssignment => $model->toEntity())
            ->all();
    }

    public function currentBySlotForPeriods(int $schoolId, array $periodIds): array
    {
        if ($periodIds === []) {
            return [];
        }

        return PlanAssignmentModel::query()
            ->where('school_id', $schoolId)
            ->whereIn('academic_period_id', $periodIds)
            ->current()
            ->get()
            ->map(fn (PlanAssignmentModel $model): PlanAssignment => $model->toEntity())
            ->keyBy(fn (PlanAssignment $assignment): string => $assignment->slotKey())
            ->all();
    }

    public function replacedForPlanInPeriods(int $planId, int $schoolId, array $periodIds): array
    {
        if ($periodIds === []) {
            return [];
        }

        return PlanAssignmentModel::query()
            ->where('school_id', $schoolId)
            ->where('study_plan_id', $planId)
            ->whereIn('academic_period_id', $periodIds)
            ->whereNotNull('replaced_at')
            ->orderBy('id')
            ->get()
            ->map(fn (PlanAssignmentModel $model): PlanAssignment => $model->toEntity())
            ->all();
    }

    public function currentForPlan(int $planId, int $schoolId): array
    {
        return PlanAssignmentModel::query()
            ->where('school_id', $schoolId)
            ->where('study_plan_id', $planId)
            ->current()
            ->orderByDesc('academic_period_id')
            ->orderBy('id')
            ->get()
            ->map(fn (PlanAssignmentModel $model): PlanAssignment => $model->toEntity())
            ->all();
    }

    public function countForPlan(int $planId, int $schoolId): int
    {
        return PlanAssignmentModel::query()->where('school_id', $schoolId)->where('study_plan_id', $planId)->count();
    }

    public function planCoversGradeLevelInPeriods(int $planId, int $gradeLevelId, int $schoolId, array $periodIds): bool
    {
        if ($periodIds === []) {
            return false;
        }

        return PlanAssignmentModel::query()
            ->where('school_id', $schoolId)
            ->where('study_plan_id', $planId)
            ->whereIn('academic_period_id', $periodIds)
            ->current()
            // The offer scope stores its offer's grade level too.
            ->where(fn ($query) => $query
                ->where('scope', AssignmentScope::School->value)
                ->orWhere('grade_level_id', $gradeLevelId))
            ->exists();
    }

    public function delete(int $id, int $schoolId): void
    {
        PlanAssignmentModel::query()->where('school_id', $schoolId)->whereKey($id)->delete();
    }

    public function markReplaced(int $id, int $schoolId): void
    {
        PlanAssignmentModel::query()->where('school_id', $schoolId)->whereKey($id)->update(['replaced_at' => now()]);
    }

    public function markCurrent(int $id, int $schoolId): void
    {
        PlanAssignmentModel::query()->where('school_id', $schoolId)->whereKey($id)->update(['replaced_at' => null]);
    }

    public function excludedSubjectIds(int $assignmentId, int $schoolId): array
    {
        return $this->excludedSubjectIdsFor([$assignmentId], $schoolId)[$assignmentId] ?? [];
    }

    public function excludedSubjectIdsFor(array $assignmentIds, int $schoolId): array
    {
        if ($assignmentIds === []) {
            return [];
        }

        $byAssignment = [];

        SubjectExclusionModel::query()
            ->where('school_id', $schoolId)
            ->whereIn('study_plan_assignment_id', $assignmentIds)
            ->get(['study_plan_assignment_id', 'study_plan_subject_id'])
            ->each(function (SubjectExclusionModel $row) use (&$byAssignment): void {
                $byAssignment[$row->study_plan_assignment_id][] = $row->study_plan_subject_id;
            });

        return $byAssignment;
    }

    public function setExclusion(int $assignmentId, int $subjectId, bool $excluded, int $schoolId): void
    {
        $attributes = ['school_id' => $schoolId, 'study_plan_assignment_id' => $assignmentId, 'study_plan_subject_id' => $subjectId];

        if ($excluded) {
            SubjectExclusionModel::query()->firstOrCreate($attributes);

            return;
        }

        SubjectExclusionModel::query()->where($attributes)->delete();
    }

    public function copyExclusions(int $fromAssignmentId, int $toAssignmentId, int $schoolId): void
    {
        foreach ($this->excludedSubjectIds($fromAssignmentId, $schoolId) as $subjectId) {
            $this->setExclusion($toAssignmentId, $subjectId, true, $schoolId);
        }
    }
}
