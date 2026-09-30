<?php

namespace Modules\TeachingAssignments\Domain\Exceptions;

use DomainException;

final class AssignmentEnded extends DomainException
{
    public static function withId(int $assignmentId): self
    {
        return new self("Teaching assignment [{$assignmentId}] has already ended.");
    }
}
