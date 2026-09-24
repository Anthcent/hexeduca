<?php

namespace Modules\Users\Domain\Exceptions;

use DomainException;
use Throwable;

/**
 * Raised when a user still has records (enrollments, assigned course
 * offerings, ...) that other modules protect with a restrict-on-delete
 * foreign key.
 */
final class UserCannotBeDeleted extends DomainException
{
    public static function becauseOfDependentRecords(int $userId, ?Throwable $previous = null): self
    {
        return new self("User {$userId} has dependent records and cannot be deleted.", 0, $previous);
    }
}
