<?php

namespace Modules\Subjects\Infrastructure\Persistence;

use Modules\Subjects\Domain\Entities\StudyPlan;
use Modules\Subjects\Domain\Repositories\StudyPlanRepositoryInterface;
use Modules\Subjects\Infrastructure\Models\StudyPlanModel;

final class EloquentStudyPlanRepository implements StudyPlanRepositoryInterface
{
    public function save(StudyPlan $plan): StudyPlan
    {
        $model = $plan->id()
            ? StudyPlanModel::query()->where('school_id', $plan->schoolId())->findOrFail($plan->id())
            : new StudyPlanModel;

        $model->fill([
            'school_id' => $plan->schoolId(),
            'code' => $plan->code(),
            'name' => $plan->name(),
            'observation' => $plan->observation(),
            'status' => $plan->status()->value,
        ])->save();

        return $model->toEntity();
    }

    public function findInSchool(int $id, int $schoolId): ?StudyPlan
    {
        return StudyPlanModel::query()->where('school_id', $schoolId)->find($id)?->toEntity();
    }

    public function delete(int $id, int $schoolId): void
    {
        StudyPlanModel::query()->where('school_id', $schoolId)->whereKey($id)->delete();
    }

    public function allInSchool(int $schoolId): array
    {
        return StudyPlanModel::query()
            ->where('school_id', $schoolId)
            ->get()
            ->mapWithKeys(fn (StudyPlanModel $model): array => [$model->id => $model->toEntity()])
            ->all();
    }

    public function codeTaken(int $schoolId, string $code, ?int $exceptPlanId = null): bool
    {
        return StudyPlanModel::query()
            ->where('school_id', $schoolId)
            ->whereRaw('LOWER(code) = ?', [mb_strtolower(trim($code))])
            ->when($exceptPlanId !== null, fn ($query) => $query->whereKeyNot($exceptPlanId))
            ->exists();
    }
}
