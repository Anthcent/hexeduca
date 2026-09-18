<?php

namespace Modules\Academic\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\Academic\Domain\Entities\Matricula as MatriculaEntity;
use Modules\Academic\Domain\Repositories\MatriculaRepositoryInterface;
use Modules\Academic\Infrastructure\Models\Matricula as MatriculaModel;

final class EloquentMatriculaRepository implements MatriculaRepositoryInterface
{
    public function findById(int $id): ?MatriculaEntity
    {
        $model = MatriculaModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function countByOfertaAcademica(int $ofertaAcademicaId): int
    {
        // Only ACTIVE matriculas occupy a capacity seat — a withdrawn/inactive
        // matricula must free up the seat for another student.
        return MatriculaModel::where('oferta_academica_id', $ofertaAcademicaId)
            ->where('status', 'active')
            ->count();
    }

    public function existsForOfertaAcademicaAndStudent(int $ofertaAcademicaId, int $studentId): bool
    {
        return MatriculaModel::where('oferta_academica_id', $ofertaAcademicaId)
            ->where('student_id', $studentId)
            ->exists();
    }

    public function save(MatriculaEntity $matricula): MatriculaEntity
    {
        $model = $matricula->id()
            ? MatriculaModel::findOrFail($matricula->id())
            : new MatriculaModel;

        $model->school_id = $matricula->schoolId();
        $model->periodo_academico_id = $matricula->periodoAcademicoId();
        $model->oferta_academica_id = $matricula->ofertaAcademicaId();
        $model->student_id = $matricula->studentId();
        $model->status = $matricula->status();
        $model->enrolled_at = $matricula->enrolledAt();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(MatriculaModel $model): MatriculaEntity
    {
        return new MatriculaEntity(
            id: $model->id,
            schoolId: $model->school_id,
            periodoAcademicoId: $model->periodo_academico_id,
            ofertaAcademicaId: $model->oferta_academica_id,
            studentId: $model->student_id,
            status: $model->status,
            enrolledAt: new DateTimeImmutable($model->enrolled_at->toDateTimeString()),
        );
    }
}
