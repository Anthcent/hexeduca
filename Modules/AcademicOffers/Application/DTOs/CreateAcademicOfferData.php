<?php

namespace Modules\AcademicOffers\Application\DTOs;

final readonly class CreateAcademicOfferData
{
    public function __construct(
        public int $schoolId,
        public int $academicPeriodId,
        public int $gradeLevelId,
        public int $sectionId,
        public ?int $teacherId,
        public int $capacity,
    ) {}
}
