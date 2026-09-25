<?php

namespace Modules\Subjects\Domain\Exceptions;

use DomainException;

/**
 * Reactivating the plan would replace current assignments and the user
 * did not approve those replacements.
 */
final class ReactivationNotApproved extends DomainException
{
    public static function conflicts(int $count): self
    {
        return new self("Reactivation would replace {$count} current assignment(s) and was not approved.");
    }
}
