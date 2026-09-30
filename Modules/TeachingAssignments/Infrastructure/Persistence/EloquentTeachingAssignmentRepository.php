<?php

namespace Modules\TeachingAssignments\Infrastructure\Persistence;

use Modules\TeachingAssignments\Domain\Entities\TeachingAssignment;
use Modules\TeachingAssignments\Domain\Repositories\TeachingAssignmentRepositoryInterface;
use Modules\TeachingAssignments\Domain\ValueObjects\TeachingRole;
use Modules\TeachingAssignments\Infrastructure\Models\OfferCoordinatorModel;
use Modules\TeachingAssignments\Infrastructure\Models\TeachingAssignmentModel;

final class EloquentTeachingAssignmentRepository implements TeachingAssignmentRepositoryInterface
{
    public function save(TeachingAssignment $assignment): TeachingAssignment
    {
        $attributes = [
            'school_id' => $assignment->schoolId(),
            'academic_period_id' => $assignment->periodId(),
            'academic_offer_id' => $assignment->offerId(),
            'study_plan_subject_id' => $assignment->subjectId(),
            'teacher_id' => $assignment->teacherId(),
            'role' => $assignment->role()->value,
            'started_on' => $assignment->startedOn(),
            'ended_on' => $assignment->endedOn(),
        ];

        if ($assignment->id() === null) {
            return TeachingAssignmentModel::query()->create($attributes)->toEntity();
        }

        $model = TeachingAssignmentModel::query()
            ->where('school_id', $assignment->schoolId())
            ->findOrFail($assignment->id());
        $model->update($attributes);

        return $model->toEntity();
    }

    public function findActiveInSchool(int $id, int $schoolId): ?TeachingAssignment
    {
        return TeachingAssignmentModel::query()
            ->where('school_id', $schoolId)
            ->whereNull('ended_on')
            ->find($id)
            ?->toEntity();
    }

    public function activeInSlot(int $schoolId, int $offerId, int $subjectId, TeachingRole $role): ?TeachingAssignment
    {
        return TeachingAssignmentModel::query()
            ->where('school_id', $schoolId)
            ->where('academic_offer_id', $offerId)
            ->where('study_plan_subject_id', $subjectId)
            ->where('role', $role->value)
            ->whereNull('ended_on')
            ->first()
            ?->toEntity();
    }

    public function activeForPeriod(int $schoolId, int $periodId): array
    {
        return TeachingAssignmentModel::query()
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $periodId)
            ->whereNull('ended_on')
            ->orderBy('id')
            ->get()
            ->map(fn (TeachingAssignmentModel $model): TeachingAssignment => $model->toEntity())
            ->all();
    }

    public function coordinatorsForPeriod(int $schoolId, int $periodId): array
    {
        return OfferCoordinatorModel::query()
            ->where('school_id', $schoolId)
            ->where('academic_period_id', $periodId)
            ->pluck('teacher_id', 'academic_offer_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function setCoordinator(int $schoolId, int $periodId, int $offerId, ?int $teacherId): void
    {
        if ($teacherId === null) {
            OfferCoordinatorModel::query()->where('school_id', $schoolId)->where('academic_offer_id', $offerId)->delete();

            return;
        }

        OfferCoordinatorModel::query()->updateOrCreate(
            ['school_id' => $schoolId, 'academic_offer_id' => $offerId],
            ['academic_period_id' => $periodId, 'teacher_id' => $teacherId],
        );
    }
}
