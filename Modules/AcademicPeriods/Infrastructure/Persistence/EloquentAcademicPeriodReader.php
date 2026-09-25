<?php

namespace Modules\AcademicPeriods\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod as AcademicPeriodModel;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;

final class EloquentAcademicPeriodReader implements AcademicPeriodReader
{
    public function findForSchool(int $id, int $schoolId): ?AcademicPeriodDTO
    {
        $model = $this->forSchool($schoolId)->find($id);

        return $model ? $this->toDTO($model) : null;
    }

    public function activeForSchool(int $schoolId): ?AcademicPeriodDTO
    {
        $model = $this->forSchool($schoolId)->where('is_active', true)->first();

        return $model ? $this->toDTO($model) : null;
    }

    /**
     * @return array<int, AcademicPeriodDTO>
     */
    public function allForSchool(int $schoolId): array
    {
        return $this->forSchool($schoolId)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AcademicPeriodModel $model): AcademicPeriodDTO => $this->toDTO($model))
            ->all();
    }

    /**
     * Explicit school filter instead of the ambient tenant scope, so the
     * reader answers for `$schoolId` in any context (request, console, queue).
     *
     * @return Builder<AcademicPeriodModel>
     */
    private function forSchool(int $schoolId): Builder
    {
        return AcademicPeriodModel::withoutTenantScope()->where('school_id', $schoolId);
    }

    private function toDTO(AcademicPeriodModel $model): AcademicPeriodDTO
    {
        return new AcademicPeriodDTO(
            id: $model->id,
            schoolId: $model->school_id,
            name: $model->name,
            isActive: (bool) $model->is_active,
            startsOn: $model->starts_on->toDateString(),
            endsOn: $model->ends_on->toDateString(),
        );
    }
}
