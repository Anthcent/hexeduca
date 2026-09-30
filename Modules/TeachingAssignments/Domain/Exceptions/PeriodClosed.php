<?php

namespace Modules\TeachingAssignments\Domain\Exceptions;

use DomainException;

final class PeriodClosed extends DomainException
{
    public static function withId(int $periodId): self
    {
        return new self("Academic period [{$periodId}] is closed; its teaching assignments are read-only.");
    }
}
