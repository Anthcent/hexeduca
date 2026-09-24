<?php

namespace Modules\AcademicPeriods\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\AcademicPeriods\Domain\Entities\AcademicPeriod as AcademicPeriodEntity;
use Modules\AcademicPeriods\Domain\Repositories\AcademicPeriodRepositoryInterface;
use Modules\AcademicPeriods\Domain\ValueObjects\DateRange;
use Modules\AcademicPeriods\Infrastructure\Models\AcademicPeriod as AcademicPeriodModel;

final class EloquentAcademicPeriodRepository implements AcademicPeriodRepositoryInterface
{
    public function findById(int $id): ?AcademicPeriodEntity
    {
        $model = AcademicPeriodModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(AcademicPeriodEntity $academicPeriod): AcademicPeriodEntity
    {
        $model = $academicPeriod->id()
            ? AcademicPeriodModel::findOrFail($academicPeriod->id())
            : new AcademicPeriodModel;

        $model->school_id = $academicPeriod->schoolId();
        $model->name = $academicPeriod->name();
        $model->starts_on = $academicPeriod->dateRange()->startsOn();
        $model->ends_on = $academicPeriod->dateRange()->endsOn();
        $model->is_active = $academicPeriod->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(AcademicPeriodModel $model): AcademicPeriodEntity
    {
        return new AcademicPeriodEntity(
            id: $model->id,
            schoolId: $model->school_id,
            name: $model->name,
            dateRange: new DateRange(
                new DateTimeImmutable($model->starts_on->toDateString()),
                new DateTimeImmutable($model->ends_on->toDateString()),
            ),
            isActive: $model->is_active,
        );
    }
}
