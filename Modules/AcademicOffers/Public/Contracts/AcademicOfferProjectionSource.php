<?php

namespace Modules\AcademicOffers\Public\Contracts;

use Modules\AcademicOffers\Public\DTOs\AcademicOfferProjectionRow;

/**
 * Stable public contract for sibling modules that need to build/rebuild a
 * local projection of AcademicOffers data (Enrollments, Schedule) without
 * querying Modules\AcademicOffers\Infrastructure\Models directly. See plan
 * §5, §10 (rebuild) and §13 Fase 7-8.
 */
interface AcademicOfferProjectionSource
{
    public function find(int $id): ?AcademicOfferProjectionRow;

    /**
     * Load and lock the source offer for an enrollment capacity decision.
     * Callers must already be inside a database transaction.
     */
    public function findForEnrollment(int $id): ?AcademicOfferProjectionRow;

    /**
     * Full source-of-truth listing, for rebuild commands. Not for
     * request-time hot paths.
     *
     * @return iterable<AcademicOfferProjectionRow>
     */
    public function allForRebuild(): iterable;
}
