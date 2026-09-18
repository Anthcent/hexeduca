<?php

namespace Modules\Users\Domain\Events;

use Modules\Users\Domain\Entities\User;

/**
 * Framework-agnostic domain event. No Eloquent, no framework dependencies.
 */
final class UserRegistered
{
    public function __construct(
        public readonly User $user,
    ) {}
}
