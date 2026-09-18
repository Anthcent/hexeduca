<?php

namespace Modules\Academic\Infrastructure\Persistence;

use Modules\Academic\Domain\Entities\Seccion as SeccionEntity;
use Modules\Academic\Domain\Repositories\SeccionRepositoryInterface;
use Modules\Academic\Infrastructure\Models\Seccion as SeccionModel;

final class EloquentSeccionRepository implements SeccionRepositoryInterface
{
    public function findById(int $id): ?SeccionEntity
    {
        $model = SeccionModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(SeccionEntity $seccion): SeccionEntity
    {
        $model = $seccion->id()
            ? SeccionModel::findOrFail($seccion->id())
            : new SeccionModel;

        $model->school_id = $seccion->schoolId();
        $model->name = $seccion->name();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(SeccionModel $model): SeccionEntity
    {
        return new SeccionEntity(
            id: $model->id,
            schoolId: $model->school_id,
            name: $model->name,
        );
    }
}
