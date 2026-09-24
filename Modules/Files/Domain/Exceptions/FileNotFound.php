<?php

namespace Modules\Files\Domain\Exceptions;

use DomainException;

/**
 * The file does not exist in the current school. Callers answer 404 so a
 * file of another school is indistinguishable from a missing one.
 */
final class FileNotFound extends DomainException
{
    public static function withId(int $id): self
    {
        return new self("File [{$id}] was not found.");
    }
}
