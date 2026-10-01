<?php

namespace Modules\Users\Application\DTOs;

use Modules\Users\Public\Enums\UserType;

final readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public int $schoolId,
        public string $role = 'student',
        public ?UserType $type = null,
    ) {}

    /**
     * The explicit type, or the one implied by the role.
     */
    public function type(): UserType
    {
        return $this->type ?? UserType::forRole($this->role);
    }
}
