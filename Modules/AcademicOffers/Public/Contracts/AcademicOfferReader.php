<?php

namespace Modules\AcademicOffers\Public\Contracts;

use Modules\AcademicOffers\Public\DTOs\AcademicOfferSummary;

/**
 * Request-time, tenant+period-scoped read contract for sibling modules
 * that need a human-readable list of currently offered AcademicOffers
 * (e.g. a UI picker). NOT for background/bulk projection needs — see
 * AcademicOfferProjectionSource for that.
 */
interface AcademicOfferReader
{
    /**
     * @return array<int, AcademicOfferSummary>
     */
    public function allActiveForSchool(int $schoolId): array;
}
