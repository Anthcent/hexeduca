<?php

namespace Modules\Grades\Infrastructure\Persistence;

use Modules\Grades\Domain\Entities\Grade as GradeEntity;
use Modules\Grades\Domain\Repositories\GradeRepositoryInterface;
use Modules\Grades\Infrastructure\Models\Grade as GradeModel;

final class EloquentGradeRepository implements GradeRepositoryInterface
{
    public function findById(int $id): ?GradeEntity
    {
        $model = GradeModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function save(GradeEntity $grade): GradeEntity
    {
        $model = $grade->id() ? GradeModel::findOrFail($grade->id()) : new GradeModel;

        $model->school_id = $grade->schoolId();
        $model->periodo_academico_id = $grade->academicPeriodId();
        $model->academic_offer_id = $grade->academicOfferId();
        $model->student_id = $grade->studentId();
        $model->teacher_id = $grade->teacherId();
        $model->value = $grade->value();
        $model->recorded_at = $model->recorded_at ?? now();
        $model->save();

        return $this->toEntity($model);
    }

    private function toEntity(GradeModel $model): GradeEntity
    {
        return new GradeEntity(
            id: $model->id,
            schoolId: $model->school_id,
            academicPeriodId: $model->periodo_academico_id,
            academicOfferId: $model->academic_offer_id,
            studentId: $model->student_id,
            teacherId: $model->teacher_id,
            value: (float) $model->value,
        );
    }
}
