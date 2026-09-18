<?php

namespace Modules\Academic\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\Academic\Domain\Entities\MomentoAcademico as MomentoAcademicoEntity;
use Modules\Academic\Domain\Repositories\MomentoAcademicoRepositoryInterface;
use Modules\Academic\Domain\ValueObjects\DateRange;
use Modules\Academic\Infrastructure\Models\MomentoAcademico as MomentoAcademicoModel;

final class EloquentMomentoAcademicoRepository implements MomentoAcademicoRepositoryInterface
{
    public function findById(int $id): ?MomentoAcademicoEntity
    {
        $model = MomentoAcademicoModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(MomentoAcademicoEntity $momentoAcademico): MomentoAcademicoEntity
    {
        $model = $momentoAcademico->id()
            ? MomentoAcademicoModel::findOrFail($momentoAcademico->id())
            : new MomentoAcademicoModel;

        $model->periodo_academico_id = $momentoAcademico->periodoAcademicoId();
        $model->name = $momentoAcademico->name();
        $model->order = $momentoAcademico->order();
        $model->starts_on = $momentoAcademico->dateRange()->startsOn();
        $model->ends_on = $momentoAcademico->dateRange()->endsOn();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(MomentoAcademicoModel $model): MomentoAcademicoEntity
    {
        return new MomentoAcademicoEntity(
            id: $model->id,
            periodoAcademicoId: $model->periodo_academico_id,
            name: $model->name,
            order: $model->order,
            dateRange: new DateRange(
                new DateTimeImmutable($model->starts_on->toDateString()),
                new DateTimeImmutable($model->ends_on->toDateString()),
            ),
        );
    }
}
