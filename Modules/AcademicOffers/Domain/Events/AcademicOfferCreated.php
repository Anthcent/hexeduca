<?php

namespace Modules\AcademicOffers\Domain\Events;

use Modules\AcademicOffers\Domain\Entities\AcademicOffer;

/**
 * Framework-agnostic domain event. No Eloquent, no framework dependencies.
 */
final class AcademicOfferCreated
{
    public function __construct(
        public readonly AcademicOffer $academicOffer,
    ) {}
}
