<?php

namespace Modules\AcademicMoments\Domain\ValueObjects;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DateRange
{
    public function __construct(
        private DateTimeImmutable $startsOn,
        private DateTimeImmutable $endsOn,
    ) {
        if ($this->endsOn < $this->startsOn) {
            throw new InvalidArgumentException('endsOn must not be before startsOn.');
        }
    }

    public function startsOn(): DateTimeImmutable
    {
        return $this->startsOn;
    }

    public function endsOn(): DateTimeImmutable
    {
        return $this->endsOn;
    }
}
