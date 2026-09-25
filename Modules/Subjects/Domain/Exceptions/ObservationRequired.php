<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * Another plan of the school already uses the code, so an observation is
 * required to tell the plans apart.
 */
final class ObservationRequired extends DomainException
{
    public static function forDuplicateCode(string $code): self
    {
        return new self("A plan with code [{$code}] already exists; an observation is required.");
    }
}
