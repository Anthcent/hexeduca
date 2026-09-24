<?php

namespace Modules\AcademicLevels\Infrastructure\Persistence;

use Modules\AcademicLevels\Infrastructure\Models\AcademicLevel as AcademicLevelModel;
use Modules\AcademicLevels\Public\Contracts\AcademicLevelReader;
use Modules\AcademicLevels\Public\DTOs\AcademicLevelDTO;

final class EloquentAcademicLevelReader implements AcademicLevelReader
{
    public function find(int $id): ?AcademicLevelDTO
    {
        $model = AcademicLevelModel::find($id);

        return $model ? $this->toDTO($model) : null;
    }

    /**
     * @return array<int, AcademicLevelDTO>
     */
    public function allForSchool(int $schoolId): array
    {
        return AcademicLevelModel::where('school_id', $schoolId)
            ->get()
            ->map(fn (AcademicLevelModel $model): AcademicLevelDTO => $this->toDTO($model))
            ->all();
    }

    private function toDTO(AcademicLevelModel $model): AcademicLevelDTO
    {
        return new AcademicLevelDTO(
            id: $model->id,
            schoolId: $model->school_id,
            name: $model->name,
        );
    }
}
