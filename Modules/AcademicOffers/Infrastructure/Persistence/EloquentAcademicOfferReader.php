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
            ->map(function (AcademicOfferModel $offer): AcademicOfferSummary {
                $gradeLevel = $this->gradeLevelReader->find($offer->grado_id);
                $section = $this->sectionReader->find($offer->seccion_id);

                return new AcademicOfferSummary(
                    id: $offer->id,
                    gradeLevelName: $gradeLevel?->name ?? '—',
                    sectionName: $section?->name ?? '—',
                    capacity: $offer->capacity,
                );
            })
            ->all();
    }
}
