<?php

namespace Modules\AcademicMoments\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\AcademicMoments\Domain\Entities\AcademicMoment as AcademicMomentEntity;
use Modules\AcademicMoments\Domain\Repositories\AcademicMomentRepositoryInterface;
use Modules\AcademicMoments\Domain\ValueObjects\DateRange;
use Modules\AcademicMoments\Infrastructure\Models\AcademicMoment as AcademicMomentModel;

final class EloquentAcademicMomentRepository implements AcademicMomentRepositoryInterface
{
    public function findById(int $id): ?AcademicMomentEntity
    {
        $model = AcademicMomentModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(AcademicMomentEntity $academicMoment): AcademicMomentEntity
    {
        $model = $academicMoment->id()
            ? AcademicMomentModel::findOrFail($academicMoment->id())
            : new AcademicMomentModel;

        $model->periodo_academico_id = $academicMoment->academicPeriodId();
        $model->name = $academicMoment->name();
        $model->order = $academicMoment->order();
        $model->starts_on = $academicMoment->dateRange()->startsOn();
        $model->ends_on = $academicMoment->dateRange()->endsOn();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(AcademicMomentModel $model): AcademicMomentEntity
    {
        return new AcademicMomentEntity(
            id: $model->id,
            academicPeriodId: $model->periodo_academico_id,
            name: $model->name,
            order: $model->order,
            dateRange: new DateRange(
                new DateTimeImmutable($model->starts_on->toDateString()),
                new DateTimeImmutable($model->ends_on->toDateString()),
            ),
        );
    }
}
