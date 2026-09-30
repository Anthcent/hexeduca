<?php

namespace Modules\Grades\Infrastructure\Persistence;

use Modules\Grades\Domain\Repositories\ConductBookRepositoryInterface;
use Modules\Grades\Infrastructure\Models\GradeConductChangeModel;
use Modules\Grades\Infrastructure\Models\GradeConductModel;

final class EloquentConductBookRepository implements ConductBookRepositoryInterface
{
    public function setLetter(int $schoolId, int $offerId, int $momentId, int $studentId, ?string $letter, int $actorId): ?string
    {
        $key = ['school_id' => $schoolId, 'academic_offer_id' => $offerId, 'academic_moment_id' => $momentId, 'student_id' => $studentId];
        $row = GradeConductModel::query()->where($key)->lockForUpdate()->first();
        $old = $row?->letter;

        if ($old === $letter) {
            return $old;
        }

        if ($letter === null) {
            $row->delete();
        } elseif ($row) {
            $row->update(['letter' => $letter, 'recorded_by' => $actorId]);
        } else {
            GradeConductModel::query()->create($key + ['letter' => $letter, 'recorded_by' => $actorId]);
        }

        GradeConductChangeModel::query()->create($key + ['old_letter' => $old, 'new_letter' => $letter, 'changed_by' => $actorId]);

        return $old;
    }

    public function letters(int $schoolId, int $offerId, int $momentId): array
    {
        return GradeConductModel::query()
            ->where('school_id', $schoolId)
            ->where('academic_offer_id', $offerId)
            ->where('academic_moment_id', $momentId)
            ->pluck('letter', 'student_id')
            ->all();
    }

    public function isEdited(int $schoolId, int $offerId, int $momentId, int $studentId): bool
    {
        return GradeConductChangeModel::query()
            ->where('school_id', $schoolId)
            ->where('academic_offer_id', $offerId)
            ->where('academic_moment_id', $momentId)
            ->where('student_id', $studentId)
            ->count() > 1;
    }

    public function editedStudents(int $schoolId, int $offerId, int $momentId): array
    {
        return GradeConductChangeModel::query()
            ->where('school_id', $schoolId)
            ->where('academic_offer_id', $offerId)
            ->where('academic_moment_id', $momentId)
            ->groupBy('student_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('student_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }
}
