<?php

namespace Modules\Academic\Infrastructure\Persistence;

use Modules\Academic\Domain\Entities\Grado as GradoEntity;
use Modules\Academic\Domain\Repositories\GradoRepositoryInterface;
use Modules\Academic\Infrastructure\Models\Grado as GradoModel;

final class EloquentGradoRepository implements GradoRepositoryInterface
{
    public function findById(int $id): ?GradoEntity
    {
        $model = GradoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(GradoEntity $grado): GradoEntity
    {
        $model = $grado->id()
            ? GradoModel::findOrFail($grado->id())
            : new GradoModel;

        $model->school_id = $grado->schoolId();
        $model->nivel_academico_id = $grado->nivelAcademicoId();
        $model->name = $grado->name();
        $model->order = $grado->order();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(GradoModel $model): GradoEntity
    {
        return new GradoEntity(
            id: $model->id,
            schoolId: $model->school_id,
            nivelAcademicoId: $model->nivel_academico_id,
            name: $model->name,
            order: $model->order,
        );
    }
}
