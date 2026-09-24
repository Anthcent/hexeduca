<?php

namespace Modules\AcademicOffers\Domain\Repositories;

use Modules\AcademicOffers\Domain\Entities\AcademicOffer;

interface AcademicOfferRepositoryInterface
{
    public function findById(int $id): ?AcademicOffer;

    /**
     * Finds an existing offering for the same (period, gradeLevel, section)
     * combination — used to enforce the unique-per-cycle constraint before
     * insert, since the catalog pair may legitimately repeat across
     * periods.
     */
    public function findByPeriodGradeLevelSection(int $academicPeriodId, int $gradeLevelId, int $sectionId): ?AcademicOffer;

    public function save(AcademicOffer $academicOffer): AcademicOffer;
}
