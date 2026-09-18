<?php

namespace Modules\Academic\Infrastructure\Persistence;

use Modules\Academic\Domain\Entities\NivelAcademico as NivelAcademicoEntity;
use Modules\Academic\Domain\Repositories\NivelAcademicoRepositoryInterface;
use Modules\Academic\Infrastructure\Models\NivelAcademico as NivelAcademicoModel;

final class EloquentNivelAcademicoRepository implements NivelAcademicoRepositoryInterface
{
    public function findById(int $id): ?NivelAcademicoEntity
    {
        $model = NivelAcademicoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(NivelAcademicoEntity $nivelAcademico): NivelAcademicoEntity
    {
        $model = $nivelAcademico->id()
            ? NivelAcademicoModel::findOrFail($nivelAcademico->id())
            : new NivelAcademicoModel;

        $model->school_id = $nivelAcademico->schoolId();
        $model->name = $nivelAcademico->name();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(NivelAcademicoModel $model): NivelAcademicoEntity
    {
        return new NivelAcademicoEntity(
            id: $model->id,
            schoolId: $model->school_id,
            name: $model->name,
        );
    }
}
