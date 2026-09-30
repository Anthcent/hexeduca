<?php

namespace Modules\AcademicOffers\Infrastructure\Persistence;

use Modules\AcademicOffers\Infrastructure\Models\AcademicOffer as AcademicOfferModel;
use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\AcademicOffers\Public\DTOs\AcademicOfferSummary;
use Modules\GradeLevels\Public\Contracts\GradeLevelReader;
use Modules\Sections\Public\Contracts\SectionReader;

final class EloquentAcademicOfferReader implements AcademicOfferReader
{
    public function __construct(
        private readonly GradeLevelReader $gradeLevelReader,
        private readonly SectionReader $sectionReader,
    ) {}

    /**
     * @return array<int, AcademicOfferSummary>
     */
    public function allActiveForSchool(int $schoolId): array
    {
        // Relies on the model's own BelongsToTenant + BelongsToActivePeriod
        // scopes (bound to the current request's Tenant/Period context) —
        // `$schoolId` here is the caller's expected tenant, not an extra
        // manual filter.
        return AcademicOfferModel::where('school_id', $schoolId)
            ->get()
            ->map(fn (AcademicOfferModel $offer): AcademicOfferSummary => $this->toSummary($offer))
            ->all();
    }

    /**
     * Crosses the active-period scope on purpose (the caller names the
     * period); the school filter stays explicit.
     *
     * @return array<int, AcademicOfferSummary>
     */
    public function allForPeriod(int $schoolId, int $periodId): array
    {
        return AcademicOfferModel::withoutActivePeriodScope()
            ->where('school_id', $schoolId)
            ->where('periodo_academico_id', $periodId)
            ->orderBy('id')
            ->get()
            ->map(fn (AcademicOfferModel $offer): AcademicOfferSummary => $this->toSummary($offer))
            ->all();
    }

    private function toSummary(AcademicOfferModel $offer): AcademicOfferSummary
    {
        $gradeLevel = $this->gradeLevelReader->find($offer->grado_id);
        $section = $this->sectionReader->find($offer->seccion_id);

        return new AcademicOfferSummary(
            id: $offer->id,
            gradeLevelName: $gradeLevel?->name ?? '—',
            sectionName: $section?->name ?? '—',
            capacity: $offer->capacity,
            gradeLevelId: (int) $offer->grado_id,
            sectionId: (int) $offer->seccion_id,
            teacherId: $offer->teacher_id !== null ? (int) $offer->teacher_id : null,
        );
    }
}
