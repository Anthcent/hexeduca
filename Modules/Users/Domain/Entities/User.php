<?php

namespace Modules\Users\Domain\Entities;

use Modules\Users\Domain\ValueObjects\Email;

/**
 * Framework-agnostic domain entity. No Eloquent, no framework dependencies.
 */
final class User
{
    public function __construct(
        private readonly ?int $id,
        private string $name,
        private Email $email,
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

    public function renameTo(string $name): void
    {
        $this->name = $name;
    }
}
