<?php

namespace Modules\TeachingAssignments\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Modules\TeachingAssignments\Infrastructure\Models\TeachingAssignmentModel;
use Modules\TeachingAssignments\Public\Contracts\TeachingAssignmentReader;
use Modules\TeachingAssignments\Public\DTOs\TeachingAssignmentDTO;

final class EloquentTeachingAssignmentReader implements TeachingAssignmentReader
{
    public function forTeacher(int $schoolId, int $periodId, int $teacherId): array
    {
        return $this->dtos($this->active($schoolId)
            ->where('academic_period_id', $periodId)
            ->where('teacher_id', $teacherId));
    }

    public function forPeriod(int $schoolId, int $periodId): array
    {
        return $this->dtos($this->active($schoolId)->where('academic_period_id', $periodId));
    }

    public function forOfferSubject(int $schoolId, int $offerId, int $subjectId): array
    {
        return $this->dtos($this->active($schoolId)
            ->where('academic_offer_id', $offerId)
            ->where('study_plan_subject_id', $subjectId));
    }

    public function teaches(int $schoolId, int $teacherId, int $offerId, int $subjectId): bool
    {
        return $this->active($schoolId)
            ->where('teacher_id', $teacherId)
            ->where('academic_offer_id', $offerId)
            ->where('study_plan_subject_id', $subjectId)
            ->exists();
    }

    /**
     * @return Builder<TeachingAssignmentModel>
     */
    private function active(int $schoolId): Builder
    {
        return TeachingAssignmentModel::query()
            ->withoutGlobalScopes()
            ->where('school_id', $schoolId)
            ->whereNull('ended_on');
    }

    /**
     * @param  Builder<TeachingAssignmentModel>  $query
     * @return list<TeachingAssignmentDTO>
     */
    private function dtos(Builder $query): array
    {
        return $query->orderBy('id')->get()->map(fn (TeachingAssignmentModel $model): TeachingAssignmentDTO => new TeachingAssignmentDTO(
            id: $model->id,
            periodId: $model->academic_period_id,
            offerId: $model->academic_offer_id,
            subjectId: $model->study_plan_subject_id,
            teacherId: $model->teacher_id,
            role: $model->role,
            startedOn: $model->started_on->toDateString(),
            endedOn: $model->ended_on?->toDateString(),
        ))->all();
    }
}
