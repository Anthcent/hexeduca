<?php

namespace Modules\Subjects\Infrastructure\Persistence;

use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;
use Modules\Subjects\Infrastructure\Models\PlanAssignmentModel;
use Modules\Subjects\Infrastructure\Models\SubjectExclusionModel;
use Modules\Subjects\Infrastructure\Models\SubjectModel;

final class EloquentSubjectRepository implements SubjectRepositoryInterface
{
    public function save(Subject $subject): Subject
    {
        $model = $subject->id()
            ? SubjectModel::query()->where('school_id', $subject->schoolId())->findOrFail($subject->id())
            : new SubjectModel;

        $model->fill([
            'school_id' => $subject->schoolId(),
            'study_plan_id' => $subject->planId(),
            'grade_level_id' => $subject->gradeLevelId(),
            'name' => $subject->name(),
            'code' => $subject->code(),
            'weekly_hours' => $subject->weeklyHours(),
            'status' => $subject->status()->value,
        ])->save();

        return $model->toEntity();
    }

    public function findInPlan(int $id, int $planId, int $schoolId): ?Subject
    {
        return SubjectModel::query()
            ->where('school_id', $schoolId)
            ->where('study_plan_id', $planId)
            ->find($id)
            ?->toEntity();
    }

    public function delete(int $id, int $schoolId): void
    {
        SubjectModel::query()->where('school_id', $schoolId)->whereKey($id)->delete();
    }

    public function countForPlan(int $planId, int $schoolId): int
    {
        return SubjectModel::query()->where('school_id', $schoolId)->where('study_plan_id', $planId)->count();
    }

    public function forPlan(int $planId, int $schoolId): array
    {
        return $this->forPlans([$planId], $schoolId)[$planId] ?? [];
    }

    public function forPlans(array $planIds, int $schoolId): array
    {
        if ($planIds === []) {
            return [];
        }

        $byPlan = [];

        SubjectModel::query()
            ->where('school_id', $schoolId)
            ->whereIn('study_plan_id', $planIds)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->each(function (SubjectModel $model) use (&$byPlan): void {
                $byPlan[$model->study_plan_id][] = $model->toEntity();
            });

        return $byPlan;
    }

    public function excludedInPeriods(array $subjectIds, int $schoolId, array $periodIds): array
    {
        if ($subjectIds === [] || $periodIds === []) {
            return [];
        }

        return SubjectExclusionModel::query()
            ->where('school_id', $schoolId)
            ->whereIn('study_plan_subject_id', $subjectIds)
            ->whereIn('study_plan_assignment_id', PlanAssignmentModel::query()
                ->select('id')
                ->where('school_id', $schoolId)
                ->whereNull('replaced_at')
                ->whereIn('academic_period_id', $periodIds))
            ->distinct()
            ->pluck('study_plan_subject_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function deleteExclusions(int $subjectId, int $schoolId): void
    {
        SubjectExclusionModel::query()->where('school_id', $schoolId)->where('study_plan_subject_id', $subjectId)->delete();
    }
}
