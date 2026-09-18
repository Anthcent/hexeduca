<?php

namespace Modules\Academic\Infrastructure\Persistence;

use Modules\Academic\Domain\Entities\OfertaAcademica as OfertaAcademicaEntity;
use Modules\Academic\Domain\Repositories\OfertaAcademicaRepositoryInterface;
use Modules\Academic\Domain\ValueObjects\Capacity;
use Modules\Academic\Infrastructure\Models\OfertaAcademica as OfertaAcademicaModel;

final class EloquentOfertaAcademicaRepository implements OfertaAcademicaRepositoryInterface
{
    public function findById(int $id): ?OfertaAcademicaEntity
    {
        $model = OfertaAcademicaModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function findByPeriodoGradoSeccion(int $periodoAcademicoId, int $gradoId, int $seccionId): ?OfertaAcademicaEntity
    {
        $model = OfertaAcademicaModel::where('periodo_academico_id', $periodoAcademicoId)
            ->where('grado_id', $gradoId)
            ->where('seccion_id', $seccionId)
            ->first();

        return $model ? $this->toEntity($model) : null;
    }

    public function save(OfertaAcademicaEntity $ofertaAcademica): OfertaAcademicaEntity
    {
        $model = $ofertaAcademica->id()
            ? OfertaAcademicaModel::findOrFail($ofertaAcademica->id())
            : new OfertaAcademicaModel;

        $model->school_id = $ofertaAcademica->schoolId();
        $model->periodo_academico_id = $ofertaAcademica->periodoAcademicoId();
        $model->grado_id = $ofertaAcademica->gradoId();
        $model->seccion_id = $ofertaAcademica->seccionId();
        $model->teacher_id = $ofertaAcademica->teacherId();
        $model->capacity = $ofertaAcademica->capacity()->limit();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(OfertaAcademicaModel $model): OfertaAcademicaEntity
    {
        return new OfertaAcademicaEntity(
            id: $model->id,
            schoolId: $model->school_id,
            periodoAcademicoId: $model->periodo_academico_id,
            gradoId: $model->grado_id,
            seccionId: $model->seccion_id,
            teacherId: $model->teacher_id,
            capacity: new Capacity($model->capacity),
        );
    }
}
