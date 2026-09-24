<?php

namespace Modules\AcademicOffers\Infrastructure\Persistence;

use Modules\AcademicOffers\Domain\Entities\AcademicOffer as AcademicOfferEntity;
use Modules\AcademicOffers\Domain\Repositories\AcademicOfferRepositoryInterface;
use Modules\AcademicOffers\Domain\ValueObjects\Capacity;
use Modules\AcademicOffers\Infrastructure\Models\AcademicOffer as AcademicOfferModel;

final class EloquentAcademicOfferRepository implements AcademicOfferRepositoryInterface
{
    public function findById(int $id): ?AcademicOfferEntity
    {
        $model = AcademicOfferModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByPeriodGradeLevelSection(int $academicPeriodId, int $gradeLevelId, int $sectionId): ?AcademicOfferEntity
    {
        $model = AcademicOfferModel::where('periodo_academico_id', $academicPeriodId)
            ->where('grado_id', $gradeLevelId)
            ->where('seccion_id', $sectionId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(AcademicOfferEntity $academicOffer): AcademicOfferEntity
    {
        $model = $academicOffer->id()
            ? AcademicOfferModel::findOrFail($academicOffer->id())
            : new AcademicOfferModel;

        $model->school_id = $academicOffer->schoolId();
        $model->periodo_academico_id = $academicOffer->academicPeriodId();
        $model->grado_id = $academicOffer->gradeLevelId();
        $model->seccion_id = $academicOffer->sectionId();
        $model->teacher_id = $academicOffer->teacherId();
        $model->capacity = $academicOffer->capacity()->limit();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(AcademicOfferModel $model): AcademicOfferEntity
    {
        return new AcademicOfferEntity(
            id: $model->id,
            schoolId: $model->school_id,
            academicPeriodId: $model->periodo_academico_id,
            gradeLevelId: $model->grado_id,
            sectionId: $model->seccion_id,
            teacherId: $model->teacher_id,
            capacity: new Capacity($model->capacity),
        );
    }
}
