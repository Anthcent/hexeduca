<?php

namespace Modules\AcademicPeriods\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Validates a period's start/end date range.
 *
 * Each module owns its own copy of this value object — deliberately not
 * imported from Modules\Academic, per the no-cross-module-imports rule.
 */
final class DateRange
{
    private DateTimeImmutable $startsOn;

    private DateTimeImmutable $endsOn;

    public function __construct(DateTimeImmutable $startsOn, DateTimeImmutable $endsOn)
    {
        if ($endsOn < $startsOn) {
            throw new InvalidArgumentException('Range end date must not precede its start date.');
        }

        $this->startsOn = $startsOn;
        $this->endsOn = $endsOn;
    }

    public function startsOn(): DateTimeImmutable
    {
        return $this->startsOn;
    }

    public function endsOn(): DateTimeImmutable
    {
        return $this->endsOn;
    }

    public function contains(DateTimeImmutable $date): bool
    {
        return $date >= $this->startsOn && $date <= $this->endsOn;
    }
}
