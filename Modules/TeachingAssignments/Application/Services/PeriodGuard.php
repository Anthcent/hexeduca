<?php

namespace Modules\TeachingAssignments\Application\Services;

use Modules\AcademicOffers\Public\Contracts\AcademicOfferReader;
use Modules\AcademicOffers\Public\DTOs\AcademicOfferSummary;
use Modules\AcademicPeriods\Public\Contracts\AcademicPeriodReader;
use Modules\AcademicPeriods\Public\DTOs\AcademicPeriodDTO;
use Modules\TeachingAssignments\Domain\Exceptions\InvalidTeachingAssignment;
use Modules\TeachingAssignments\Domain\Exceptions\PeriodClosed;
use Modules\TeachingAssignments\Domain\Services\OpenPeriodPolicy;

/**
 * Resolves the period and offer a request names, inside the school, and
 * refuses changes to a closed period.
 */
final class PeriodGuard
{
    public function __construct(
        private readonly AcademicPeriodReader $periods,
        private readonly AcademicOfferReader $offers,
    ) {}

    public function isOpen(AcademicPeriodDTO $period): bool
    {
        return OpenPeriodPolicy::isOpen($period->isActive, $period->endsOn, now()->toDateString());
    }

    /**
     * @throws InvalidTeachingAssignment when the period is not the school's
     * @throws PeriodClosed
     */
    public function openPeriod(int $periodId, int $schoolId): AcademicPeriodDTO
    {
        $period = $this->periods->findForSchool($periodId, $schoolId) ?? throw InvalidTeachingAssignment::unknownPeriod($periodId);

        if (! $this->isOpen($period)) {
            throw PeriodClosed::withId($periodId);
        }

        return $period;
    }

    /**
     * @throws InvalidTeachingAssignment when the offer is not part of the period
     */
    public function offerInPeriod(int $offerId, int $periodId, int $schoolId): AcademicOfferSummary
    {
        foreach ($this->offers->allForPeriod($schoolId, $periodId) as $offer) {
            if ($offer->id === $offerId) {
                return $offer;
            }
        }

        throw InvalidTeachingAssignment::unknownOffer($offerId);
    }
}
