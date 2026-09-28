<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * The period is closed (see OpenPeriodPolicy): its assignments and
 * exclusions are history and read-only.
 */
final class PeriodClosed extends DomainException
{
    public static function withId(int $periodId): self
    {
        return new self("Academic period [{$periodId}] is closed; its assignments are read-only.");
    }
}
