<?php

namespace Modules\AcademicOffers\Public\DTOs;

/**
 * Enriched, display-ready projection of an AcademicOffer (grade level and
 * section names already resolved) — for sibling modules building a UI
 * picker (e.g. Enrollments' create-enrollment screen). Distinct from
 * AcademicOfferProjectionRow, which is the raw shape used for
 * projection/rebuild pipelines (plan §5/§10).
 *
 * `gradeLevelId` and `sectionId` were added later (additively) for modules
 * that group or resolve offers by grade level (e.g. Subjects).
 */
final readonly class AcademicOfferSummary
{
    public function __construct(
        public int $id,
        public string $gradeLevelName,
        public string $sectionName,
        public int $capacity,
        public ?int $gradeLevelId = null,
        public ?int $sectionId = null,
    ) {}
}
