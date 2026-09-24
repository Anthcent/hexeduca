<?php

namespace Modules\GradeLevels\Infrastructure\Persistence;

use Modules\GradeLevels\Domain\Entities\GradeLevel as GradeLevelEntity;
use Modules\GradeLevels\Domain\Repositories\GradeLevelRepositoryInterface;
use Modules\GradeLevels\Infrastructure\Models\GradeLevel as GradeLevelModel;

final class EloquentGradeLevelRepository implements GradeLevelRepositoryInterface
{
    public function findById(int $id): ?GradeLevelEntity
    {
        $model = GradeLevelModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(GradeLevelEntity $gradeLevel): GradeLevelEntity
    {
        $model = $gradeLevel->id()
            ? GradeLevelModel::findOrFail($gradeLevel->id())
            : new GradeLevelModel;

        $model->school_id = $gradeLevel->schoolId();
        $model->nivel_academico_id = $gradeLevel->academicLevelId();
        $model->name = $gradeLevel->name();
        $model->order = $gradeLevel->order();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(GradeLevelModel $model): GradeLevelEntity
    {
        return new GradeLevelEntity(
            id: $model->id,
            schoolId: $model->school_id,
            academicLevelId: $model->nivel_academico_id,
            name: $model->name,
            order: $model->order,
        );
    }
}
