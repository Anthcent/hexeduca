<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * The plan or subject has related data, so it can only be archived, never
 * deleted.
 */
final class RecordInUse extends DomainException
{
    public static function plan(int $id): self
    {
        return new self("Study plan [{$id}] has subjects or assignments and cannot be deleted.");
    }

    public static function subject(int $id): self
    {
        return new self("Subject [{$id}] is active through an assignment or has exclusions and cannot be deleted.");
    }
}
