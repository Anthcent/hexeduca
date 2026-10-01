<?php

namespace Modules\Users\Domain\Entities;

use Modules\Users\Domain\ValueObjects\Email;
use Modules\Users\Public\Enums\UserType;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 */
final class User
{
    public function __construct(
        private readonly ?int $id,
        private string $name,
        private Email $email,
        private readonly ?string $passwordHash = null,
        private readonly ?int $schoolId = null,
        private readonly UserType $type = UserType::Student,
    ) {}

    public function id(): ?int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function passwordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function schoolId(): ?int
    {
        return $this->schoolId;
    }

    public function type(): UserType
    {
        return $this->type;
    }

    public function renameTo(string $name): void
    {
        $this->name = $name;
    }
}
