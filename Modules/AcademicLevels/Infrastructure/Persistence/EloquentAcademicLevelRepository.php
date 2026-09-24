<?php

namespace Modules\AcademicLevels\Infrastructure\Persistence;

use Modules\AcademicLevels\Domain\Entities\AcademicLevel as AcademicLevelEntity;
use Modules\AcademicLevels\Domain\Repositories\AcademicLevelRepositoryInterface;
use Modules\AcademicLevels\Infrastructure\Models\AcademicLevel as AcademicLevelModel;

final class EloquentAcademicLevelRepository implements AcademicLevelRepositoryInterface
{
    public function findById(int $id): ?AcademicLevelEntity
    {
        $model = AcademicLevelModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(AcademicLevelEntity $academicLevel): AcademicLevelEntity
    {
        $model = $academicLevel->id()
            ? AcademicLevelModel::findOrFail($academicLevel->id())
            : new AcademicLevelModel;

        $model->school_id = $academicLevel->schoolId();
        $model->name = $academicLevel->name();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(AcademicLevelModel $model): AcademicLevelEntity
    {
        return new AcademicLevelEntity(
            id: $model->id,
            schoolId: $model->school_id,
            name: $model->name,
        );
    }
}
