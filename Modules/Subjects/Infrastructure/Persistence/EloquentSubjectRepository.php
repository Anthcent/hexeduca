<?php

namespace Modules\Subjects\Infrastructure\Persistence;

use Modules\Subjects\Domain\Entities\Subject;
use Modules\Subjects\Domain\Repositories\SubjectRepositoryInterface;
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

    public function hasExclusions(int $subjectId, int $schoolId): bool
    {
        return SubjectExclusionModel::query()
            ->where('school_id', $schoolId)
            ->where('study_plan_subject_id', $subjectId)
            ->exists();
    }
}
