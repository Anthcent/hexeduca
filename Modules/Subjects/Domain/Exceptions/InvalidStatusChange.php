<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * Archiving an archived record, or reactivating an active one.
 */
final class InvalidStatusChange extends DomainException
{
    public static function alreadyArchived(): self
    {
        return new self('The record is already archived.');
    }

    public static function notArchived(): self
    {
        return new self('The record is not archived.');
    }
}
