<?php

namespace Modules\AcademicMoments\Infrastructure\Persistence;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AcademicMoments\Infrastructure\Models\AcademicMoment;
use Modules\AcademicMoments\Public\Contracts\AcademicMomentReader;
use Modules\AcademicMoments\Public\DTOs\AcademicMomentDTO;

/**
 * Moments carry no school_id: they belong to the school through their
 * period, so every read joins the school's periods explicitly instead of
 * relying on the request's active period.
 */
final class EloquentAcademicMomentReader implements AcademicMomentReader
{
    public function forPeriod(int $schoolId, int $periodId): array
    {
        return $this->inSchool($schoolId)
            ->where('periodo_academico_id', $periodId)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->map(fn (AcademicMoment $moment): AcademicMomentDTO => $this->dto($moment))
            ->all();
    }

    public function findForSchool(int $id, int $schoolId): ?AcademicMomentDTO
    {
        $moment = $this->inSchool($schoolId)->find($id);

        return $moment ? $this->dto($moment) : null;
    }

    /**
     * @return Builder<AcademicMoment>
     */
    private function inSchool(int $schoolId): Builder
    {
        return AcademicMoment::query()
            ->withoutActivePeriodScope()
            ->whereIn('periodo_academico_id', DB::table('periodos_academicos')->select('id')->where('school_id', $schoolId));
    }

    private function dto(AcademicMoment $moment): AcademicMomentDTO
    {
        return new AcademicMomentDTO(
            id: $moment->id,
            periodId: (int) $moment->periodo_academico_id,
            name: $moment->name,
            order: (int) $moment->order,
            startsOn: $moment->starts_on->toDateString(),
            endsOn: $moment->ends_on->toDateString(),
            gradingOpensOn: $moment->grading_opens_on?->toDateString(),
            gradingClosesOn: $moment->grading_closes_on?->toDateString(),
        );
    }
}
