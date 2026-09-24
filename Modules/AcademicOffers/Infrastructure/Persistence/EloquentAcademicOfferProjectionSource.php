<?php

namespace Modules\AcademicOffers\Infrastructure\Persistence;

use Modules\AcademicOffers\Infrastructure\Models\AcademicOffer as AcademicOfferModel;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferProjectionSource;
use Modules\AcademicOffers\Public\DTOs\AcademicOfferProjectionRow;

final class EloquentAcademicOfferProjectionSource implements AcademicOfferProjectionSource
{
    public function find(int $id): ?AcademicOfferProjectionRow
    {
        $model = AcademicOfferModel::find($id);

        return $model ? $this->toRow($model) : null;
    }

    public function findForEnrollment(int $id): ?AcademicOfferProjectionRow
    {
        $model = AcademicOfferModel::query()->lockForUpdate()->find($id);

        return $model ? $this->toRow($model) : null;
    }

    /**
     * @return iterable<AcademicOfferProjectionRow>
     */
    public function allForRebuild(): iterable
    {
        foreach (AcademicOfferModel::withoutTenantScope()->withoutActivePeriodScope()->cursor() as $model) {
            yield $this->toRow($model);
        }
    }

    private function toRow(AcademicOfferModel $model): AcademicOfferProjectionRow
    {
        return new AcademicOfferProjectionRow(
            id: $model->id,
            schoolId: $model->school_id,
            academicPeriodId: $model->periodo_academico_id,
            gradeLevelId: $model->grado_id,
            sectionId: $model->seccion_id,
            teacherId: $model->teacher_id,
            capacity: $model->capacity,
        );
    }
}
