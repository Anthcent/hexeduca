<?php

namespace Modules\Academic\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\Academic\Domain\Entities\PeriodoAcademico as PeriodoAcademicoEntity;
use Modules\Academic\Domain\Repositories\PeriodoAcademicoRepositoryInterface;
use Modules\Academic\Domain\ValueObjects\DateRange;
use Modules\Academic\Infrastructure\Models\PeriodoAcademico as PeriodoAcademicoModel;

final class EloquentPeriodoAcademicoRepository implements PeriodoAcademicoRepositoryInterface
{
    public function findById(int $id): ?PeriodoAcademicoEntity
    {
        $model = PeriodoAcademicoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(PeriodoAcademicoEntity $periodoAcademico): PeriodoAcademicoEntity
    {
        $model = $periodoAcademico->id()
            ? PeriodoAcademicoModel::findOrFail($periodoAcademico->id())
            : new PeriodoAcademicoModel;

        $model->school_id = $periodoAcademico->schoolId();
        $model->name = $periodoAcademico->name();
        $model->starts_on = $periodoAcademico->dateRange()->startsOn();
        $model->ends_on = $periodoAcademico->dateRange()->endsOn();
        $model->is_active = $periodoAcademico->isActive();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(PeriodoAcademicoModel $model): PeriodoAcademicoEntity
    {
        return new PeriodoAcademicoEntity(
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
