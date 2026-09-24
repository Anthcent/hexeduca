<?php

namespace Modules\AcademicPeriods\Domain\Events;

use Modules\AcademicPeriods\Domain\Entities\AcademicPeriod;

/**
 * Framework-agnostic domain event. No Eloquent, no framework dependencies.
 */
final class AcademicPeriodActivated
{
    public function __construct(
        public readonly AcademicPeriod $academicPeriod,
    ) {}
}
