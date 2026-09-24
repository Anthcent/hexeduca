<?php

namespace Modules\Sections\Infrastructure\Persistence;

use Modules\Sections\Domain\Entities\Section as SectionEntity;
use Modules\Sections\Domain\Repositories\SectionRepositoryInterface;
use Modules\Sections\Infrastructure\Models\Section as SectionModel;

final class EloquentSectionRepository implements SectionRepositoryInterface
{
    public function findById(int $id): ?SectionEntity
    {
        $model = SectionModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(SectionEntity $section): SectionEntity
    {
        $model = $section->id()
            ? SectionModel::findOrFail($section->id())
            : new SectionModel;

        $model->school_id = $section->schoolId();
        $model->name = $section->name();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(SectionModel $model): SectionEntity
    {
        return new SectionEntity(
            id: $model->id,
            schoolId: $model->school_id,
            name: $model->name,
        );
    }
}
