<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * The assignment does not exist (or is no longer current) in the current school.
 */
final class PlanAssignmentNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Plan assignment [{$id}] was not found.");
    }
}
