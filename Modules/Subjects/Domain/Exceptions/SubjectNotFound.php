<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * The subject does not exist in the given plan of the current school.
 */
final class SubjectNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Subject [{$id}] was not found.");
    }
}
