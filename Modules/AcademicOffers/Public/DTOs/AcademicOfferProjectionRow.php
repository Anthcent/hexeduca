<?php

namespace Modules\AcademicOffers\Public\DTOs;

/**
 * Plain read-only projection of an AcademicOffer, for other modules
 * (Enrollments, Schedule) to build/rebuild their own local projections
 * from. See plan §5/§13 Fase 5-7.
 */
final readonly class AcademicOfferProjectionRow
{
    public function __construct(
        public int $id,
        public int $schoolId,
        public int $academicPeriodId,
        public int $gradeLevelId,
        public int $sectionId,
        public ?int $teacherId,
        public int $capacity,
    ) {}
}
