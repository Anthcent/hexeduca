<?php

namespace Modules\Enrollments\Infrastructure\Persistence;

use DateTimeImmutable;
use Modules\Enrollments\Infrastructure\Models\Enrollment as EnrollmentModel;
use Modules\Enrollments\Public\Contracts\EnrollmentProjectionSource;
use Modules\Enrollments\Public\DTOs\EnrollmentProjectionRow;

final class EloquentEnrollmentProjectionSource implements EnrollmentProjectionSource
{
    public function find(int $id): ?EnrollmentProjectionRow
    {
        $model = EnrollmentModel::find($id);

        return $model ? $this->toRow($model) : null;
    }

    /**
     * @return iterable<EnrollmentProjectionRow>
     */
    public function allForRebuild(): iterable
    {
        foreach (EnrollmentModel::withoutTenantScope()->withoutActivePeriodScope()->cursor() as $model) {
            yield $this->toRow($model);
        }
    }

    private function toRow(EnrollmentModel $model): EnrollmentProjectionRow
    {
        return new EnrollmentProjectionRow(
            id: $model->id,
            schoolId: $model->school_id,
            academicPeriodId: $model->periodo_academico_id,
            academicOfferId: $model->oferta_academica_id,
            studentId: $model->student_id,
            status: $model->status,
            enrolledAt: new DateTimeImmutable($model->enrolled_at->toDateTimeString()),
            version: $model->source_version,
        );
    }
}
