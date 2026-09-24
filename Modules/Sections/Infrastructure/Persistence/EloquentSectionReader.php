<?php

namespace Modules\Sections\Infrastructure\Persistence;

use Modules\Sections\Infrastructure\Models\Section as SectionModel;
use Modules\Sections\Public\Contracts\SectionReader;
use Modules\Sections\Public\DTOs\SectionDTO;

final class EloquentSectionReader implements SectionReader
{
    public function find(int $id): ?SectionDTO
    {
        $model = SectionModel::find($id);

        return $model ? $this->toDTO($model) : null;
    }

    public function findForSchool(int $id, int $schoolId): ?SectionDTO
    {
        $model = SectionModel::withoutTenantScope()
            ->where('school_id', $schoolId)
            ->find($id);

        return $model ? $this->toDTO($model) : null;
    }

    /**
     * @return array<int, SectionDTO>
     */
    public function allForSchool(int $schoolId): array
    {
        return SectionModel::where('school_id', $schoolId)
            ->orderBy('name')
            ->get()
            ->map(fn (SectionModel $model): SectionDTO => $this->toDTO($model))
            ->all();
    }

    private function toDTO(SectionModel $model): SectionDTO
    {
        return new SectionDTO(
            id: $model->id,
            schoolId: $model->school_id,
            name: $model->name,
        );
    }
}
