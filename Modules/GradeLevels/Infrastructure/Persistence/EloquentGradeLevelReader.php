<?php

namespace Modules\GradeLevels\Infrastructure\Persistence;

use Modules\GradeLevels\Infrastructure\Models\GradeLevel as GradeLevelModel;
use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\GradeLevels\Public\DTOs\GradeLevelDTO;

final class EloquentGradeLevelReader implements GradeLevelReader
{
    public function find(int $id): ?GradeLevelDTO
    {
        $model = GradeLevelModel::find($id);

        return $model ? $this->toDTO($model) : null;
    }

    public function findForSchool(int $id, int $schoolId): ?GradeLevelDTO
    {
        $model = GradeLevelModel::withoutTenantScope()
            ->where('school_id', $schoolId)
            ->find($id);

        return $model ? $this->toDTO($model) : null;
    }

    /**
     * @return array<int, GradeLevelDTO>
     */
    public function allForSchool(int $schoolId): array
    {
        return GradeLevelModel::where('school_id', $schoolId)
            ->orderBy('order')
            ->get()
            ->map(fn (GradeLevelModel $model): GradeLevelDTO => $this->toDTO($model))
            ->all();
    }

    private function toDTO(GradeLevelModel $model): GradeLevelDTO
    {
        return new GradeLevelDTO(
            id: $model->id,
            schoolId: $model->school_id,
            name: $model->name,
            order: $model->order,
        );
    }
}
