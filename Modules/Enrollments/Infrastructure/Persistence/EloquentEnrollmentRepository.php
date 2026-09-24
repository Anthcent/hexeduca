<?php

namespace Modules\Enrollments\Infrastructure\Persistence;

use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Enrollments\Domain\Entities\Enrollment as EnrollmentEntity;
use Modules\Enrollments\Domain\Repositories\EnrollmentRepositoryInterface;
use Modules\Enrollments\Infrastructure\Models\Enrollment as EnrollmentModel;

final class EloquentEnrollmentRepository implements EnrollmentRepositoryInterface
{
    public function findById(int $id): ?EnrollmentEntity
    {
        $model = EnrollmentModel::find($id);

        return $model ? $this->toEntity($model) : null;
    }

    public function countByAcademicOffer(int $academicOfferId): int
    {
        // Only ACTIVE enrollments occupy a capacity seat — a
        // withdrawn/inactive enrollment must free up the seat for another
        // student.
        return EnrollmentModel::where('oferta_academica_id', $academicOfferId)
            ->where('status', 'active')
            ->count();
    }

    public function existsForAcademicOfferAndStudent(int $academicOfferId, int $studentId): bool
    {
        return EnrollmentModel::where('oferta_academica_id', $academicOfferId)
            ->where('student_id', $studentId)
            ->exists();
    }

    public function save(EnrollmentEntity $enrollment): EnrollmentEntity
    {
        return DB::transaction(function () use ($enrollment): EnrollmentEntity {
            $model = $enrollment->id()
                ? EnrollmentModel::query()->lockForUpdate()->findOrFail($enrollment->id())
                : new EnrollmentModel;

            $model->school_id = $enrollment->schoolId();
            $model->periodo_academico_id = $enrollment->academicPeriodId();
            $model->oferta_academica_id = $enrollment->academicOfferId();
            $model->student_id = $enrollment->studentId();
            $model->status = $enrollment->status();
            $model->enrolled_at = $enrollment->enrolledAt();
            $model->source_version = $model->exists ? $model->source_version + 1 : 1;
            $model->save();

            return $this->toEntity($model);
        });
    }

    private function toEntity(EnrollmentModel $model): EnrollmentEntity
    {
        return new EnrollmentEntity(
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
