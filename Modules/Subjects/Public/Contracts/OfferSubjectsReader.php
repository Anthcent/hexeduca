<?php

namespace Modules\Subjects\Public\Contracts;

use Modules\Subjects\Public\DTOs\OfferSubjectDTO;

/**
 * Which subjects each academic offer takes in a period, resolved from the
 * study plan assignments (offer beats grade level beats school) minus the
 * assignment's exclusions. Only active subjects are returned.
 */
interface OfferSubjectsReader
{
    /**
     * @return array<int, list<OfferSubjectDTO>> keyed by offer id, in plan order; offers without a plan map to []
     */
    public function forPeriod(int $schoolId, int $periodId): array;

    /**
     * @return list<OfferSubjectDTO>
     */
    public function forOffer(int $schoolId, int $periodId, int $offerId): array;
}
