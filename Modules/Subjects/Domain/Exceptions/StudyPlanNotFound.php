<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * The plan does not exist in the current school. Callers answer 404, so a
 * plan of another school is indistinguishable from a missing one.
 */
final class StudyPlanNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("Study plan [{$id}] was not found.");
    }
}
